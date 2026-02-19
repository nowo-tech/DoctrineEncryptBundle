<?php

namespace Nowo\DoctrineEncryptBundle\Encryptors;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Registry of named encryptors. Used when multiple encryptor configs are defined (e.g. personal_data, financial_data).
 * Arguments are set at compile time by DoctrineEncryptExtension.
 */
#[AsAlias(id: self::class, public: true)]
final class EncryptorRegistry
{
    /** @var array<string, EncryptorInterface> */
    private array $encryptors = [];

    private string $defaultName;

    /**
     * @param array<string, EncryptorInterface> $encryptors map of config alias => encryptor
     * @param string $defaultName config name to use when attribute has no config or uses "default"
     */
    public function __construct(array $encryptors, string $defaultName = 'default')
    {
        $this->encryptors = $encryptors;
        $this->defaultName = $defaultName;
    }

    public function get(string $name): EncryptorInterface
    {
        if (!isset($this->encryptors[$name])) {
            throw new \InvalidArgumentException(sprintf('Unknown encryptor config "%s". Available: %s.', $name, implode(', ', array_keys($this->encryptors))));
        }

        return $this->encryptors[$name];
    }

    public function getDefault(): EncryptorInterface
    {
        return $this->get($this->defaultName);
    }

    public function getDefaultName(): string
    {
        return $this->defaultName;
    }

    public function has(string $name): bool
    {
        return isset($this->encryptors[$name]);
    }

    /** @return array<string> */
    public function getConfigNames(): array
    {
        return array_keys($this->encryptors);
    }
}
