<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Configuration;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use PHPUnit\Framework\TestCase;

class EncryptedTest extends TestCase
{
    public function testConstructorDefault(): void
    {
        $attr = new Encrypted();
        $this->assertSame('default', $attr->config);
    }

    public function testConstructorWithStringConfig(): void
    {
        $attr = new Encrypted('personal_data');
        $this->assertSame('personal_data', $attr->config);
    }

    public function testConstructorWithSecondParameter(): void
    {
        $attr = new Encrypted('default', 'financial_data');
        $this->assertSame('financial_data', $attr->config);
    }

    public function testConstructorWithArrayConfigKey(): void
    {
        $attr = new Encrypted(['config' => 'personal_data']);
        $this->assertSame('personal_data', $attr->config);
    }

    public function testConstructorWithArrayValueKey(): void
    {
        $attr = new Encrypted(['value' => 'financial_data']);
        $this->assertSame('financial_data', $attr->config);
    }

    public function testConstructorWithArrayFallsBackToDefault(): void
    {
        $attr = new Encrypted([]);
        $this->assertSame('default', $attr->config);
    }
}
