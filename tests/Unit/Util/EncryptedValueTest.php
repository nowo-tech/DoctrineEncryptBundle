<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Util;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Util\EncryptedValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptedValueTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function markerCases(): iterable
    {
        yield 'ciphertext + marker' => ['abc<ENC>', true];
        yield 'marker only' => ['<ENC>', false];
        yield 'no marker' => ['abc', false];
        yield 'empty' => ['', false];
        yield 'marker inside' => ['a<ENC>b', false];
    }

    #[DataProvider('markerCases')]
    public function testHasMarker(string $value, bool $expected): void
    {
        $this->assertSame($expected, EncryptedValue::hasMarker($value));
    }

    public function testPayloadStripsTheMarker(): void
    {
        $this->assertSame('abc', EncryptedValue::payload('abc<ENC>'));
    }

    public function testIsDecryptable(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $c): string => $c === 'good' ? 'plain' : throw new RuntimeException('Invalid message'));

        $this->assertTrue(EncryptedValue::isDecryptable('good<ENC>', $encryptor));
        $this->assertFalse(EncryptedValue::isDecryptable('plaintext<ENC>', $encryptor));
        $this->assertFalse(EncryptedValue::isDecryptable('good', $encryptor));
        $this->assertFalse(EncryptedValue::isDecryptable('<ENC>', $encryptor));
    }
}
