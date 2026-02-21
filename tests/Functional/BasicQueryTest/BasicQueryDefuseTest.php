<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\BasicQueryTest;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;

class BasicQueryDefuseTest extends AbstractBasicQueryTestCase
{
    protected function getEncryptor(): EncryptorInterface
    {
        return new DefuseEncryptor(__DIR__ . '/../fixtures/defuse.key');
    }
}
