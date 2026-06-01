<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use RuntimeException;

use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_readable;
use function openssl_decrypt;
use function openssl_encrypt;
use function str_pad;
use function strlen;
use function substr;
use function trim;

/**
 * Encryptor compatible with MySQL {@see https://dev.mysql.com/doc/refman/8.0/en/encryption-functions.html AES_ENCRYPT()} / AES_DECRYPT()}.
 *
 * Uses AES-128-ECB with the same key padding MySQL applies when {@code block_encryption_mode} is {@code aes-128-ecb}
 * (default). Values encrypted in PHP can be decrypted with {@code AES_DECRYPT(column, :key)} and vice versa when the
 * column stores the raw binary ciphertext (BLOB / VARBINARY recommended).
 */
class MysqlAesEncryptor implements EncryptorInterface
{
    private const CIPHER = 'AES-128-ECB';

    private const KEY_LENGTH = 16;

    private string $keyFile;

    private ?string $keyContent;

    private ?string $derivedKey = null;

    /**
     * @param string $keyFile Path to a text file containing the passphrase (ignored when $keyContent is set)
     * @param string|null $keyContent Passphrase from env or inline config
     */
    public function __construct(string $keyFile, ?string $keyContent = null)
    {
        $this->keyFile    = $keyFile;
        $this->keyContent = $keyContent !== null && $keyContent !== '' ? $keyContent : null;
    }

    public function encrypt(string $data): string
    {
        $encrypted = openssl_encrypt($data, self::CIPHER, $this->getDerivedKey(), OPENSSL_RAW_DATA);
        if ($encrypted === false) {
            throw new RuntimeException('MysqlAesEncryptor: openssl_encrypt failed.');
        }

        return $encrypted;
    }

    public function decrypt(string $data): string
    {
        $decrypted = openssl_decrypt($data, self::CIPHER, $this->getDerivedKey(), OPENSSL_RAW_DATA);
        if ($decrypted === false) {
            throw new RuntimeException('MysqlAesEncryptor: openssl_decrypt failed.');
        }

        return $decrypted;
    }

    /**
     * Returns the passphrase used for SQL bindings (AES_ENCRYPT / AES_DECRYPT second argument).
     */
    public function getPassphrase(): string
    {
        return $this->resolvePassphrase();
    }

    private function getDerivedKey(): string
    {
        if ($this->derivedKey === null) {
            $this->derivedKey = self::deriveKey($this->resolvePassphrase());
        }

        return $this->derivedKey;
    }

    private function resolvePassphrase(): string
    {
        if ($this->keyContent !== null) {
            return trim($this->keyContent);
        }

        if ($this->keyFile === '' || !file_exists($this->keyFile)) {
            throw new RuntimeException(sprintf('MysqlAesEncryptor: key file not found at "%s".', $this->keyFile));
        }

        if (is_dir($this->keyFile)) {
            throw new RuntimeException(sprintf('MysqlAesEncryptor: key path "%s" is a directory.', $this->keyFile));
        }

        $content = file_get_contents($this->keyFile);
        if ($content === false || !is_readable($this->keyFile)) {
            throw new RuntimeException(sprintf('MysqlAesEncryptor: cannot read key file "%s".', $this->keyFile));
        }

        return trim($content);
    }

    /**
     * MySQL pads the key string to the cipher key length with zero bytes (AES-128 → 16 bytes).
     */
    public static function deriveKey(string $passphrase): string
    {
        return str_pad(substr($passphrase, 0, self::KEY_LENGTH), self::KEY_LENGTH, "\0");
    }
}
