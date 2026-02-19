<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class DoctrineEncryptExtensionTest extends TestCase
{
    /**
     * @var DoctrineEncryptExtension
     */
    private $extension;

    protected function setUp(): void
    {
        $this->extension = new DoctrineEncryptExtension();
    }

    public function testConfigLoadHalite(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $this->assertSame(HaliteEncryptor::class, $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
    }

    public function testConfigLoadDefuse(): void
    {
        $container = $this->createContainer();

        $config = [
            'configs' => [
                'default' => ['encryptor_class' => 'Defuse'],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame(DefuseEncryptor::class, $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
    }

    public function testConfigLoadSecretDirectoryPath(): void
    {
        $container = $this->createContainer();

        $this->extension->load([[]], $container);

        $path = $container->getParameter('nowo_doctrine_encrypt.secret_key_path');
        $this->assertStringEndsWith('.Halite.default.key', $path);
    }

    public function testConfigLoadCustomSecretDirectoryPath(): void
    {
        $container = $this->createContainer();

        $config = [
            'configs' => [
                'default' => [
                    'secret_directory_path' => '/var/keys',
                    'encryptor_class' => 'Defuse',
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame('/var/keys/.Defuse.default.key', $container->getParameter('nowo_doctrine_encrypt.secret_key_path'));
    }

    public function testGetAlias(): void
    {
        $this->assertSame('nowo_doctrine_encrypt', $this->extension->getAlias());
    }

    public function testConfigLoadCustom(): void
    {
        $container = $this->createContainer();
        $config = [
            'configs' => [
                'default' => ['encryptor_class' => self::class],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->markTestSkipped();

        $this->assertSame(self::class, $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
    }

    public function testLoadWithConfigsRegistersMultipleEncryptors(): void
    {
        $container = $this->createContainer();
        $config = [
            'default_config' => 'personal_data',
            'configs' => [
                'personal_data' => [
                    'encryptor_class' => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
                'financial_data' => [
                    'encryptor_class' => 'Defuse',
                    'secret_directory_path' => '/var/keys',
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame(HaliteEncryptor::class, $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
        $this->assertStringEndsWith('.Halite.personal_data.key', $container->getParameter('nowo_doctrine_encrypt.secret_key_path'));
        $this->assertTrue($container->hasDefinition('nowo_doctrine_encrypt.encryptor.personal_data'));
        $this->assertTrue($container->hasDefinition('nowo_doctrine_encrypt.encryptor.financial_data'));
        $registryDef = $container->getDefinition('nowo_doctrine_encrypt.encryptor_registry');
        $args = $registryDef->getArguments();
        $this->assertSame('personal_data', $args[1]);
    }

    public function testLoadWithConfigsInvalidDefaultConfigFallsBackToFirst(): void
    {
        $container = $this->createContainer();
        $config = [
            'default_config' => 'nonexistent',
            'configs' => [
                'personal_data' => [
                    'encryptor_class' => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $registryDef = $container->getDefinition('nowo_doctrine_encrypt.encryptor_registry');
        $args = $registryDef->getArguments();
        $this->assertSame('personal_data', $args[1]);
    }

    public function testResolveEncryptorClassReturnsCustomClassWhenNotSupported(): void
    {
        $container = $this->createContainer();
        $config = [
            'configs' => [
                'default' => ['encryptor_class' => 'Custom\\MyEncryptor'],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame('Custom\\MyEncryptor', $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder(
            new ParameterBag(['kernel.debug' => false])
        );

        return $container;
    }
}
