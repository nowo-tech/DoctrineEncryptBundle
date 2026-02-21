<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Util;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function strlen;

/**
 * Masks sensitive values for display: shows a replacement string (e.g. ****) plus the last N characters.
 *
 * Example: MaskUtil::mask('12345678', 4) => '****5678'
 */
#[AsAlias(id: self::UTIL_NAME, public: true)]
class MaskUtil
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
}
