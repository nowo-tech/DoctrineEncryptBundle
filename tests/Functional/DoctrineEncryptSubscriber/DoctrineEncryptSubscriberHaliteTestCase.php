<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DoctrineEncryptSubscriber;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;

class DoctrineEncryptSubscriberHaliteTestCase extends AbstractDoctrineEncryptSubscriberBase
{
    protected function getEncryptor(): EncryptorInterface
    {
        return new HaliteEncryptor(__DIR__ . '/../fixtures/halite.key');
    }

    public function setUp(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');

            return;
        }

        parent::setUp();
    }
}
