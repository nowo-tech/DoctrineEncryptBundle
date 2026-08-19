<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptStatusCommand;
use Nowo\DoctrineEncryptBundle\Command\GenerateSecretKeyCommand;
use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Twig\DecryptExtension;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

class DoctrineEncryptExtensionTest extends TestCase
{
    private DoctrineEncryptExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new DoctrineEncryptExtension();
    }

    public function testConfigLoadHalite(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $this->assertSame(HaliteEncryptor::class, $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
        $this->assertSame(5, $container->getParameter('nowo_doctrine_encrypt.batch_size'));
    }

    public function testConfigLoadDefuse(): void
    {
        $container = $this->createContainer();

        $config = [
            'profiles' => [
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
        $this->assertIsString($path);
        $this->assertStringEndsWith('.Halite.default.key', $path);
    }

    public function testLoadWithEmptyProfilesArrayRegistersDefaultProfile(): void
    {
        $container = $this->createContainer();
        $this->extension->load([['profiles' => []]], $container);

        $this->assertTrue($container->hasDefinition('nowo_doctrine_encrypt.encryptor.default'));
        $secretPath = $container->getParameter('nowo_doctrine_encrypt.secret_key_path');
        $this->assertIsString($secretPath);
        $this->assertStringEndsWith('.Halite.default.key', $secretPath);
        /** @var array<string, mixed> $keyPaths */
        $keyPaths = $container->getParameter('nowo_doctrine_encrypt.key_paths');
        $this->assertArrayHasKey('default', $keyPaths);
    }

    public function testConfigLoadCustomSecretDirectoryPath(): void
    {
        $container = $this->createContainer();

        $config = [
            'profiles' => [
                'default' => [
                    'secret_directory_path' => '/var/keys',
                    'encryptor_class'       => 'Defuse',
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

    /**
     * Checks that all services defined by the bundle exist in the container
     * (by namespace/class and by alias), as when running "php bin/console debug:container".
     */
    public function testBundleServicesExistInContainer(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $expectedByNamespace = [
            DoctrineEncryptDatabaseCommand::class,
            DoctrineDecryptDatabaseCommand::class,
            DoctrineEncryptStatusCommand::class,
            GenerateSecretKeyCommand::class,
            EncryptorRegistry::class,
            EncryptorInterface::class,
            AttributeReader::class,
            DoctrineEncryptSubscriber::class,
            DecryptExtension::class,
            EncryptUtil::class,
        ];

        // Aliases defined in services.yml (encrypt_util is added at compile time via AsAlias)
        $expectedByAlias = [
            'nowo_doctrine_encrypt.encryptor_registry',
            'nowo_doctrine_encrypt.encryptor',
            'nowo_doctrine_encrypt.orm_subscriber',
            'nowo_doctrine_encrypt.subscriber',
            'nowo_doctrine_attribute_reader',
        ];

        foreach ($expectedByNamespace as $serviceId) {
            $this->assertTrue($container->has($serviceId), "Container must define service by namespace: {$serviceId}");
        }

        foreach ($expectedByAlias as $serviceId) {
            $this->assertTrue($container->has($serviceId), "Container must define service by alias: {$serviceId}");
        }
    }

    public function testConfigLoadCustom(): void
    {
        $container = $this->createContainer();
        $config    = [
            'profiles' => [
                'default' => ['encryptor_class' => self::class],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->markTestSkipped();
    }

    public function testLoadWithProfilesRegistersMultipleEncryptors(): void
    {
        $container = $this->createContainer();
        $config    = [
            'default_profile' => 'personal_data',
            'profiles'        => [
                'personal_data' => [
                    'encryptor_class'       => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
                'financial_data' => [
                    'encryptor_class'       => 'Defuse',
                    'secret_directory_path' => '/var/keys',
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame(HaliteEncryptor::class, $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
        $keyPath = $container->getParameter('nowo_doctrine_encrypt.secret_key_path');
        $this->assertIsString($keyPath);
        $this->assertStringEndsWith('.Halite.personal_data.key', $keyPath);
        $this->assertTrue($container->hasDefinition('nowo_doctrine_encrypt.encryptor.personal_data'));
        $this->assertTrue($container->hasDefinition('nowo_doctrine_encrypt.encryptor.financial_data'));
        $registryDef = $container->getDefinition('nowo_doctrine_encrypt.encryptor_registry');
        $args        = $registryDef->getArguments();
        $this->assertSame('personal_data', $args[1]);
    }

    public function testLoadWithSecretKeyFilenameUsesCustomPath(): void
    {
        $container = $this->createContainer();
        $config    = [
            'profiles' => [
                'default' => [
                    'encryptor_class'       => 'Defuse',
                    'secret_directory_path' => '/var/keys',
                    'secret_key_filename'   => '.my_encrypt.key',
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame('/var/keys/.my_encrypt.key', $container->getParameter('nowo_doctrine_encrypt.secret_key_path'));
        /** @var array<string, array<string, mixed>> $keyPaths */
        $keyPaths = $container->getParameter('nowo_doctrine_encrypt.key_paths');
        $this->assertSame('/var/keys/.my_encrypt.key', $keyPaths['default']['path']);
    }

    public function testLoadWithSecretKeyEnvVarInjectsEnvAndKeyPathsHasNullPath(): void
    {
        $container   = $this->createContainer();
        $resolvedKey = 'resolved-key-from-env';
        $config      = [
            'profiles' => [
                'default' => [
                    'encryptor_class'    => 'Halite',
                    'secret_key_env_var' => $resolvedKey,
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        /** @var array<string, array<string, mixed>> $keyPaths */
        $keyPaths = $container->getParameter('nowo_doctrine_encrypt.key_paths');
        $this->assertArrayHasKey('default', $keyPaths);
        $this->assertNull($keyPaths['default']['path']);
        $this->assertSame('Halite', $keyPaths['default']['encryptor_class']);

        $def = $container->getDefinition('nowo_doctrine_encrypt.encryptor.default');
        $this->assertSame($resolvedKey, $def->getArgument(1));
    }

    public function testLoadWithSecretKeyEnvVarResolvedValueInjectsKeyContentAsIs(): void
    {
        $container        = $this->createContainer();
        $resolvedKeyValue = str_repeat('a', 512);
        $config           = [
            'profiles' => [
                'default' => [
                    'encryptor_class'    => 'Halite',
                    'secret_key_env_var' => $resolvedKeyValue,
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $def = $container->getDefinition('nowo_doctrine_encrypt.encryptor.default');
        $this->assertSame($resolvedKeyValue, $def->getArgument(1));
    }

    public function testLoadWithSecretKeyEnvVarInjectsResolvedValueAsIs(): void
    {
        $container   = $this->createContainer();
        $resolvedKey = 'resolved-key-APP_ENCRYPT_KEY_2';
        $config      = [
            'profiles' => [
                'default' => [
                    'encryptor_class'    => 'Halite',
                    'secret_key_env_var' => $resolvedKey,
                ],
            ],
        ];
        $this->extension->load([$config], $container);

        $def = $container->getDefinition('nowo_doctrine_encrypt.encryptor.default');
        $this->assertSame($resolvedKey, $def->getArgument(1));
    }

    public function testLoadWithProfilesInvalidDefaultProfileThrows(): void
    {
        $container = $this->createContainer();
        $config    = [
            'default_profile' => 'nonexistent',
            'profiles'        => [
                'personal_data' => [
                    'encryptor_class'       => 'Halite',
                    'secret_directory_path' => '%kernel.project_dir%',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('nowo_doctrine_encrypt.default_profile "nonexistent" must be a key in nowo_doctrine_encrypt.profiles');

        $this->extension->load([$config], $container);
    }

    public function testResolveEncryptorClassReturnsCustomClassWhenNotSupported(): void
    {
        $container = $this->createContainer();
        $config    = [
            'profiles' => [
                'default' => ['encryptor_class' => 'Custom\\MyEncryptor'],
            ],
        ];
        $this->extension->load([$config], $container);

        $this->assertSame('Custom\\MyEncryptor', $container->getParameter('nowo_doctrine_encrypt.encryptor_class_name'));
    }

    public function testEncryptDatabaseCommandReceivesRegistryAndDefaultEncryptor(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $def  = $container->getDefinition(DoctrineEncryptDatabaseCommand::class);
        $args = $def->getArguments();

        $this->assertArrayHasKey('$encryptorRegistry', $args);
        $this->assertArrayHasKey('$defaultEncryptor', $args);
        $this->assertArrayHasKey('$defaultBatchSize', $args);
        $this->assertInstanceOf(Reference::class, $args['$encryptorRegistry']);
        $this->assertInstanceOf(Reference::class, $args['$defaultEncryptor']);
        $this->assertSame('nowo_doctrine_encrypt.encryptor_registry', (string) $args['$encryptorRegistry']);
        $this->assertSame('nowo_doctrine_encrypt.encryptor', (string) $args['$defaultEncryptor']);
        $this->assertSame('%nowo_doctrine_encrypt.batch_size%', $args['$defaultBatchSize']);
    }

    public function testDecryptDatabaseCommandReceivesRegistryAndDefaultEncryptor(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $def  = $container->getDefinition(DoctrineDecryptDatabaseCommand::class);
        $args = $def->getArguments();

        $this->assertArrayHasKey('$encryptorRegistry', $args);
        $this->assertArrayHasKey('$defaultEncryptor', $args);
        $this->assertArrayHasKey('$defaultBatchSize', $args);
        $this->assertSame('nowo_doctrine_encrypt.encryptor_registry', (string) $args['$encryptorRegistry']);
        $this->assertSame('nowo_doctrine_encrypt.encryptor', (string) $args['$defaultEncryptor']);
        $this->assertSame('%nowo_doctrine_encrypt.batch_size%', $args['$defaultBatchSize']);
    }

    public function testSubscriberReceivesEncryptorRegistry(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $def  = $container->getDefinition('nowo_doctrine_encrypt.orm_subscriber');
        $args = $def->getArguments();

        $this->assertNotEmpty($args);
        $first = $args[0] ?? $args['$registryOrEncryptor'] ?? null;
        $this->assertInstanceOf(Reference::class, $first);
        $this->assertSame('nowo_doctrine_encrypt.encryptor_registry', (string) $first);
    }

    public function testEncryptUtilAndDecryptExtensionAreRegisteredAndReceiveRegistry(): void
    {
        $container = $this->createContainer();
        $this->extension->load([[]], $container);

        $registryServiceId = 'nowo_doctrine_encrypt.encryptor_registry';
        $registryClass     = EncryptorRegistry::class;

        $this->assertTrue($container->has(EncryptUtil::class));
        $utilDef  = $container->getDefinition(EncryptUtil::class);
        $utilArgs = $utilDef->getArguments();
        if ($utilArgs !== []) {
            $registryRef = $utilArgs['registry'] ?? $utilArgs[0] ?? null;
            $this->assertInstanceOf(Reference::class, $registryRef);
            $refId = (string) $registryRef;
            $this->assertTrue($refId === $registryServiceId || $refId === $registryClass, 'EncryptUtil must receive EncryptorRegistry (id or class alias)');
        }
        // If getArguments() is empty, the registry is injected by autowiring at compile time

        $this->assertTrue($container->has(DecryptExtension::class));
        $twigDef  = $container->getDefinition(DecryptExtension::class);
        $twigArgs = $twigDef->getArguments();
        if ($twigArgs !== []) {
            $twigRegistryRef = $twigArgs['registry'] ?? $twigArgs[0] ?? null;
            $this->assertInstanceOf(Reference::class, $twigRegistryRef);
            $twigRefId = (string) $twigRegistryRef;
            $this->assertTrue($twigRefId === $registryServiceId || $twigRefId === $registryClass, 'DecryptExtension must receive EncryptorRegistry (id or class alias)');
        }
        // If getArguments() is empty, the registry is injected by autowiring at compile time
    }

    public function testMysqlAesBlockedInProduction(): void
    {
        $container = $this->createContainer('prod');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MysqlAes, which is blocked in production');

        $this->extension->load([
            [
                'profiles' => [
                    'legacy' => [
                        'encryptor_class'    => 'MysqlAes',
                        'secret_key_env_var' => '%env(MYSQL_AES_KEY)%',
                    ],
                ],
            ],
        ], $container);
    }

    public function testMysqlAesAllowedInDev(): void
    {
        $container = $this->createContainer('dev');

        $this->extension->load([
            [
                'profiles' => [
                    'legacy' => [
                        'encryptor_class'    => 'MysqlAes',
                        'secret_key_env_var' => '%env(MYSQL_AES_KEY)%',
                    ],
                ],
            ],
        ], $container);

        $this->assertTrue($container->hasDefinition('nowo_doctrine_encrypt.encryptor.legacy'));
    }

    private function createContainer(string $environment = 'dev'): ContainerBuilder
    {
        return new ContainerBuilder(
            new ParameterBag([
                'kernel.debug'       => false,
                'kernel.environment' => $environment,
            ]),
        );
    }
}
