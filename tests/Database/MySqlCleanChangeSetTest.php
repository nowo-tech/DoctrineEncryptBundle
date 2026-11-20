<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Database;

use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\AbstractCleanChangeSetTestCase;

/**
 * Clean change set scenarios on MySQL 8.4 (DE_MYSQL_URL; run by the "Databases" CI job).
 */
final class MySqlCleanChangeSetTest extends AbstractCleanChangeSetTestCase
{
    protected function connectionParams(): ?array
    {
        return DatabaseUrls::params('DE_MYSQL_URL');
    }
}
