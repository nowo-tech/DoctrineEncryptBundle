<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle;

use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntimeConfigurator;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for Doctrine entity field encryption (Halite, Defuse).
 *
 * Registers the DI extension that loads configuration and encryptor services. With `dbal_types: true` it hands the
 * encryptors to the encrypted DBAL types when the kernel boots and takes them back when it shuts down.
 */
final class NowoDoctrineEncryptBundle extends Bundle
{
    /**
     * Returns the container extension that configures the bundle.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new DoctrineEncryptExtension();
    }

    public function boot(): void
    {
        $this->typeRuntimeConfigurator()?->configure();
    }

    public function shutdown(): void
    {
        $this->typeRuntimeConfigurator()?->clear();
    }

    private function typeRuntimeConfigurator(): ?EncryptedTypeRuntimeConfigurator
    {
        if (!isset($this->container) || !$this->container->has(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE)) {
            return null;
        }

        $configurator = $this->container->get(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE);

        return $configurator instanceof EncryptedTypeRuntimeConfigurator ? $configurator : null;
    }
}
