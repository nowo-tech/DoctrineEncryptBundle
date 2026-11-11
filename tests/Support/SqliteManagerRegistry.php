<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Proxy;

use function array_combine;
use function array_keys;
use function array_map;
use function str_starts_with;
use function substr;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * A real ManagerRegistry over in-memory SQLite entity managers, one connection per manager (same name). Each manager
 * maps only the namespaces given to it; resetManager() replaces the manager instance (as DoctrineBundle does for
 * non-lazy managers) and keeps its connection.
 */
final class SqliteManagerRegistry extends AbstractManagerRegistry
{
    /** @var array<string, EntityManagerInterface> */
    private array $managers = [];

    /** @var array<string, Connection> */
    private array $connections = [];

    /**
     * @param array<string, array<string, string>> $mappings manager name => [namespace => directory]
     */
    public function __construct(private readonly array $mappings, string $default = 'default')
    {
        $names = array_keys($mappings);

        parent::__construct(
            'ORM',
            array_combine($names, array_map(static fn (string $name): string => 'conn.' . $name, $names)),
            array_combine($names, array_map(static fn (string $name): string => 'em.' . $name, $names)),
            $default,
            $default,
            Proxy::class,
        );
    }

    protected function getService(string $name): object
    {
        $manager = substr($name, str_starts_with($name, 'em.') ? 3 : 5);
        if (str_starts_with($name, 'conn.')) {
            return $this->connection($manager);
        }

        return $this->managers[$manager] ??= $this->create($manager);
    }

    protected function resetService(string $name): void
    {
        $manager                  = substr($name, 3);
        $this->managers[$manager] = $this->create($manager);
    }

    private function connection(string $name): Connection
    {
        return $this->connections[$name] ??= DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    private function create(string $name): EntityManagerInterface
    {
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(sys_get_temp_dir() . '/nowo-registry-tests/proxies');
            $config->setProxyNamespace('NowoRegistryTestProxies');
        }
        $chain = new MappingDriverChain();
        foreach ($this->mappings[$name] as $namespace => $directory) {
            $chain->addDriver(new AttributeDriver([$directory]), $namespace);
        }
        $config->setMetadataDriverImpl($chain);

        return new EntityManager($this->connection($name), $config);
    }
}
