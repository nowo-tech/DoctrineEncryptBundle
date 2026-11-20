<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Database;

use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\AbstractCleanChangeSetTestCase;

/**
 * Clean change set scenarios on PostgreSQL 16 (DE_POSTGRES_URL; run by the "Databases" CI job).
 */
final class PostgreSqlCleanChangeSetTest extends AbstractCleanChangeSetTestCase
{
    protected function connectionParams(): ?array
    {
        return DatabaseUrls::params('DE_POSTGRES_URL');
    }
}
