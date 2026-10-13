<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Twig;

use Nowo\DoctrineEncryptBundle\Util\MaskUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for masking sensitive values.
 *
 * Provides filter:
 * - mask – masks a plain value (e.g. **** + last N chars).
 * - secret_hint – hint for a saved secret (e.g. •••• 1234), null when empty.
 *
 * Usage: {{ value|mask }} or {{ value|mask(4, '****') }}
 *
 * Filters are registered with getFilters() (not #[AsTwigFilter]) so they work on every supported
 * Twig / TwigBundle version. Output is not marked HTML-safe — Twig auto-escaping applies
 * (do not reintroduce is_safe html: the visible tail and the replacement are user-controlled).
 */
final class MaskExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('mask', $this->mask(...)),
            new TwigFilter('secret_hint', $this->secretHint(...)),
        ];
    }

    /**
     * Masks a plain value: replacement (e.g. ****) + last N characters visible.
     *
     * @param mixed $value Value to mask (string or cast to string)
     * @param int|null $visibleLast Number of characters to leave visible at the end (default 4)
     * @param string|null $replacement String to show instead of hidden part (default '****')
     *
     * @return string|null Masked value, or null if input is null
     */
    public function mask(mixed $value, ?int $visibleLast = 4, ?string $replacement = '****'): ?string
    {
        $str = $value === null ? null : (string) $value;

        return MaskUtil::mask($str, $visibleLast, $replacement);
    }

    /**
     * Hint for a saved secret without revealing it, e.g. "•••• 1234" (null when nothing is saved).
     *
     * Usage: {{ settings.apiKey|secret_hint ?? 'Not set' }} or {{ value|secret_hint(4, '****') }}
     */
    public function secretHint(mixed $value, int $visibleLast = 4, string $mask = '••••'): ?string
    {
        if ($value === null) {
            return null;
        }

        return MaskUtil::secretHint((string) $value, $visibleLast, $mask);
    }
}
