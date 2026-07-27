<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

use function sprintf;

/**
 * Registry of named encryptors. Used when multiple encryptor configs are defined (e.g. personal_data, financial_data).
 * Arguments are set at compile time by DoctrineEncryptExtension.
 */
#[AsAlias(id: self::class, public: true)]
final class EncryptorRegistry
{
    /**
     * @param array<string, EncryptorInterface> $encryptors map of config alias => encryptor
     * @param string $defaultName config name to use when attribute has no config or uses "default"
     */
    public function __construct(private array $encryptors, private readonly string $defaultName = 'default')
    {
    }

    /**
     * Returns the encryptor for the given config name.
     *
     * @param string $name Config alias (e.g. personal_data, financial_data)
     *
     * @throws InvalidArgumentException When the config name is not registered
     */
    public function get(string $name): EncryptorInterface
    {
        if (!isset($this->encryptors[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown encryptor config "%s". Available: %s.', $name, implode(', ', array_keys($this->encryptors))));
        }

        return $this->encryptors[$name];
    }

    /**
     * Returns the default encryptor (used when no config is specified).
     */
    public function getDefault(): EncryptorInterface
    {
        return $this->get($this->defaultName);
    }

    /**
     * Returns the name of the default config.
     */
    public function getDefaultName(): string
    {
        return $this->defaultName;
    }

    /**
     * Checks whether an encryptor is registered for the given config name.
     *
     * @param string $name Config alias
     */
    public function has(string $name): bool
    {
        return isset($this->encryptors[$name]);
    }

    /**
     * Returns all registered config names.
     *
     * @return array<string>
     */
    public function getConfigNames(): array
    {
        return array_keys($this->encryptors);
    }
}
