<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use Defuse\Crypto\Crypto;
use Nowo\DoctrineEncryptBundle\Exception\MissingEncryptionKeyException;
use Nowo\DoctrineEncryptBundle\Security\SecretKeyPermissions;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

use function dirname;

/**
 * Encryptor implementation using defuse/php-encryption (password-based encryption).
 *
 * The secret key can be read from a file or from string content (e.g. env var).
 * If the key file does not exist, a new key is generated and saved only when $generateMissingKey is true
 * (bundle default: only in debug); otherwise {@see MissingEncryptionKeyException} is thrown.
 */
final class DefuseEncryptor implements EncryptorInterface
{
    private readonly Filesystem $fs;
    private ?string $encryptionKey = null;
    private readonly ?string $keyContent;

    /**
     * @param string $keyFile path to the key file (ignored when $keyContent is set)
     * @param string|null $keyContent Optional key value (e.g. from env). When set, $keyFile is not read.
     * @param bool $generateMissingKey Whether a missing key file may be created on first use. The bundle passes
     *                                 `generate_missing_key` (default: kernel.debug); when false a missing key file throws
     *                                 {@see MissingEncryptionKeyException}. An existing key file is never replaced.
     */
    public function __construct(private readonly string $keyFile, ?string $keyContent = null, private readonly bool $generateMissingKey = true)
    {
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
        return Crypto::encryptWithPassword($data, $this->getKey());
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
        return Crypto::decryptWithPassword($data, $this->getKey());
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
                // @igor-ignore - Stateless crypto helper; mutation is local algorithm state
                $this->encryptionKey = trim($this->keyContent);

                return $this->encryptionKey;
            }
            if ($this->keyFile === '') {
                throw new RuntimeException('The encryption key environment variable is not set. Run "php bin/console doctrine:encrypt:generate-secret-key" to get the key value, then set it in your .env or environment.');
            }
            // @igor-ignore - Stateless crypto helper; mutation is local algorithm state
            if ($this->fs->exists($this->keyFile)) {
                $content = is_readable($this->keyFile) ? file_get_contents($this->keyFile) : false;
                if ($content === false) {
                    throw MissingEncryptionKeyException::unreadable($this->keyFile);
                }
                // @igor-ignore - Stateless crypto helper; mutation is local algorithm state
                $this->encryptionKey = trim($content);
            // @igor-ignore - Stateless crypto helper; mutation is local algorithm state
            } elseif (!$this->generateMissingKey) {
                throw MissingEncryptionKeyException::forFile($this->keyFile);
            } else {
                // @igor-ignore - Stateless crypto helper; mutation is local algorithm state
                $string = random_bytes(255);
                // @igor-ignore - Stateless crypto helper; mutation is local algorithm state
                $this->encryptionKey = bin2hex($string);
                // Same hardening as Halite: no world-accessible directory, key file owner-only (0600).
                SecretKeyPermissions::ensureDirectory(dirname($this->keyFile));
                $this->fs->dumpFile($this->keyFile, $this->encryptionKey);
                SecretKeyPermissions::hardenFile($this->keyFile);
            }
        }

        return $this->encryptionKey;
    }
}
