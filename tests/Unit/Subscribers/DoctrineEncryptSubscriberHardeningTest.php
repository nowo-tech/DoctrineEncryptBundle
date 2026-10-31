<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\CountingStrictEncryptor;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\EntityWithoutAccessors;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\StrictUser;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\WithUser;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/**
 * Marker handling (plaintext ending with <ENC>), on_decrypt_failure and the reflection fallback.
 */
final class DoctrineEncryptSubscriberHardeningTest extends TestCase
{
    private function strictEncryptor(): CountingStrictEncryptor
    {
        return new CountingStrictEncryptor();
    }

    public function testPlaintextEndingWithTheMarkerIsEncryptedAndRoundTrips(): void
    {
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor);
        $user       = new User('smtp://u:not-a-secret@h:25<ENC>', '<ENC>');

        $subscriber->processFields($user, true);

        $this->assertSame('C(' . base64_encode('smtp://u:not-a-secret@h:25<ENC>') . ')<ENC>', $user->name);
        $this->assertSame('C(' . base64_encode('<ENC>') . ')<ENC>', $user->getAddress());
        $this->assertSame(2, $subscriber->encryptCounter);

        $fresh = new User($user->name, $user->getAddress());
        $subscriber->processFields($fresh, false);

        $this->assertSame('smtp://u:not-a-secret@h:25<ENC>', $fresh->name);
        $this->assertSame('<ENC>', $fresh->getAddress());
    }

    public function testDecryptedPlaintextEndingWithTheMarkerIsNotDecryptedAgain(): void
    {
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor, null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW);
        $user       = new User($encryptor->encrypt('pw<ENC>') . '<ENC>', null);

        $subscriber->processFields($user, false);
        $subscriber->processFields($user, false);

        $this->assertSame('pw<ENC>', $user->name);
        $this->assertSame(1, $encryptor->decryptCalls);
    }

    public function testCiphertextAssignedByTheCallerIsNotEncryptedTwice(): void
    {
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor);
        $ciphertext = $encryptor->encrypt('already') . '<ENC>';
        $user       = new User($ciphertext, null);

        $subscriber->processFields($user, true);
        $subscriber->processFields($user, true);

        $this->assertSame($ciphertext, $user->name);
        $this->assertSame(0, $subscriber->encryptCounter);
        $this->assertSame(1, $encryptor->decryptCalls, 'verified once, then remembered');
    }

    public function testCiphertextWrittenByTheListenerIsNotDecryptedToBeRecognised(): void
    {
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor);
        $user       = new User('David', null);

        $subscriber->processFields($user, true);
        $ciphertext = $user->name;
        $subscriber->processFields($user, true);

        $this->assertSame($ciphertext, $user->name);
        $this->assertSame(1, $encryptor->encryptCalls);
        $this->assertSame(0, $encryptor->decryptCalls);
    }

    public function testRestoredCiphertextIsRememberedAfterPreFlushForgetsTheDecryption(): void
    {
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor);
        $stored     = $encryptor->encrypt('David') . '<ENC>';
        $user       = new User($stored, null);

        $subscriber->processFields($user, false);
        $subscriber->processFields($user, true);
        $subscriber->clearDecryptionCache();
        $subscriber->processFields($user, true);

        $this->assertSame($stored, $user->name);
        $this->assertSame(1, $encryptor->encryptCalls, 'only the fixture ciphertext');
    }

    public function testKeepModeKeepsTheRawValueAndWritesTheCiphertextBack(): void
    {
        $subscriber = new DoctrineEncryptSubscriber($this->strictEncryptor());
        $user       = new User('garbage<ENC>', null);

        $subscriber->processFields($user, false);
        $this->assertSame('garbage', $user->name);

        $subscriber->processFields($user, true);
        $this->assertSame('garbage<ENC>', $user->name);
        $this->assertSame(0, $subscriber->encryptCounter);
    }

    public function testNullModeSetsNullLogsAndWritesTheCiphertextBackOnFlush(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor, $logger, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL);
        $user       = new User($encryptor->encrypt('David') . '<ENC>', 'wrong-key-ciphertext<ENC>');

        $subscriber->processFields($user, false);

        $this->assertSame('David', $user->name);
        $this->assertNull($user->getAddress());
        $this->assertCount(1, $logger->records);
        $this->assertSame('The property is set to null.', $logger->records[0]['context']['outcome']);
        $this->assertSame('address', $logger->records[0]['context']['property']);

        // The listener must not turn the undecryptable ciphertext into NULL in the database.
        $subscriber->processFields($user, true);
        $this->assertSame('wrong-key-ciphertext<ENC>', $user->getAddress());

        // An explicit new value still replaces it.
        $subscriber->processFields($user, false);
        $user->setAddress('Spain');
        $subscriber->processFields($user, true);
        $this->assertStringStartsWith('C(', (string) $user->getAddress());
    }

    public function testNullModeOnANonNullablePropertyFailsClosed(): void
    {
        $subscriber = new DoctrineEncryptSubscriber($this->strictEncryptor(), null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL);
        $user       = new StrictUser('wrong-key-ciphertext<ENC>');

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage(StrictUser::class . '::$name');

        $subscriber->processFields($user, false);
    }

    public function testThrowModeThrowsWithoutTheValue(): void
    {
        $subscriber = new DoctrineEncryptSubscriber($this->strictEncryptor(), null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW);
        $user       = new User('top-secret-ciphertext<ENC>', null);

        try {
            $subscriber->processFields($user, false);
            $this->fail('DecryptionFailedException expected');
        } catch (DecryptionFailedException $e) {
            $this->assertStringContainsString(User::class . '::$name (config "default")', $e->getMessage());
            $this->assertStringNotContainsString('top-secret', $e->getMessage());
            $this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
        }
    }

    public function testInvalidModeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid on_decrypt_failure "ignore"');

        new DoctrineEncryptSubscriber(null, null, 'ignore');
    }

    public function testPropertiesWithoutUsableAccessorsUseReflection(): void
    {
        $encryptor  = $this->strictEncryptor();
        $subscriber = new DoctrineEncryptSubscriber($encryptor);
        $entity     = EntityWithoutAccessors::create('tok', 'lbl');

        $subscriber->processFields($entity, true);

        $this->assertSame('C(' . base64_encode('tok') . ')<ENC>', $entity->token());
        $this->assertSame('C(' . base64_encode('lbl') . ')<ENC>', $entity->getLabel());
        $this->assertSame(0, $entity->labelSetterCalls, 'a two-argument setter is never called');
        $this->assertFalse($entity->isNeverSetInitialized(), 'an uninitialized property is left alone');

        $subscriber->processFields($entity, false);

        $this->assertSame('tok', $entity->token());
        $this->assertSame('lbl', $entity->getLabel());
    }

    public function testEmbeddedObjectIsReadThroughTheSameAccessors(): void
    {
        $subscriber = new DoctrineEncryptSubscriber($this->strictEncryptor());
        $withUser   = new WithUser('thing', null, new User('David', null));

        $subscriber->processFields($withUser, true);

        $this->assertStringStartsWith('C(', $withUser->user->name);
    }
}
