<?php

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use ParagonIE\Halite\Alerts\CannotPerformOperation;
use ParagonIE\Halite\KeyFactory;
use ParagonIE\Halite\Symmetric\Crypto;
use ParagonIE\Halite\Symmetric\EncryptionKey;
use ParagonIE\HiddenString\HiddenString;

/**
 * Class for encrypting and decrypting with the halite library
 *
 * @author Michael de Groot <specamps@gmail.com>
 */

class HaliteEncryptor implements EncryptorInterface
{
    private ?EncryptionKey $encryptionKey = null;
    private string $keyFile;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $keyFile)
    {
        $this->keyFile = $keyFile;
    }

    /**
     * The function encrypts a given string using a specified key.
     *
     * @param string $data The parameter is a string that represents the data that you want to
     * encrypt.
     *
     * @return string The encrypt function is returning a string.
     */
    public function encrypt(string $data): string
    {
        return Crypto::encrypt(new HiddenString($data), $this->getKey());
    }

    /**
     * The function decrypts a given string using a specified key and returns the decrypted data.
     *
     * @param string $data The parameter is a string that represents the encrypted data that needs
     * to be decrypted.
     *
     * @return string The decrypted data is being returned as a string.
     */
    public function decrypt(string $data): string
    {
        $data = Crypto::decrypt($data, $this->getKey());

        if ($data instanceof HiddenString) {
            $data = $data->getString();
        }

        return $data;
    }

    /**
     * The function `getKey()` returns an encryption key, generating a new one if it doesn't exist or
     * loading it from a file if it does.
     *
     * @return EncryptionKey an instance of the EncryptionKey class.
     */
    private function getKey(): EncryptionKey
    {
        if ($this->encryptionKey === null) {
            try {
                $this->encryptionKey = KeyFactory::loadEncryptionKey($this->keyFile);
            } catch (CannotPerformOperation $e) {
                $this->encryptionKey = KeyFactory::generateEncryptionKey();
                KeyFactory::save($this->encryptionKey, $this->keyFile);
            }
        }

        return $this->encryptionKey;
    }
}
