<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\DependencyInjection;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Twig\DecryptExtension;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtilInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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
