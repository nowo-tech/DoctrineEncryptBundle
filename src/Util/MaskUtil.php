<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Util;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function array_slice;
use function count;
use function strlen;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Masks sensitive values for display: shows a replacement string (e.g. ****) plus the last N characters.
 *
 * Example: MaskUtil::mask('12345678', 4) => '****5678'
 * Saved-secret hint: MaskUtil::secretHint('re_live_abcd1234') => '•••• 1234'
 */
#[AsAlias(id: self::UTIL_NAME, public: true)]
final class MaskUtil
{
    public const UTIL_NAME = 'nowo_doctrine_encrypt.mask_util';

    /**
     * Masks a value by replacing all but the last N characters with a replacement string.
     *
     * @param string|null $value The value to mask (e.g. decrypted sensitive data)
     * @param int $visibleLast Number of characters to leave visible at the end (default 4)
     * @param string $replacement String to show instead of hidden part (default '****')
     *
     * @return string|null The masked value, or null if $value is null
     */
    public static function mask(?string $value, ?int $visibleLast = 4, ?string $replacement = '****'): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '') {
            return '';
        }
        $len = strlen($value);
        if ($visibleLast <= 0 || $len <= $visibleLast) {
            return $replacement;
        }

        return $replacement . substr($value, -$visibleLast);
    }

    /**
     * Hint for a stored secret (API key, token, password) that proves one is saved without revealing it.
     *
     * Example: MaskUtil::secretHint('re_live_abcd1234') => '•••• 1234'
     *
     * - Returns null for null or '' (nothing saved), so templates can show "not set".
     * - Multibyte-safe (counts characters, not bytes).
     * - Secrets not longer than 2 × $visibleLast characters show only the mask, so short
     *   secrets are never (almost) fully disclosed.
     *
     * @param string|null $secret The stored secret (plain value, never send it to the browser)
     * @param int $visibleLast Number of trailing characters to reveal (default 4; <= 0 reveals nothing)
     * @param string $mask Mask prefix (default four bullets)
     */
    public static function secretHint(?string $secret, int $visibleLast = 4, string $mask = '••••'): ?string
    {
        if ($secret === null || $secret === '') {
            return null;
        }

        $chars = preg_split('//u', $secret, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            // Not valid UTF-8: fall back to bytes.
            $chars = str_split($secret);
        }

        if ($visibleLast <= 0 || count($chars) <= 2 * $visibleLast) {
            return $mask;
        }

        return $mask . ' ' . implode('', array_slice($chars, -$visibleLast));
    }
}
