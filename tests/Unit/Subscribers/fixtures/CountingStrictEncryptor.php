<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use RuntimeException;

/**
 * Authenticated-like fake: only "C(<base64>)" decrypts; anything else (plaintext, wrong key) throws.
 */
final class CountingStrictEncryptor implements EncryptorInterface
{
    public int $encryptCalls = 0;

    public int $decryptCalls = 0;

    public function encrypt(string $data): string
    {
        ++$this->encryptCalls;

        return 'C(' . base64_encode($data) . ')';
    }

    public function decrypt(string $data): string
    {
        ++$this->decryptCalls;
        if (preg_match('/^C\(([A-Za-z0-9+\/=]*)\)$/', $data, $m) !== 1) {
            throw new RuntimeException('Invalid message');
        }

        return (string) base64_decode($m[1], true);
    }
}
