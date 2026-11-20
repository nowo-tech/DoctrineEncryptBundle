<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet;

use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Clean change set scenarios on SQLite (a file, so a second entity manager sees the same database).
 */
final class CleanChangeSetSqliteTest extends AbstractCleanChangeSetTestCase
{
    private string $file = '';

    protected function connectionParams(): array
    {
        $file       = tempnam(sys_get_temp_dir(), 'nowo_encrypt_ccs');
        $this->file = $file !== false ? $file : sys_get_temp_dir() . '/nowo_encrypt_ccs.sqlite';

        return ['driver' => 'pdo_sqlite', 'path' => $this->file];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }
}
