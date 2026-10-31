<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Util;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Throwable;

use function strlen;
use function substr;

/**
 * Helpers for stored values in the "ciphertext + {@see EncryptUtil::ENCRYPTION_MARKER}" format.
 *
 * The marker alone does not prove that a value is ciphertext: a plaintext may end with "<ENC>" too.
 * {@see self::isDecryptable()} also checks that the payload actually decrypts with the encryptor
 * (Halite and Defuse are authenticated, so a plaintext never passes that check).
 */
final class EncryptedValue
{
    public const MARKER = EncryptUtil::ENCRYPTION_MARKER;

    /**
     * Whether the value ends with the marker and has a non-empty payload before it (format only, no decryption).
     */
    public static function hasMarker(string $value): bool
    {
        return strlen($value) > strlen(self::MARKER) && str_ends_with($value, self::MARKER);
    }

    /**
     * The ciphertext before the marker (the value is expected to pass {@see self::hasMarker()}).
     */
    public static function payload(string $value): string
    {
        return substr($value, 0, -strlen(self::MARKER));
    }

    /**
     * Whether the value is marked ciphertext that the encryptor can decrypt (wrong key, corrupted data and
     * plaintext ending with the marker all return false).
     */
    public static function isDecryptable(string $value, EncryptorInterface $encryptor): bool
    {
        if (!self::hasMarker($value)) {
            return false;
        }

        try {
            $encryptor->decrypt(self::payload($value));
        } catch (Throwable) {
            return false;
        }

        return true;
    }
}
