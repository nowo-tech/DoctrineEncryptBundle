<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptStatusCommand;
use Nowo\DoctrineEncryptBundle\Command\RotateKeysCommand;
use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntimeConfigurator;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Twig\DecryptExtension;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtilInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

use function in_array;

/**
 * REQ-ARCH-001 rule 1: EncryptorRegistry and EncryptUtil have interfaces aliased in the container; a host replaces
 * them by aliasing the interface, and the bundle's own consumers follow.
 */
final class PortsTest extends TestCase
{
    public function testTheDefaultsImplementThePorts(): void
    {
        self::assertTrue((new ReflectionClass(EncryptorRegistry::class))->implementsInterface(EncryptorRegistryInterface::class));
        self::assertTrue((new ReflectionClass(EncryptUtil::class))->implementsInterface(EncryptUtilInterface::class));
    }

    public function testThePortsAreAliasedToTheDefaultServicesAndTheOldIdsStillResolve(): void
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false, 'kernel.environment' => 'dev']));
        (new DoctrineEncryptExtension())->load([[]], $container);

        self::assertSame('nowo_doctrine_encrypt.encryptor_registry', (string) $container->getAlias(EncryptorRegistryInterface::class));
        self::assertSame(EncryptUtil::class, (string) $container->getAlias(EncryptUtilInterface::class));
        self::assertSame('nowo_doctrine_encrypt.encryptor_registry', (string) $container->getAlias(EncryptorRegistry::class));
        self::assertTrue($container->hasDefinition(EncryptUtil::class));
    }

    public function testAHostAliasReachesTheListenerTheConfiguratorAndTheCommands(): void
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false, 'kernel.environment' => 'dev', 'kernel.project_dir' => sys_get_temp_dir()]));
        (new DoctrineEncryptExtension())->load([['dbal_types' => true]], $container);

        $consumers = [
            'nowo_doctrine_encrypt.orm_subscriber'              => 0,
            DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE => 0,
        ];
        foreach ($consumers as $id => $index) {
            $argument = $container->getDefinition($id)->getArgument($index);
            self::assertInstanceOf(Reference::class, $argument, $id);
            self::assertSame(EncryptorRegistryInterface::class, (string) $argument, $id);
        }
        foreach ([DoctrineEncryptDatabaseCommand::class, DoctrineDecryptDatabaseCommand::class, DoctrineEncryptStatusCommand::class, RotateKeysCommand::class] as $command) {
            $argument = $container->getDefinition($command)->getArgument('$encryptorRegistry');
            self::assertInstanceOf(Reference::class, $argument, $command);
            self::assertSame(EncryptorRegistryInterface::class, (string) $argument, $command);
        }

        // Compile only the consumers around a host registry: the listener and the configurator get the host object.
        $keep = ['service_container', 'nowo_doctrine_encrypt.orm_subscriber', DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE, 'nowo_doctrine_encrypt.encrypted_field_map'];
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (!in_array($id, $keep, true)) {
                $container->removeDefinition($id);
            }
        }
        foreach (array_keys($container->getAliases()) as $id) {
            $container->removeAlias($id);
        }
        $container->register(HostRegistry::class, HostRegistry::class);
        $container->setAlias(EncryptorRegistryInterface::class, HostRegistry::class);
        $container->getDefinition('nowo_doctrine_encrypt.orm_subscriber')->setPublic(true);
        $container->getDefinition(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE)->setPublic(true);
        $container->compile();

        $listener = $container->get('nowo_doctrine_encrypt.orm_subscriber');
        self::assertInstanceOf(DoctrineEncryptSubscriber::class, $listener);
        self::assertInstanceOf(HostRegistry::class, (new ReflectionProperty($listener, 'registry'))->getValue($listener));
        $configurator = $container->get(DoctrineEncryptExtension::DBAL_TYPE_RUNTIME_SERVICE);
        self::assertInstanceOf(EncryptedTypeRuntimeConfigurator::class, $configurator);
        self::assertInstanceOf(HostRegistry::class, (new ReflectionProperty($configurator, 'registry'))->getValue($configurator));
    }

    public function testAHostRegistryReachesEncryptUtilAndTheTwigExtension(): void
    {
        $container = new ContainerBuilder();
        $container->register(HostRegistry::class, HostRegistry::class);
        $container->setAlias(EncryptorRegistryInterface::class, HostRegistry::class);
        $container->register(EncryptUtil::class, EncryptUtil::class)->setAutowired(true)->setPublic(true);
        $container->register(DecryptExtension::class, DecryptExtension::class)->setAutowired(true)->setPublic(true);
        $container->compile();

        $util = $container->get(EncryptUtil::class);
        self::assertInstanceOf(EncryptUtil::class, $util);
        self::assertSame('host(secret)' . EncryptUtil::ENCRYPTION_MARKER, $util->encrypt('secret'));
        self::assertSame('secret', $util->decrypt('host(secret)' . EncryptUtil::ENCRYPTION_MARKER));

        $twig = $container->get(DecryptExtension::class);
        self::assertInstanceOf(DecryptExtension::class, $twig);
        self::assertSame('secret', $twig->decrypt('host(secret)' . EncryptUtil::ENCRYPTION_MARKER));
    }
}

final readonly class HostRegistry implements EncryptorRegistryInterface
{
    private EncryptorInterface $encryptor;

    public function __construct()
    {
        $this->encryptor = new class implements EncryptorInterface {
            public function encrypt(string $data): string
            {
                return 'host(' . $data . ')';
            }

            public function decrypt(string $data): string
            {
                return substr($data, 5, -1);
            }
        };
    }

    public function get(string $name): EncryptorInterface
    {
        if ($name !== 'default') {
            throw new InvalidArgumentException('Unknown encryptor config "' . $name . '".');
        }

        return $this->encryptor;
    }

    public function getDefault(): EncryptorInterface
    {
        return $this->encryptor;
    }

    public function getDefaultName(): string
    {
        return 'default';
    }

    public function has(string $name): bool
    {
        return $name === 'default';
    }

    public function getConfigNames(): array
    {
        return ['default'];
    }
}
