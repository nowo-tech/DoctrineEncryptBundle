<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Database;

use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\AbstractCleanChangeSetTestCase;

/**
 * Clean change set scenarios on MariaDB 11.4 (DE_MARIADB_URL; run by the "Databases" CI job).
 */
final class MariaDbCleanChangeSetTest extends AbstractCleanChangeSetTestCase
{
    protected function connectionParams(): ?array
    {
        return DatabaseUrls::params('DE_MARIADB_URL');
    }
}
