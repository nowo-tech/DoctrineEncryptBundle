<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Twig;

use Nowo\DoctrineEncryptBundle\Twig\MaskExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

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

    public function testFiltersAreRegisteredWithoutAttributes(): void
    {
        $names = array_map(static fn (TwigFilter $filter): string => $filter->getName(), (new MaskExtension())->getFilters());

        self::assertSame(['mask', 'secret_hint'], $names);
    }

    public function testMaskOutputIsAutoEscaped(): void
    {
        $twig = new Environment(new ArrayLoader([
            'tail'        => '{{ value|mask(30) }}',
            'replacement' => '{{ value|mask(2, replacement) }}',
            'hint'        => '{{ value|secret_hint(30) }}',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new MaskExtension());

        $payload = 'xxxxxxxxxx<script>alert(1)</script>';
        $tail    = $twig->render('tail', ['value' => $payload]);
        self::assertStringNotContainsString('<script>', $tail);
        self::assertStringContainsString('&lt;script&gt;', $tail);

        $replacement = $twig->render('replacement', ['value' => '12345678', 'replacement' => '<img src=x onerror=alert(1)>']);
        self::assertSame('&lt;img src=x onerror=alert(1)&gt;78', $replacement);

        self::assertStringNotContainsString('<script>', $twig->render('hint', ['value' => $payload . $payload]));
    }
}
