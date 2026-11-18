<?php

declare(strict_types=1);

/*
 * A second process writing like the application while a database command runs (REQ-RUNTIME-003 race tests).
 *
 * Usage: php concurrent_writer.php <url> <mode>
 *   update-name:<id>:<value>   print "ready", then UPDATE one row's name (autocommit, waits for the row lock) and
 *                              print the wait in ms
 *   deadlock:<first>:<second>  in one transaction: write row <first>, print "locked", wait 500 ms, write row <second>,
 *                              commit; print "committed" or "rolled-back:<class>"
 *
 * Every session sets a 10 second lock timeout, so a hang fails fast instead of holding the machine.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Nowo\DoctrineEncryptBundle\Tests\Database\DatabaseUrls;

require __DIR__ . '/../../../vendor/autoload.php';

/* @var array{0: string, 1?: string, 2?: string} $argv */
[, $url, $mode] = $argv + [1 => '', 2 => ''];
$conn           = DatabaseUrls::connect($url);
limitLockWait($conn, 10);
$table = $conn->getDatabasePlatform()->quoteIdentifier('user');
$parts = explode(':', $mode, 3);

if ($parts[0] === 'update-name') {
    $conn->fetchOne('SELECT 1');
    fwrite(\STDOUT, "ready\n");
    fflush(\STDOUT);
    usleep(100_000);
    $start = hrtime(true);
    $conn->executeStatement('UPDATE ' . $table . ' SET name = ? WHERE id = ?', [$parts[2], (int) $parts[1]]);
    fwrite(\STDOUT, 'waited:' . (int) ((hrtime(true) - $start) / 1_000_000) . "\n");

    return;
}

if ($parts[0] === 'deadlock') {
    $conn->beginTransaction();
    try {
        $conn->executeStatement('UPDATE ' . $table . ' SET name = ? WHERE id = ?', ['app-write', (int) $parts[1]]);
        fwrite(\STDOUT, "locked\n");
        fflush(\STDOUT);
        usleep(500_000);
        $conn->executeStatement('UPDATE ' . $table . ' SET address = ? WHERE id = ?', ['app-address', (int) $parts[2]]);
        $conn->commit();
        fwrite(\STDOUT, "committed\n");
    } catch (Throwable $e) {
        if ($conn->isTransactionActive()) {
            $conn->rollBack();
        }
        fwrite(\STDOUT, 'rolled-back:' . $e::class . "\n");
    }

    return;
}

throw new InvalidArgumentException('Unknown mode');
function limitLockWait(Connection $conn, int $seconds): void
{
    $platform = $conn->getDatabasePlatform();
    if ($platform instanceof AbstractMySQLPlatform) {
        $conn->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $seconds);
    } elseif ($platform instanceof PostgreSQLPlatform) {
        $conn->executeStatement("SET lock_timeout = '" . $seconds . "s'");
    }
}
