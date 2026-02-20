<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;

/** @internal */
class DummyEncryptorForCommand implements EncryptorInterface
{
    public function encrypt(string $data): string
    {
        return $data;
    }

    public function decrypt(string $data): string
    {
        return $data;
    }
}
