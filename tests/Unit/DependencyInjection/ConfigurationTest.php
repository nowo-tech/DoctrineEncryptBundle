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

        $this->assertSame('default', $config['default_config']);
        $this->assertSame([], $config['configs']);
    }

    public function testSingleConfigWithCustomEncryptor(): void
    {
        $config = $this->process([
            'configs' => [
                'default' => ['encryptor_class' => 'Defuse'],
            ],
        ]);

        $this->assertSame('Defuse', $config['configs']['default']['encryptor_class']);
        $this->assertSame('%kernel.project_dir%', $config['configs']['default']['secret_directory_path']);
    }

    public function testSingleConfigWithCustomSecretDirectoryPath(): void
    {
        $config = $this->process([
            'configs' => [
                'default' => ['secret_directory_path' => '/var/secrets'],
            ],
        ]);

        $this->assertSame('/var/secrets', $config['configs']['default']['secret_directory_path']);
    }

    public function testConfigsAndDefaultConfig(): void
    {
        $config = $this->process([
            'default_config' => 'financial_data',
            'configs' => [
                'personal_data' => [
                    'encryptor_class' => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
                'financial_data' => [
                    'encryptor_class' => 'Defuse',
                    'secret_directory_path' => '/var/secrets',
                ],
            ],
        ]);

        $this->assertSame('financial_data', $config['default_config']);
        $this->assertArrayHasKey('personal_data', $config['configs']);
        $this->assertSame('Halite', $config['configs']['personal_data']['encryptor_class']);
        $this->assertArrayHasKey('financial_data', $config['configs']);
        $this->assertSame('Defuse', $config['configs']['financial_data']['encryptor_class']);
    }

    public function testGetConfigTreeBuilderReturnsTreeWithExpectedStructure(): void
    {
        $configuration = new Configuration();
        $treeBuilder = $configuration->getConfigTreeBuilder();

        $this->assertInstanceOf(\Symfony\Component\Config\Definition\Builder\TreeBuilder::class, $treeBuilder);
        $this->assertNotNull($treeBuilder->getRootNode());
    }

    public function testConfigurationAliasConstant(): void
    {
        $this->assertSame('nowo_doctrine_encrypt', Configuration::ALIAS);
    }

    public function testProcessedConfigHasDefaultEncryptorClassAndSecretPath(): void
    {
        $config = $this->process([
            'configs' => [
                'default' => [],
            ],
        ]);

        $this->assertSame('Halite', $config['configs']['default']['encryptor_class']);
        $this->assertSame('%kernel.project_dir%', $config['configs']['default']['secret_directory_path']);
    }

    private function process(array $config): array
    {
        $configuration = new Configuration();
        $processor = new Processor();

        return $processor->processConfiguration($configuration, [$config]);
    }
}
