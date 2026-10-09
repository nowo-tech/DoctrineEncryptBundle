<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Twig;

use Nowo\DoctrineEncryptBundle\Twig\MaskExtension;
use PHPUnit\Framework\TestCase;

final class MaskExtensionTest extends TestCase
{
    public function testMaskFilter(): void
    {
        self::assertSame('****5678', (new MaskExtension())->mask(12345678));
        self::assertNull((new MaskExtension())->mask(null));
    }

    public function testSecretHintFilter(): void
    {
        $extension = new MaskExtension();

        self::assertSame('•••• 1234', $extension->secretHint('re_live_abcd1234'));
        self::assertSame('**** 34', $extension->secretHint('re_live_abcd1234', 2, '****'));
        self::assertNull($extension->secretHint(null));
        self::assertNull($extension->secretHint(''));
    }
}
