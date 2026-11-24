<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Closure;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use DomainException;
use JsonException;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedJsonType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use Nowo\DoctrineEncryptBundle\Exception\PropertyWriteFailedException;
use Nowo\DoctrineEncryptBundle\Form\SecretFieldApplier;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Twig\DecryptExtension;
use Nowo\DoctrineEncryptBundle\Twig\MaskExtension;
use Nowo\DoctrineEncryptBundle\Util\EncryptedValue;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtilInterface;
use Nowo\DoctrineEncryptBundle\Util\MaskUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use SensitiveParameter;
use SensitiveParameterValue;
use Throwable;

use function is_array;
use function is_scalar;
use function sprintf;
use function strlen;

/**
 * Audit ZAW-I4: plaintext and key material passed to the encryptors never appear in exception stack traces.
 *
 * PHP 8.2+ replaces a #[\SensitiveParameter] argument with a {@see SensitiveParameterValue} in every trace frame, even
 * when `zend.exception_ignore_args` is off (development php.ini), so loggers and error pages cannot print it.
 */
final class SensitiveParameterRedactionTest extends TestCase
{
    private string|false $previousIgnoreArgs = false;

    protected function setUp(): void
    {
        // Traces only carry arguments when exception_ignore_args is off: force it so the redaction is really tested.
        $this->previousIgnoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        if ($this->previousIgnoreArgs !== false) {
            ini_set('zend.exception_ignore_args', $this->previousIgnoreArgs);
        }
    }

    public function testHaliteEncryptRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $encryptor = new HaliteEncryptor('', null, false);

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt($plaintext));

        $this->assertFrameRedacts($e, HaliteEncryptor::class, 'encrypt', 0, $plaintext);
    }

    public function testHaliteKeyContentIsRedactedInTrace(): void
    {
        // Not a valid hex Halite key: importing it throws inside loadKeyFromString().
        $keyContent = $this->marker();
        $encryptor  = new HaliteEncryptor('', $keyContent, false);

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt('x'));

        $this->assertFrameRedacts($e, HaliteEncryptor::class, 'loadKeyFromString', 0, $keyContent);
    }

    public function testDefuseEncryptRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $encryptor = new DefuseEncryptor('', null, false);

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt($plaintext));

        $this->assertFrameRedacts($e, DefuseEncryptor::class, 'encrypt', 0, $plaintext);
    }

    public function testMysqlAesEncryptRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $encryptor = new MysqlAesEncryptor(sys_get_temp_dir() . '/missing-' . uniqid('', true) . '.key');

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt($plaintext));

        $this->assertFrameRedacts($e, MysqlAesEncryptor::class, 'encrypt', 0, $plaintext);
    }

    public function testEncryptUtilRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $failing   = $this->createMock(EncryptorInterface::class);
        // Thrown from inside the call (an exception built up front would carry the test's trace, not EncryptUtil's).
        $failing->method('encrypt')->willReturnCallback(static fn (): never => throw new RuntimeException('encryptor failure'));
        $registry = $this->createMock(EncryptorRegistryInterface::class);
        $registry->method('getDefault')->willReturn($failing);
        $util = new EncryptUtil($registry);

        $e = $this->catchThrowable(static fn (): ?string => $util->encrypt($plaintext));

        // The (mock) encryptor receives the plaintext by design: check the bundle's frames.
        $this->assertFrameRedacts($e, EncryptUtil::class, 'encrypt', 0, $plaintext, null, false);
    }

    public function testEncryptedJsonTypeDecodeFailureDoesNotLeakPlaintext(): void
    {
        // A legacy unmarked row is passed to fromPlaintext() as is; invalid JSON makes json_decode() throw.
        $stored = '{"k":"' . ($secret = $this->marker());
        $type   = new EncryptedJsonType();

        $e = $this->catchThrowable(fn (): mixed => $type->convertToPHPValue($stored, $this->createMock(AbstractPlatform::class)));

        $this->assertInstanceOf(JsonException::class, $e);
        $this->assertNull($e->getPrevious(), 'The json_decode() frame of the original exception carries the plaintext');
        $this->assertFrameRedacts($e, EncryptedJsonType::class, 'fromPlaintext', 0, $secret, $stored);
        $this->assertFrameRedacts($e, AbstractEncryptedType::class, 'convertToPHPValue', 0, $secret, $stored);
        foreach ($e->getTrace() as $frame) {
            $this->assertNotSame('json_decode', $frame['function']);
        }
    }

    public function testEncryptedJsonTypeEncodeFailureDoesNotLeakPlaintext(): void
    {
        // Invalid UTF-8 makes json_encode() throw before anything is encrypted.
        $value = ['k' => "\xB1" . ($secret = $this->marker())];
        $type  = new EncryptedJsonType();

        $e = $this->catchThrowable(fn (): ?string => $type->convertToDatabaseValue($value, $this->createMock(AbstractPlatform::class)));

        $this->assertInstanceOf(JsonException::class, $e);
        $this->assertNull($e->getPrevious());
        $this->assertFrameRedacts($e, EncryptedJsonType::class, 'toPlaintext', 0, $secret, $value);
        $this->assertFrameRedacts($e, AbstractEncryptedType::class, 'convertToDatabaseValue', 0, $secret, $value);
    }

    public function testSecretFieldApplierRedactsSubmittedSecret(): void
    {
        $secret = $this->marker();
        $setter = static function (?string $value): never {
            throw new RuntimeException('host setter refused the value');
        };

        $e = $this->catchThrowable(static fn () => SecretFieldApplier::apply(false, $secret, $setter));
        // The host setter receives the secret by design: check the bundle's frames.
        $this->assertFrameRedacts($e, SecretFieldApplier::class, 'apply', 1, $secret, null, false);

        $data = [SecretFieldApplier::KEY_VALUE => $secret, SecretFieldApplier::KEY_CLEAR => false];
        $e    = $this->catchThrowable(static fn () => SecretFieldApplier::applyData($data, $setter));
        $this->assertFrameRedacts($e, SecretFieldApplier::class, 'applyData', 0, $secret, $data, false);
        $this->assertFrameRedacts($e, SecretFieldApplier::class, 'apply', 1, $secret, null, false);
    }

    public function testListenerWrapsASetterFailureWithoutTheValue(): void
    {
        $secret = $this->marker();
        $entity = new class {
            public ?string $secret = null;

            public function setSecret(?string $secret): never
            {
                // A host setter whose message quotes the value: neither it nor its trace may surface.
                throw new DomainException('invalid secret ' . $secret);
            }
        };
        $subscriber = new DoctrineEncryptSubscriber();
        $write      = Closure::bind(fn () => $this->writeProperty($entity, new ReflectionProperty($entity, 'secret'), $secret, 'default'), $subscriber, DoctrineEncryptSubscriber::class);

        $e = $this->catchThrowable($write);

        $this->assertInstanceOf(PropertyWriteFailedException::class, $e);
        $this->assertNull($e->getPrevious());
        $this->assertStringContainsString(DomainException::class, $e->getMessage());
        $this->assertFrameRedacts($e, DoctrineEncryptSubscriber::class, 'writeProperty', 2, $secret);
    }

    public function testListenerWrapsATypedPropertyErrorWithoutTheValue(): void
    {
        $secret = $this->marker();
        $entity = new class {
            public int $secret = 0;
        };
        $subscriber = new DoctrineEncryptSubscriber(null, null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP, null, DoctrineEncryptSubscriber::PROPERTY_ACCESS_REFLECTION);
        $write      = Closure::bind(fn () => $this->writeProperty($entity, new ReflectionProperty($entity, 'secret'), $secret, 'default'), $subscriber, DoctrineEncryptSubscriber::class);

        $e = $this->catchThrowable($write);

        $this->assertInstanceOf(PropertyWriteFailedException::class, $e);
        $this->assertStringContainsString('TypeError', $e->getMessage());
        $this->assertNull($e->getPrevious());
        $this->assertLeaksNowhere($e, $secret);
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function sensitiveParameters(): iterable
    {
        yield 'Halite key content' => [HaliteEncryptor::class, '__construct', 'keyContent'];
        yield 'Halite plaintext' => [HaliteEncryptor::class, 'encrypt', 'data'];
        yield 'Halite key import' => [HaliteEncryptor::class, 'loadKeyFromString', 'content'];
        yield 'Defuse key content' => [DefuseEncryptor::class, '__construct', 'keyContent'];
        yield 'Defuse plaintext' => [DefuseEncryptor::class, 'encrypt', 'data'];
        yield 'MysqlAes key content' => [MysqlAesEncryptor::class, '__construct', 'keyContent'];
        yield 'MysqlAes plaintext' => [MysqlAesEncryptor::class, 'encrypt', 'data'];
        yield 'MysqlAes passphrase' => [MysqlAesEncryptor::class, 'deriveKey', 'passphrase'];
        yield 'EncryptorInterface plaintext' => [EncryptorInterface::class, 'encrypt', 'data'];
        yield 'EncryptUtil plaintext' => [EncryptUtil::class, 'encrypt', 'value'];
        yield 'EncryptUtilInterface plaintext' => [EncryptUtilInterface::class, 'encrypt', 'value'];
        yield 'DBAL type value' => [AbstractEncryptedType::class, 'convertToDatabaseValue', 'value'];
        yield 'DBAL string type plaintext' => [EncryptedStringType::class, 'toPlaintext', 'value'];
        yield 'DBAL json type plaintext' => [EncryptedJsonType::class, 'toPlaintext', 'value'];
        yield 'DBAL stored value (legacy plaintext rows)' => [AbstractEncryptedType::class, 'convertToPHPValue', 'value'];
        yield 'DBAL decrypted plaintext' => [AbstractEncryptedType::class, 'fromPlaintext', 'plaintext'];
        yield 'DBAL string type decrypted plaintext' => [EncryptedStringType::class, 'fromPlaintext', 'plaintext'];
        yield 'DBAL json type decrypted plaintext' => [EncryptedJsonType::class, 'fromPlaintext', 'plaintext'];
        yield 'EncryptUtil decrypt (plaintext passes through)' => [EncryptUtil::class, 'decrypt', 'value'];
        yield 'EncryptUtil decryptStrict (plaintext passes through)' => [EncryptUtil::class, 'decryptStrict', 'value'];
        yield 'EncryptUtilInterface decrypt' => [EncryptUtilInterface::class, 'decrypt', 'value'];
        yield 'EncryptUtilInterface decryptStrict' => [EncryptUtilInterface::class, 'decryptStrict', 'value'];
        yield 'Twig decrypt filter' => [DecryptExtension::class, 'decrypt', 'value'];
        yield 'Twig mask filter' => [MaskExtension::class, 'mask', 'value'];
        yield 'Twig secret_hint filter' => [MaskExtension::class, 'secretHint', 'value'];
        yield 'MaskUtil mask' => [MaskUtil::class, 'mask', 'value'];
        yield 'MaskUtil secretHint' => [MaskUtil::class, 'secretHint', 'secret'];
        yield 'MaskUtil splitCharacters' => [MaskUtil::class, 'splitCharacters', 'value'];
        yield 'EncryptedValue hasMarker' => [EncryptedValue::class, 'hasMarker', 'value'];
        yield 'EncryptedValue payload' => [EncryptedValue::class, 'payload', 'value'];
        yield 'EncryptedValue isDecryptable' => [EncryptedValue::class, 'isDecryptable', 'value'];
        yield 'SecretFieldApplier apply' => [SecretFieldApplier::class, 'apply', 'plain'];
        yield 'SecretFieldApplier applyData' => [SecretFieldApplier::class, 'applyData', 'data'];
        yield 'Listener plaintext check' => [DoctrineEncryptSubscriber::class, 'isCiphertext', 'value'];
        yield 'Listener property write' => [DoctrineEncryptSubscriber::class, 'writeProperty', 'value'];
        yield 'Listener swap record (before)' => [DoctrineEncryptSubscriber::class, 'recordSwap', 'before'];
        yield 'Listener swap record (after)' => [DoctrineEncryptSubscriber::class, 'recordSwap', 'after'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('sensitiveParameters')]
    public function testParameterIsMarkedSensitive(string $class, string $method, string $parameter): void
    {
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $param) {
            if ($param->getName() === $parameter) {
                $this->assertCount(1, $param->getAttributes(SensitiveParameter::class), sprintf('%s::%s($%s) must be #[\SensitiveParameter]', $class, $method, $parameter));

                return;
            }
        }

        self::fail(sprintf('Parameter $%s not found on %s::%s()', $parameter, $class, $method));
    }

    /**
     * At most 15 bytes on purpose: getTraceAsString() prints string arguments truncated to 15 bytes, so a longer marker
     * could never show up there and the string assertions below would be vacuous (audit ZBC-I1).
     */
    private function marker(): string
    {
        return 'Z' . bin2hex(random_bytes(6));
    }

    private function catchThrowable(callable $call): Throwable
    {
        try {
            $call();
        } catch (Throwable $e) {
            return $e;
        }

        self::fail('An exception was expected.');
    }

    /**
     * Asserts the $class::$function frame argument $index is a SensitiveParameterValue holding $expected, and that the
     * $secret marker appears nowhere: not in any frame argument outside this test class (whose closures legitimately
     * capture it), not in getTraceAsString() and not in (string) $e, for the exception and every previous one.
     * $wholeTrace false limits the argument check to the bundle's frames, for calls that hand the value on to code the
     * bundle does not own (a host encryptor or setter, here a mock or a closure, receives it by design).
     */
    private function assertFrameRedacts(Throwable $e, string $class, string $function, int $index, string $secret, mixed $expected = null, bool $wholeTrace = true): void
    {
        self::assertLessThanOrEqual(15, strlen($secret), 'Marker must fit the 15-byte argument preview of getTraceAsString()');
        $found = false;
        for ($t = $e; $t instanceof Throwable; $t = $t->getPrevious()) {
            foreach ($t->getTrace() as $frame) {
                if (($frame['class'] ?? null) === $class && $frame['function'] === $function) {
                    $found = true;
                    $this->assertArrayHasKey('args', $frame, 'Trace arguments must be collected for this test');
                    $arg = $frame['args'][$index] ?? null;
                    $this->assertInstanceOf(SensitiveParameterValue::class, $arg);
                    $this->assertSame($expected ?? $secret, $arg->getValue(), 'The original value stays reachable for deliberate use only');
                }
            }
            $this->assertStringNotContainsString($secret, $t->getMessage());
            $this->assertStringNotContainsString($secret, $this->frameArguments($t, !$wholeTrace));
            if ($wholeTrace) {
                $this->assertStringNotContainsString($secret, $t->getTraceAsString());
            }
        }

        $this->assertTrue($found, sprintf('No %s::%s() frame in the trace', $class, $function));
        if ($wholeTrace) {
            $this->assertStringNotContainsString($secret, (string) $e);
        }
    }

    private function assertLeaksNowhere(Throwable $e, string $secret): void
    {
        for ($t = $e; $t instanceof Throwable; $t = $t->getPrevious()) {
            $this->assertStringNotContainsString($secret, $this->frameArguments($t));
            $this->assertStringNotContainsString($secret, $t->getMessage());
        }
        $this->assertStringNotContainsString($secret, (string) $e);
    }

    /**
     * Scalar and array arguments of every frame outside this test class (whose closures legitimately capture the
     * value), or of the bundle's own frames only when $bundleOnly is true, as text. Objects are named by class only: dumping the runner's objects would be huge, and a leaked
     * plaintext is a string (or an array of strings) argument.
     */
    private function frameArguments(Throwable $t, bool $bundleOnly = false): string
    {
        $out = '';
        foreach ($t->getTrace() as $frame) {
            $class = $frame['class'] ?? '';
            if ($class === self::class || ($bundleOnly && (!str_starts_with($class, 'Nowo\\DoctrineEncryptBundle\\') || str_starts_with($class, 'Nowo\\DoctrineEncryptBundle\\Tests\\')))) {
                continue;
            }
            foreach ($frame['args'] ?? [] as $arg) {
                $out .= ($frame['class'] ?? '') . '::' . $frame['function'] . ' ' . (is_scalar($arg) || is_array($arg) ? var_export($arg, true) : get_debug_type($arg)) . "\n";
            }
        }

        return $out;
    }
}
