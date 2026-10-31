<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Exception;

use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Exception\ExceptionInterface;
use Nowo\DoctrineEncryptBundle\Exception\MissingEncryptionKeyException;
use Nowo\DoctrineEncryptBundle\Exception\ValueNotEncryptedException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

final class ExceptionsTest extends TestCase
{
    public function testDecryptionFailedForProperty(): void
    {
        $previous = new RuntimeException('Invalid version tag');
        $e        = DecryptionFailedException::forProperty('App\\Entity\\User', 'iban', 'financial');

        $this->assertContains(ExceptionInterface::class, [...class_implements($e), ...class_parents($e)]);
        $this->assertContains(RuntimeException::class, [...class_implements($e), ...class_parents($e)]);
        $this->assertSame('Could not decrypt App\\Entity\\User::$iban (config "financial"): wrong or rotated key, or corrupted ciphertext.', $e->getMessage());

        $e = DecryptionFailedException::forProperty('App\\Entity\\User', 'iban', 'financial', $previous);
        $this->assertStringEndsWith('Cause: RuntimeException: Invalid version tag', $e->getMessage());
        $this->assertSame($previous, $e->getPrevious());
    }

    public function testDecryptionFailedForValue(): void
    {
        $previous = new RuntimeException('bad');

        $this->assertStringContainsString('(config "default")', DecryptionFailedException::forValue(null, $previous)->getMessage());
        $this->assertStringContainsString('(config "personal")', DecryptionFailedException::forValue('personal', $previous)->getMessage());
        $this->assertSame($previous, DecryptionFailedException::forValue(null, $previous)->getPrevious());
    }

    public function testValueNotEncrypted(): void
    {
        $e = ValueNotEncryptedException::create();

        $this->assertContains(ExceptionInterface::class, [...class_implements($e), ...class_parents($e)]);
        $this->assertContains(UnexpectedValueException::class, [...class_implements($e), ...class_parents($e)]);
        $this->assertStringContainsString('not encrypted', $e->getMessage());
    }

    public function testMissingKey(): void
    {
        $missing = MissingEncryptionKeyException::forFile('/keys/.Halite.default.key');
        $this->assertContains(ExceptionInterface::class, [...class_implements($missing), ...class_parents($missing)]);
        $this->assertContains(RuntimeException::class, [...class_implements($missing), ...class_parents($missing)]);
        $this->assertStringContainsString('"/keys/.Halite.default.key" does not exist', $missing->getMessage());
        $this->assertStringContainsString('doctrine:encrypt:generate-secret-key', $missing->getMessage());
        $this->assertStringContainsString('generate_missing_key: true', $missing->getMessage());

        $this->assertStringContainsString('cannot be read', MissingEncryptionKeyException::unreadable('/k')->getMessage());
    }
}
