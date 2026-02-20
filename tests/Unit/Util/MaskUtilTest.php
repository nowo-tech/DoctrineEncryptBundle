<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Util;

use Nowo\DoctrineEncryptBundle\Util\MaskUtil;
use PHPUnit\Framework\TestCase;

class MaskUtilTest extends TestCase
{
    public function testMaskReturnsNullForNull(): void
    {
        $this->assertNull(MaskUtil::mask(null));
    }

    public function testMaskReturnsEmptyForEmptyString(): void
    {
        $this->assertSame('', MaskUtil::mask(''));
    }

    public function testMaskShowsReplacementPlusLastFourByDefault(): void
    {
        $this->assertSame('****5678', MaskUtil::mask('12345678'));
        $this->assertSame('****abcd', MaskUtil::mask('xyzabcd'));
    }

    public function testMaskWithCustomVisibleLast(): void
    {
        $this->assertSame('****678', MaskUtil::mask('12345678', 3));
        $this->assertSame('****45678', MaskUtil::mask('12345678', 5));
    }

    public function testMaskWithCustomReplacement(): void
    {
        $this->assertSame('••••5678', MaskUtil::mask('12345678', 4, '••••'));
        $this->assertSame('***5678', MaskUtil::mask('12345678', 4, '***'));
    }

    public function testMaskWhenLengthLessThanOrEqualVisibleLastReturnsOnlyReplacement(): void
    {
        $this->assertSame('****', MaskUtil::mask('12', 4));
        $this->assertSame('****', MaskUtil::mask('1234', 4));
    }

    public function testMaskWithZeroVisibleLastReturnsOnlyReplacement(): void
    {
        $this->assertSame('****', MaskUtil::mask('12345678', 0));
    }
}
