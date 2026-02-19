<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Util;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Sensitive data encryption and decryption utility.
 *
 * Provides methods for:
 * - Encrypting sensitive data with encryption marker
 * - Decrypting data if encryption marker is present
 *
 * Same config philosophy as DecryptExtension (Twig): optional $config to choose encryptor;
 * omit (null) = default config, or pass a config name (e.g. personal_data, financial_data).
 * Adds '<ENC>' marker to identify encrypted values.
 */
/** Alias for autowiring: type-hint this class to get the util service. */
#[AsAlias(id: self::UTIL_NAME, public: true)]
class EncryptUtil
{
    public const UTIL_NAME = 'nowo_doctrine_encrypt.encrypt_util';

    public const ENCRYPTION_MARKER = '<ENC>';

    public function __construct(
        private readonly EncryptorRegistry $registry
    ) {
    }

    /**
     * Decrypts a value if it has the encryption marker at the end; otherwise returns the original value.
     *
     * @param string|null $value    The value to decrypt
     * @param string|null $config Encryptor config name (e.g. 'personal_data', 'financial_data'). Null = default config
     */
    public function decrypt(?string $value, ?string $config = null): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '' || $value === '0') {
            return '';
        }
        if (!str_ends_with($value, self::ENCRYPTION_MARKER)) {
            return $value;
        }

        $encryptor = $this->getEncryptor($config);

        return $encryptor->decrypt(substr($value, 0, -strlen(self::ENCRYPTION_MARKER)));
    }

    /**
     * Encrypts a value and returns it with the encryption marker appended.
     *
     * Empty strings and '0' are not encrypted and are returned as-is.
     *
     * @param string|null $value    The value to encrypt
     * @param string|null $config Encryptor config name. Null = default config
     */
    public function encrypt(?string $value, ?string $config = null): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value === '' || $value === '0') {
            return $value;
        }

        $encryptor = $this->getEncryptor($config);

        return $encryptor->encrypt($value) . self::ENCRYPTION_MARKER;
    }

    /**
     * Returns the encryptor for the given config name (or the default).
     */
    private function getEncryptor(?string $config): EncryptorInterface
    {
        return $config !== null
            ? $this->registry->get($config)
            : $this->registry->getDefault();
    }
}
