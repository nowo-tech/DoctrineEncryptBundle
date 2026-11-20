<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet;

/**
 * Clean change set scenarios on SQLite (in memory).
 */
final class CleanChangeSetSqliteTest extends AbstractCleanChangeSetTestCase
{
    protected function connectionParams(): array
    {
        return ['driver' => 'pdo_sqlite', 'memory' => true];
    }
}
