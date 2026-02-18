<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Functional;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that collects SQL queries for tests.
 * Used when Doctrine DBAL 4+ is in use (DebugStack and setSQLLogger were removed).
 *
 * @internal
 */
final class SqlQueryCollector extends AbstractLogger
{
    /** @var list<array{sql: string}> */
    public $queries = [];

    public function log($level, $message, array $context = []): void
    {
        $this->queries[] = [
            'sql' => $context['sql'] ?? $message,
            'params' => $context['params'] ?? [],
        ];
    }
}
