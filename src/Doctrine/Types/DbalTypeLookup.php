<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Exception\TypesException;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;

use function is_callable;

/**
 * Finds a registered DBAL type by name without the static Type::hasType() / Type::getType() calls that doctrine/dbal
 * 4.6 deprecates ("ask the type provider of the connection instead").
 *
 * With doctrine/dbal >= 4.6 the type provider of the connection's configuration is asked; with older versions (which
 * have no type provider, and where the static registry is not deprecated) the static registry is. An unknown name
 * gives null on both paths. Stateless: nothing is cached, so it is safe in a kept-alive worker.
 */
final class DbalTypeLookup
{
    /**
     * @param Connection|null $connection Connection whose type provider is asked (doctrine/dbal >= 4.6); null reads the static registry
     */
    public static function find(?Connection $connection, string $name): ?Type
    {
        if ($connection instanceof Connection) {
            $configuration = $connection->getConfiguration();
            if (is_callable([$configuration, 'getTypeProvider'])) {
                $provider = $configuration->getTypeProvider();

                return $provider->has($name) ? $provider->get($name) : null;
            }
        }

        return self::findInStaticRegistry($name);
    }

    /**
     * The static registry, the only source before doctrine/dbal 4.6.
     */
    public static function findInStaticRegistry(string $name): ?Type
    {
        return Type::hasType($name) ? Type::getType($name) : null;
    }

    /**
     * Name the type instance is registered under in the type registry that doctrine/dbal's own bootstrap fills (the
     * static one DoctrineBundle registers the configured types in).
     *
     * Type::getTypeRegistry() is deprecated since doctrine/dbal 4.6 for callers outside DBAL; DBAL's own
     * Configuration::getTypeProvider() returns that same registry without the deprecation.
     *
     * @throws TypesException When the instance is not registered
     */
    public static function nameOf(Type $type): string
    {
        $configuration = new Configuration();
        /** @var TypeRegistry $registry */
        $registry = is_callable([$configuration, 'getTypeProvider']) ? $configuration->getTypeProvider() : Type::getTypeRegistry();

        return $registry->lookupName($type);
    }
}
