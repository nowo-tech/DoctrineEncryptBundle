<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DoctrineEncryptSubscriber;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;

use function extension_loaded;

class DoctrineEncryptSubscriberHaliteTestCase extends AbstractDoctrineEncryptSubscriberBase
{
    protected function getEncryptor(): EncryptorInterface
    {
        return new HaliteEncryptor(__DIR__ . '/../fixtures/halite.key');
    }

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }

        parent::setUp();
    }
}
