<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use Nowo\DoctrineEncryptBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        $config = $this->process([]);

        $this->assertSame('default', $config['default_profile']);
        $this->assertSame(5, $config['batch_size']);
        $this->assertSame([], $config['profiles']);
    }

    public function testBatchSizeDefaultAndCustom(): void
    {
        $config = $this->process([]);
        $this->assertSame(5, $config['batch_size']);

        $config = $this->process(['batch_size' => 10]);
        $this->assertSame(10, $config['batch_size']);
    }

    public function testSingleConfigWithCustomEncryptor(): void
    {
        $config = $this->process([
            'profiles' => [
                'default' => ['encryptor_class' => 'Defuse'],
            ],
        ]);

        $this->assertSame('Defuse', $config['profiles']['default']['encryptor_class']);
        $this->assertSame('%kernel.project_dir%', $config['profiles']['default']['secret_directory_path']);
    }

    public function testSingleConfigWithCustomSecretDirectoryPath(): void
    {
        $config = $this->process([
            'profiles' => [
                'default' => ['secret_directory_path' => '/var/secrets'],
            ],
        ]);

        $this->assertSame('/var/secrets', $config['profiles']['default']['secret_directory_path']);
    }

    public function testProfilesAndDefaultProfile(): void
    {
        $config = $this->process([
            'default_profile' => 'financial_data',
            'profiles'        => [
                'personal_data' => [
                    'encryptor_class'       => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
                'financial_data' => [
                    'encryptor_class'       => 'Defuse',
                    'secret_directory_path' => '/var/secrets',
                ],
            ],
        ]);

        $this->assertSame('financial_data', $config['default_profile']);
        $this->assertArrayHasKey('personal_data', $config['profiles']);
        $this->assertSame('Halite', $config['profiles']['personal_data']['encryptor_class']);
        $this->assertArrayHasKey('financial_data', $config['profiles']);
        $this->assertSame('Defuse', $config['profiles']['financial_data']['encryptor_class']);
    }

    public function testGetConfigTreeBuilderReturnsTreeWithExpectedStructure(): void
    {
        $configuration = new Configuration();
        $treeBuilder   = $configuration->getConfigTreeBuilder();

        // @phpstan-ignore method.alreadyNarrowedType (TreeBuilder return type is known, but testing the API contract)
        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
        // @phpstan-ignore method.alreadyNarrowedType (getRootNode() cannot return null here, but testing robustness)
        $this->assertNotNull($treeBuilder->getRootNode());
        // @phpstan-ignore method.alreadyNarrowedType (NodeDefinition return type is known, but testing the API contract)
        $this->assertInstanceOf(NodeDefinition::class, $treeBuilder->getRootNode());
    }

    public function testSecretKeyFilenameDefaultsToNullWhenNotProvided(): void
    {
        $config = $this->process([
            'profiles' => [
                'default' => ['encryptor_class' => 'Halite'],
            ],
        ]);
        $this->assertArrayHasKey('secret_key_filename', $config['profiles']['default']);
        $this->assertNull($config['profiles']['default']['secret_key_filename']);
    }

    public function testConfigurationAliasConstant(): void
    {
        // @phpstan-ignore method.alreadyNarrowedType (intentional constant regression guard)
        $this->assertSame('nowo_doctrine_encrypt', Configuration::ALIAS);
    }

    public function testProcessedConfigHasDefaultEncryptorClassAndSecretPath(): void
    {
        $config = $this->process([
            'profiles' => [
                'default' => [],
            ],
        ]);

        $this->assertSame('Halite', $config['profiles']['default']['encryptor_class']);
        $this->assertSame('%kernel.project_dir%', $config['profiles']['default']['secret_directory_path']);
    }

    public function testSecretKeyFilenameOptional(): void
    {
        $config = $this->process([
            'profiles' => [
                'default' => [
                    'encryptor_class'       => 'Halite',
                    'secret_directory_path' => '/var/keys',
                    'secret_key_filename'   => '.my_app.key',
                ],
            ],
        ]);

        $this->assertSame('.my_app.key', $config['profiles']['default']['secret_key_filename']);
    }

    public function testSecretKeyEnvVar(): void
    {
        $config = $this->process([
            'profiles' => [
                'default' => [
                    'encryptor_class'    => 'Halite',
                    'secret_key_env_var' => 'APP_ENCRYPT_KEY',
                ],
            ],
        ]);

        $this->assertSame('APP_ENCRYPT_KEY', $config['profiles']['default']['secret_key_env_var']);
        $this->assertNull($config['profiles']['default']['secret_directory_path']);
    }

    public function testCannotSetBothSecretKeyEnvVarAndSecretDirectoryPath(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot set both secret_key_env_var and secret_directory_path');

        $this->process([
            'profiles' => [
                'default' => [
                    'encryptor_class'       => 'Halite',
                    'secret_directory_path' => '/var/keys',
                    'secret_key_env_var'    => 'APP_ENCRYPT_KEY',
                ],
            ],
        ]);
    }

    public function testAcceptsLegacyDefaultConfigAndConfigsKeys(): void
    {
        $config = $this->process([
            'default_config' => 'financial_data',
            'configs'        => [
                'personal_data' => [
                    'encryptor_class'       => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
                'financial_data' => [
                    'encryptor_class'       => 'Defuse',
                    'secret_directory_path' => '/var/secrets',
                ],
            ],
        ]);

        $this->assertSame('financial_data', $config['default_profile']);
        $this->assertArrayHasKey('personal_data', $config['profiles']);
        $this->assertArrayHasKey('financial_data', $config['profiles']);
        $this->assertArrayNotHasKey('default_config', $config);
        $this->assertArrayNotHasKey('configs', $config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        $configuration = new Configuration();
        $processor     = new Processor();

        return $processor->processConfiguration($configuration, [$config]);
    }
}
