<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use PHPUnit\Framework\TestCase;

class EncryptorRegistryTest extends TestCase
{
    public function testGetReturnsEncryptorByName(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['personal_data' => $encryptor], 'personal_data');

        $this->assertSame($encryptor, $registry->get('personal_data'));
    }

    public function testGetThrowsForUnknownName(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['personal_data' => $encryptor], 'personal_data');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown encryptor config "other"');
        $this->expectExceptionMessage('personal_data');

        $registry->get('other');
    }

    public function testGetDefaultReturnsDefaultEncryptor(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');

        $this->assertSame($encryptor, $registry->getDefault());
    }

    public function testGetDefaultName(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['a' => $encryptor, 'b' => $encryptor], 'b');

        $this->assertSame('b', $registry->getDefaultName());
    }

    public function testHasReturnsTrueWhenConfigExists(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['personal_data' => $encryptor], 'personal_data');

        $this->assertTrue($registry->has('personal_data'));
    }

    public function testHasReturnsFalseWhenConfigMissing(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['personal_data' => $encryptor], 'personal_data');

        $this->assertFalse($registry->has('financial_data'));
    }

    public function testGetConfigNamesReturnsAllKeys(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry([
            'personal_data' => $encryptor,
            'financial_data' => $encryptor,
        ], 'personal_data');

        $this->assertSame(['personal_data', 'financial_data'], $registry->getConfigNames());
    }
}
