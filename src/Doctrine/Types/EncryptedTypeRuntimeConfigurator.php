<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Psr\Log\LoggerInterface;

/**
 * Hands the container's encryptors to {@see EncryptedTypeRuntime} when the kernel boots and takes them back when it
 * shuts down. Registered only when `nowo_doctrine_encrypt.dbal_types` is enabled.
 */
final readonly class EncryptedTypeRuntimeConfigurator
{
    public function __construct(
        private EncryptorRegistryInterface $registry,
        private string $onDecryptFailure = DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function configure(): void
    {
        EncryptedTypeRuntime::configure($this->registry, $this->onDecryptFailure, $this->logger);
    }

    public function clear(): void
    {
        EncryptedTypeRuntime::clear($this->registry);
    }
}
