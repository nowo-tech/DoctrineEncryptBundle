<?php

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use Symfony\Component\Filesystem\Filesystem;

class DefuseEncryptor implements EncryptorInterface
{
    private Filesystem $fs;
    private ?string $encryptionKey = null;
    private string $keyFile;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $keyFile)
    {
        $this->keyFile = $keyFile;
        $this->fs = new Filesystem();
    }

    /**
     * The function encrypts a given string using a password-based encryption algorithm.
     *
     * @param string data The "data" parameter is a string that represents the data you want to encrypt.
     *
     * @return string The encrypt function is returning a string.
     */
    public function encrypt(string $data): string
    {
        return \Defuse\Crypto\Crypto::encryptWithPassword($data, $this->getKey());
    }


    /**
     * The function decrypts a given string using a password-based encryption algorithm.
     *
     * @param string data The parameter `` is a string that represents the encrypted data that you
     * want to decrypt.
     *
     * @return string The decrypt function is returning a string.
     */
    public function decrypt(string $data): string
    {
        return \Defuse\Crypto\Crypto::decryptWithPassword($data, $this->getKey());
    }

    /**
     * The function `getKey()` retrieves an encryption key from a file or generates a new one if the file
     * does not exist.
     *
     * @return string the encryption key as a string.
     */
    private function getKey(): string
    {
        if ($this->encryptionKey === null) {
            if ($this->fs->exists($this->keyFile)) {
                $this->encryptionKey = file_get_contents($this->keyFile);
            } else {
                $string = random_bytes(255);
                $this->encryptionKey = bin2hex($string);
                $this->fs->dumpFile($this->keyFile, $this->encryptionKey);
            }
        }

        return $this->encryptionKey;
    }
}
