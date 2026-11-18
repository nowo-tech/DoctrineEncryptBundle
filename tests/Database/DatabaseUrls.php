<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

use function getenv;
use function is_string;

/**
 * Real databases of the "Databases" CI job (MySQL 8.4, MariaDB 11.4, PostgreSQL 16), from DE_*_URL variables.
 */
final class DatabaseUrls
{
    private const VARIABLES = [
        'MySQL 8.4'     => 'DE_MYSQL_URL',
        'MariaDB 11.4'  => 'DE_MARIADB_URL',
        'PostgreSQL 16' => 'DE_POSTGRES_URL',
    ];

    /**
     * @return iterable<string, array{string, string}> Database label => [label, URL or '' when the variable is not set]
     */
    public static function provider(): iterable
    {
        foreach (self::VARIABLES as $label => $variable) {
            $url = getenv($variable);

            yield $label => [$label, is_string($url) ? $url : ''];
        }
    }

    public static function connect(string $url): Connection
    {
        $params = (new DsnParser(['mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql', 'pgsql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);

        return DriverManager::getConnection($params);
    }
}
