<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use InvalidArgumentException;

/**
 * Port: registry of named encryptors (one per `encryptors` config alias, e.g. personal_data, financial_data).
 *
 * The default implementation is {@see EncryptorRegistry}; alias this interface to your own service to resolve
 * encryptors from another source. Every bundle consumer (listener, Doctrine types, commands, Twig, EncryptUtil)
 * depends on the interface. Keep an implementation stateless between calls (worker mode, kernel reset false).
 */
interface EncryptorRegistryInterface
{
    /**
     * Returns the encryptor for the given config name.
     *
     * @param string $name Config alias (e.g. personal_data, financial_data)
     *
     * @throws InvalidArgumentException When the config name is not registered
     */
    public function get(string $name): EncryptorInterface;

    /**
     * Returns the default encryptor (used when no config is specified).
     */
    public function getDefault(): EncryptorInterface;

    /**
     * Returns the name of the default config.
     */
    public function getDefaultName(): string;

    /**
     * Checks whether an encryptor is registered for the given config name.
     *
     * @param string $name Config alias
     */
    public function has(string $name): bool;

    /**
     * Returns all registered config names.
     *
     * @return array<string>
     */
    public function getConfigNames(): array;
}
