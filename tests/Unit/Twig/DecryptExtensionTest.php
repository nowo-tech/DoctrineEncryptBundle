<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Twig;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Twig\DecryptExtension;
use Nowo\DoctrineEncryptBundle\Twig\MaskExtension;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use PHPUnit\Framework\TestCase;

class DecryptExtensionTest extends TestCase
{
    public function testDecryptReturnsNullForNull(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);
        $this->assertNull($ext->decrypt(null));
    }

    public function testDecryptReturnsEmptyStringForEmptyString(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);
        $this->assertSame('', $ext->decrypt(''));
    }

    public function testDecryptReturnsValueAsIsWhenNoMarker(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);
        $this->assertSame('plain', $ext->decrypt('plain'));
    }

    public function testDecryptCastsNonStringToStringThenReturnsAsIsWhenNoMarker(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);
        $this->assertSame(42, $ext->decrypt(42));
    }

    public function testDecryptUsesDefaultConfigWhenMarkerPresent(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('cipher')->willReturn('secret');
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $ext      = new DecryptExtension($registry);
        $this->assertSame('secret', $ext->decrypt('cipher' . EncryptUtil::ENCRYPTION_MARKER));
    }

    public function testDecryptUsesNamedConfigWhenMarkerPresent(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('enc')->willReturn('iban');
        $registry = new EncryptorRegistry(['financial_data' => $encryptor], 'default');
        $ext      = new DecryptExtension($registry);
        $this->assertSame('iban', $ext->decrypt('enc' . EncryptUtil::ENCRYPTION_MARKER, 'financial_data'));
    }

    public function testDecryptThrowsWhenConfigDoesNotExist(): void
    {
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $ext      = new DecryptExtension($registry);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown encryptor config');
        $this->expectExceptionMessage('non_existent');

        $ext->decrypt('cipher' . EncryptUtil::ENCRYPTION_MARKER, 'non_existent');
    }

    public function testDecryptReturnsEmptyStringWhenValueCastsToEmptyString(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);

        $value = new class {
            public function __toString(): string
            {
                return '';
            }
        };

        $this->assertSame('', $ext->decrypt($value));
    }

    public function testDecryptWithConfigNullUsesDefaultEncryptor(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())->method('decrypt')->with('x')->willReturn('decrypted');
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $ext      = new DecryptExtension($registry);

        $this->assertSame('decrypted', $ext->decrypt('x' . EncryptUtil::ENCRYPTION_MARKER, null));
    }

    public function testDecryptReturnsIntegerZeroAsIsWhenNoMarker(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);

        $this->assertSame(0, $ext->decrypt(0));
    }

    public function testDecryptReturnsStringZeroWhenNoMarker(): void
    {
        $registry = new EncryptorRegistry([], 'default');
        $ext      = new DecryptExtension($registry);

        $this->assertSame('0', $ext->decrypt('0'));
    }

    public function testMaskReturnsNullForNull(): void
    {
        $ext = new MaskExtension();
        $this->assertNull($ext->mask(null));
    }

    public function testMaskShowsReplacementPlusLastFour(): void
    {
        $ext = new MaskExtension();
        $this->assertSame('****5678', $ext->mask('12345678'));
    }

    public function testMaskWithCustomVisibleLastAndReplacement(): void
    {
        $ext = new MaskExtension();
        $this->assertSame('••••78', $ext->mask('12345678', 2, '••••'));
    }
}
