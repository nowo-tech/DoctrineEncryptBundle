<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Functional;

use Nowo\DoctrineEncryptBundle\Tests\Functional\SqlQueryCollector;
use PHPUnit\Framework\TestCase;

class SqlQueryCollectorTest extends TestCase
{
    public function testLogAddsQueryWithMessageAsSql(): void
    {
        $collector = new SqlQueryCollector();
        $collector->log('debug', 'SELECT 1', []);

        $this->assertCount(1, $collector->queries);
        $this->assertSame('SELECT 1', $collector->queries[0]['sql']);
    }

    public function testLogUsesContextSqlWhenPresent(): void
    {
        $collector = new SqlQueryCollector();
        $collector->log('debug', 'fallback', ['sql' => 'INSERT INTO t (a) VALUES (?)']);

        $this->assertCount(1, $collector->queries);
        $this->assertSame('INSERT INTO t (a) VALUES (?)', $collector->queries[0]['sql']);
    }

    public function testLogAppendsMultipleQueries(): void
    {
        $collector = new SqlQueryCollector();
        $collector->log('debug', 'SELECT 1');
        $collector->log('debug', 'SELECT 2');

        $this->assertCount(2, $collector->queries);
        $this->assertSame('SELECT 1', $collector->queries[0]['sql']);
        $this->assertSame('SELECT 2', $collector->queries[1]['sql']);
    }
}
