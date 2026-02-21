<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DoctrineEncryptSubscriber;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;

class DoctrineEncryptSubscriberDefuseTestCase extends AbstractDoctrineEncryptSubscriberBase
{
    protected function getEncryptor(): EncryptorInterface
    {
        return new DefuseEncryptor(__DIR__ . '/../fixtures/defuse.key');
    }
}
