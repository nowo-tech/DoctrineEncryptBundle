<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Support;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Doctrine\Deprecations\Deprecation;

use function is_callable;

/**
 * Connections carrying DBAL types, registered the way the installed doctrine/dbal wants: in the type provider of the
 * connection (>= 4.6, where the static Type::addType() is deprecated) or in the static registry (older versions).
 */
final class DbalTypeFixtures
{
    /**
     * Registers the types (once) in the registry the connections of this process share, and returns a connection.
     *
     * @param array<string, class-string<Type>> $types Type name => class
     */
    public static function connection(array $types): Connection
    {
        $configuration = new Configuration();
        $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
        if (is_callable([$configuration, 'getTypeProvider'])) {
            // The registry DBAL itself hands out (what DoctrineBundle fills): no deprecated static Type call.
            /** @var TypeRegistry $registry */
            $registry = $configuration->getTypeProvider();
            foreach ($types as $name => $class) {
                if (!$registry->has($name)) {
                    $registry->register($name, $class);
                }
            }
        } else {
            foreach ($types as $name => $class) {
                if (!Type::hasType($name)) {
                    Type::addType($name, $class);
                }
            }
        }

        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    }

    /**
     * Runs the callback with Doctrine deprecation tracking on and returns, per deprecation link, how many it raised.
     *
     * @return array<string, int>
     */
    public static function deprecationsRaisedBy(callable $callback): array
    {
        Deprecation::enableTrackingDeprecations();
        $before = Deprecation::getTriggeredDeprecations();
        try {
            $callback();
            $after = Deprecation::getTriggeredDeprecations();
        } finally {
            Deprecation::disable();
        }

        $raised = [];
        foreach ($after as $link => $count) {
            if ($count > ($before[$link] ?? 0)) {
                $raised[$link] = $count - ($before[$link] ?? 0);
            }
        }

        return $raised;
    }
}
