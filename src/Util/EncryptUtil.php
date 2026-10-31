<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Util;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Exception\ValueNotEncryptedException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Throwable;

use function strlen;

/**
 * Sensitive data encryption and decryption utility.
 *
 * Provides methods for:
 * - Encrypting sensitive data with encryption marker
 * - Decrypting data if encryption marker is present ({@see self::decrypt()}: unmarked values pass through as plaintext)
 * - Decrypting data that must be ciphertext ({@see self::decryptStrict()}: unmarked values are refused)
 *
 * Same config philosophy as DecryptExtension (Twig): optional $config to choose encryptor;
 * omit (null) = default config, or pass a config name (e.g. personal_data, financial_data).
 * Adds '<ENC>' marker to identify encrypted values.
 *
 * Type-hint this class to get the util service (alias: nowo_doctrine_encrypt.encrypt_util).
 */
#[AsAlias(id: self::UTIL_NAME, public: true)]
final readonly class EncryptUtil
{
    public const UTIL_NAME = 'nowo_doctrine_encrypt.encrypt_util';

    public const ENCRYPTION_MARKER = '<ENC>';

    /**
     * @param EncryptorRegistry $registry Encryptors per profile alias
     */
    public function __construct(
        private EncryptorRegistry $registry
    ) {
    }

    /**
     * Decrypts a value if it has the encryption marker at the end; otherwise returns the original value.
     *
     * Lenient: a value without the marker is returned unchanged, as if it were plaintext. When the input must be
     * ciphertext (sealed values, imports, values from an untrusted store) use {@see self::decryptStrict()}.
     *
     * @param string|null $value Value to decrypt (ciphertext + marker, or plain)
     * @param string|null $config Encryptor config name (e.g. 'personal_data'). Null = default config
     *
     * @return string|null Decrypted value or original if not encrypted
     */
    public function decrypt(?string $value, ?string $config = null): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '') {
            return '';
        }
        if (!str_ends_with($value, self::ENCRYPTION_MARKER)) {
            return $value;
        }

        $encryptor = $this->getEncryptor($config);

        return $encryptor->decrypt(substr($value, 0, -strlen(self::ENCRYPTION_MARKER)));
    }

    /**
     * Decrypts a value that must be ciphertext: never passes plaintext through.
     *
     * - null → null and '' → '' (nothing stored)
     * - a value without the marker, or with an empty ciphertext → {@see ValueNotEncryptedException}
     * - a value that does not decrypt (wrong key, tampered or foreign ciphertext) → {@see DecryptionFailedException}
     *
     * @param string|null $value Ciphertext + marker
     * @param string|null $config Encryptor config name. Null = default config
     *
     * @throws ValueNotEncryptedException
     * @throws DecryptionFailedException
     */
    public function decryptStrict(?string $value, ?string $config = null): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (!EncryptedValue::hasMarker($value)) {
            throw ValueNotEncryptedException::create();
        }

        $encryptor = $this->getEncryptor($config);

        try {
            return $encryptor->decrypt(EncryptedValue::payload($value));
        } catch (Throwable $e) {
            throw DecryptionFailedException::forValue($config, $e);
        }
    }

    /**
     * Encrypts a value and returns it with the encryption marker appended.
     *
     * Empty strings are not encrypted and are returned as-is ('0' is encrypted like any other value since 2.10).
     *
     * @param string|null $value Plain text to encrypt
     * @param string|null $config Encryptor config name. Null = default config
     *
     * @return string|null Ciphertext + marker, or original value if null or empty
     */
    public function encrypt(?string $value, ?string $config = null): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '') {
            return $value;
        }

        $encryptor = $this->getEncryptor($config);

        return $encryptor->encrypt($value) . self::ENCRYPTION_MARKER;
    }

    /**
     * Returns the encryptor for the given config name (or the default).
     *
     * @param string|null $config Config alias, or null for default
     */
    private function getEncryptor(?string $config): EncryptorInterface
    {
        return $config !== null
            ? $this->registry->get($config)
            : $this->registry->getDefault();
    }
}
