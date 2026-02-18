<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use Nowo\DoctrineEncryptBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = $this->process([]);

        $this->assertSame('Halite', $config['encryptor_class']);
        $this->assertSame('%kernel.project_dir%', $config['secret_directory_path']);
    }

    public function testCustomEncryptorClass(): void
    {
        $config = $this->process(['encryptor_class' => 'Defuse']);

        $this->assertSame('Defuse', $config['encryptor_class']);
    }

    public function testCustomSecretDirectoryPath(): void
    {
        $config = $this->process(['secret_directory_path' => '/var/secrets']);

        $this->assertSame('/var/secrets', $config['secret_directory_path']);
    }

    private function process(array $config): array
    {
        $configuration = new Configuration();
        $processor = new Processor();

        return $processor->processConfiguration($configuration, [$config]);
    }
}
