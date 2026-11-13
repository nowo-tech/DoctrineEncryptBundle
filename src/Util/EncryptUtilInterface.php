<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Util;

use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Exception\ValueNotEncryptedException;

/**
 * Port: encrypt and decrypt single values outside Doctrine (marker `<ENC>` appended to the ciphertext).
 *
 * The default implementation is {@see EncryptUtil} (`EncryptUtil::ENCRYPTION_MARKER` stays on the class); alias this
 * interface to your own service to replace it. Type-hint the interface in application and domain adapters.
 */
interface EncryptUtilInterface
{
    /**
     * Decrypts a value if it has the encryption marker at the end; otherwise returns the original value (lenient).
     *
     * @param string|null $value Value to decrypt (ciphertext + marker, or plain)
     * @param string|null $config Encryptor config name. Null = default config
     */
    public function decrypt(?string $value, ?string $config = null): ?string;

    /**
     * Decrypts a value that must be ciphertext: never passes plaintext through.
     *
     * @param string|null $value Ciphertext + marker
     * @param string|null $config Encryptor config name. Null = default config
     *
     * @throws ValueNotEncryptedException
     * @throws DecryptionFailedException
     */
    public function decryptStrict(?string $value, ?string $config = null): ?string;

    /**
     * Encrypts a value and returns it with the encryption marker appended (null and '' are returned unchanged).
     *
     * @param string|null $value Plain text to encrypt
     * @param string|null $config Encryptor config name. Null = default config
     */
    public function encrypt(?string $value, ?string $config = null): ?string;
}
