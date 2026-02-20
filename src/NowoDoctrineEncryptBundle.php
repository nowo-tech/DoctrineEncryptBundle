<?php

namespace Nowo\DoctrineEncryptBundle;

use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for Doctrine entity field encryption (Halite, Defuse).
 *
 * Registers the DI extension that loads configuration and encryptor services.
 */
class NowoDoctrineEncryptBundle extends Bundle
{
    /**
     * Returns the container extension that configures the bundle.
     *
     * @return ExtensionInterface|null
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new DoctrineEncryptExtension();
    }
}
