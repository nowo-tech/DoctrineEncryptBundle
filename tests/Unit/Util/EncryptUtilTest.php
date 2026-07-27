<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Util;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use PHPUnit\Framework\TestCase;

class EncryptUtilTest extends TestCase
{
    private function createRegistryWithDefault(EncryptorInterface $encryptor): EncryptorRegistry
    {
        return new EncryptorRegistry(['default' => $encryptor], 'default');
    }

    public function testEncryptReturnsNullForNull(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertNull($util->encrypt(null));
    }

    public function testEncryptReturnsEmptyStringForEmptyString(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('', $util->encrypt(''));
    }

    public function testEncryptReturnsZeroForZero(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('0', $util->encrypt('0'));
    }

    public function testEncryptUsesDefaultConfigAndAppendsMarker(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('encrypt')->with('secret')->willReturn('cipher');
        $registry = $this->createRegistryWithDefault($encryptor);
        $util     = new EncryptUtil($registry);
        $this->assertSame('cipher' . EncryptUtil::ENCRYPTION_MARKER, $util->encrypt('secret'));
    }

    public function testEncryptUsesNamedConfig(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('encrypt')->with('iban')->willReturn('enc');
        $registry = new EncryptorRegistry(['financial_data' => $encryptor], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('enc' . EncryptUtil::ENCRYPTION_MARKER, $util->encrypt('iban', 'financial_data'));
    }

    public function testDecryptReturnsNullForNull(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertNull($util->decrypt(null));
    }

    public function testDecryptReturnsEmptyStringForEmptyString(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('', $util->decrypt(''));
    }

    public function testDecryptReturnsEmptyStringForZero(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('', $util->decrypt('0'));
    }

    public function testDecryptReturnsValueAsIsWhenNoMarker(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('plain', $util->decrypt('plain'));
    }

    public function testDecryptUsesDefaultConfigWhenMarkerPresent(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('cipher')->willReturn('secret');
        $registry = $this->createRegistryWithDefault($encryptor);
        $util     = new EncryptUtil($registry);
        $this->assertSame('secret', $util->decrypt('cipher' . EncryptUtil::ENCRYPTION_MARKER));
    }

    public function testDecryptUsesNamedConfigWhenMarkerPresent(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('enc')->willReturn('iban');
        $registry = new EncryptorRegistry(['financial_data' => $encryptor], 'default');
        $util     = new EncryptUtil($registry);
        $this->assertSame('iban', $util->decrypt('enc' . EncryptUtil::ENCRYPTION_MARKER, 'financial_data'));
    }

    public function testEncryptionMarkerConstant(): void
    {
        $this->assertSame('<ENC>', EncryptUtil::ENCRYPTION_MARKER);
    }

    public function testUtilNameConstant(): void
    {
        $this->assertSame('nowo_doctrine_encrypt.encrypt_util', EncryptUtil::UTIL_NAME);
    }

    public function testEncryptWithConfigNullUsesDefaultEncryptor(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('encrypt')->with('plain')->willReturn('cipher');
        $registry = $this->createRegistryWithDefault($encryptor);
        $util     = new EncryptUtil($registry);

        $this->assertSame('cipher' . EncryptUtil::ENCRYPTION_MARKER, $util->encrypt('plain'));
    }

    public function testEncryptThrowsWhenConfigDoesNotExist(): void
    {
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $util     = new EncryptUtil($registry);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown encryptor config');
        $this->expectExceptionMessage('non_existent');

        $util->encrypt('value', 'non_existent');
    }

    public function testDecryptThrowsWhenConfigDoesNotExist(): void
    {
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $util     = new EncryptUtil($registry);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown encryptor config');
        $this->expectExceptionMessage('non_existent');

        $util->decrypt('cipher' . EncryptUtil::ENCRYPTION_MARKER, 'non_existent');
    }

    public function testDecryptWithConfigNullUsesDefaultEncryptor(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('cipher')->willReturn('plain');
        $registry = $this->createRegistryWithDefault($encryptor);
        $util     = new EncryptUtil($registry);

        $this->assertSame('plain', $util->decrypt('cipher' . EncryptUtil::ENCRYPTION_MARKER));
    }
}
