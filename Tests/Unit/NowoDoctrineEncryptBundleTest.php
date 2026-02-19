<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit;

use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle;
use PHPUnit\Framework\TestCase;

class NowoDoctrineEncryptBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsDoctrineEncryptExtension(): void
    {
        $bundle = new NowoDoctrineEncryptBundle();

        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(DoctrineEncryptExtension::class, $extension);
    }
}
