<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Encryptor implementation using defuse/php-encryption (password-based encryption).
 *
 * The secret key can be read from a file or from string content (e.g. env var).
 * If the key file does not exist, a new key is generated and saved.
 */
class DefuseEncryptor implements EncryptorInterface
{
    private Filesystem $fs;
    private ?string $encryptionKey = null;
    private string $keyFile;
    private ?string $keyContent;

    /**
     * @param string $keyFile path to the key file (ignored when $keyContent is set)
     * @param string|null $keyContent Optional key value (e.g. from env). When set, $keyFile is not read.
     */
    public function __construct(string $keyFile, ?string $keyContent = null)
    {
        $this->keyFile    = $keyFile;
        $this->keyContent = $keyContent !== null && $keyContent !== '' ? $keyContent : null;
        $this->fs         = new Filesystem();
    }

    /**
     * Encrypts plain text using the configured key.
     *
     * @param string $data Plain text to encrypt
     *
     * @return string Ciphertext
     */
    public function encrypt(string $data): string
    {
        return \Defuse\Crypto\Crypto::encryptWithPassword($data, $this->getKey());
    }

    /**
     * Decrypts ciphertext using the configured key.
     *
     * @param string $data Ciphertext to decrypt
     *
     * @return string Plain text
     */
    public function decrypt(string $data): string
    {
        return \Defuse\Crypto\Crypto::decryptWithPassword($data, $this->getKey());
    }

    /**
     * Returns the encryption key, loading from file or env content; creates the key file if missing.
     *
     * @return string The encryption key (password string for Defuse)
     */
    private function getKey(): string
    {
        if ($this->encryptionKey === null) {
            if ($this->keyContent !== null) {
                $this->encryptionKey = trim($this->keyContent);

                return $this->encryptionKey;
            }
            if ($this->keyFile === '') {
                throw new RuntimeException('The encryption key environment variable is not set. Run "php bin/console doctrine:encrypt:generate-secret-key" to get the key value, then set it in your .env or environment.');
            }
            if ($this->fs->exists($this->keyFile)) {
                $this->encryptionKey = trim(file_get_contents($this->keyFile));
            } else {
                $string              = random_bytes(255);
                $this->encryptionKey = bin2hex($string);
                $this->fs->dumpFile($this->keyFile, $this->encryptionKey);
            }
        }

        return $this->encryptionKey;
    }
}
