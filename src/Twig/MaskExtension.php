<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Twig;

use Nowo\DoctrineEncryptBundle\Util\MaskUtil;
use Twig\Attribute\AsTwigFilter;

/**
 * Twig extension for masking sensitive values.
 *
 * Provides filter:
 * - mask – masks a plain value (e.g. **** + last N chars).
 *
 * Usage: {{ value|mask }} or {{ value|mask(4, '****') }}
 */
class MaskExtension
{
    /**
     * Masks a plain value: replacement (e.g. ****) + last N characters visible.
     *
     * @param mixed       $value       Value to mask (string or cast to string)
     * @param int|null    $visibleLast Number of characters to leave visible at the end (default 4)
     * @param string|null $replacement String to show instead of hidden part (default '****')
     * @return string|null Masked value, or null if input is null
     */
    #[AsTwigFilter('mask', isSafe: ['html'])]
    public function mask(mixed $value, ?int $visibleLast = 4, ?string $replacement = '****'): ?string
    {
        $str = $value === null ? null : (string) $value;
        return MaskUtil::mask($str, $visibleLast, $replacement);
    }
}
