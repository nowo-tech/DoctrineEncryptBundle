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

    public function testSecretHintReturnsNullWhenNothingSaved(): void
    {
        $this->assertNull(MaskUtil::secretHint(null));
        $this->assertNull(MaskUtil::secretHint(''));
    }

    public function testSecretHintShowsBulletsAndLastFour(): void
    {
        $this->assertSame('•••• 1234', MaskUtil::secretHint('re_live_abcd1234'));
        $this->assertSame('•••• 89', MaskUtil::secretHint('123456789', 2));
        $this->assertSame('*** ñüé€', MaskUtil::secretHint('contraseñañüé€', 4, '***'));
    }

    public function testSecretHintNeverRevealsShortSecrets(): void
    {
        $this->assertSame('••••', MaskUtil::secretHint('12345678'));
        $this->assertSame('••••', MaskUtil::secretHint('abc'));
        $this->assertSame('••••', MaskUtil::secretHint('re_live_abcd1234', 0));
    }

    public function testSecretHintFallsBackToBytesForInvalidUtf8(): void
    {
        $this->assertSame('•••• ' . "\xFFabc", MaskUtil::secretHint("0123456789\xFFabc"));
    }
}
