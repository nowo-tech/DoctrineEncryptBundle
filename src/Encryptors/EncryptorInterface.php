<?php

namespace Nowo\DoctrineEncryptBundle\Encryptors;

/**
 * Contract for encryption and decryption of string data.
 *
 * Implementations (e.g. HaliteEncryptor, DefuseEncryptor) use a secret key from file or environment.
 */
interface EncryptorInterface
{
    /**
     * Encrypts plain text and returns the ciphertext.
     *
     * @param string $data Plain text to encrypt
     * @return string Encrypted (ciphertext) string
     */
    public function encrypt(string $data): string;

    /**
     * Decrypts ciphertext and returns the plain text.
     *
     * @param string $data Encrypted (ciphertext) string
     * @return string Decrypted plain text
     */
    public function decrypt(string $data): string;
}
