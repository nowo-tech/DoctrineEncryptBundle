<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use LogicException;
use Nowo\DoctrineEncryptBundle\Command\ChunkedRun;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Exception\ChunkLockConflictException;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures\ChunkProcessingCommand;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Throwable;

use function fgets;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function usleep;

use const PHP_BINARY;

/**
 * REQ-RUNTIME-003 race tests of doctrine:encrypt:database / doctrine:decrypt:database on real databases, with two
 * connections (or a second process): the chunk rows are locked, an application write made during a chunk is never
 * overwritten, lock conflicts are retried and then answered with CONFLICT_001 / CONFLICT_002.
 *
 * Run by the "Databases" CI job with DE_MYSQL_URL, DE_MARIADB_URL and DE_POSTGRES_URL (--fail-on-skipped).
 */
final class ChunkLockDatabaseTest extends TestCase
{
    private const MARKER = DoctrineEncryptSubscriber::ENCRYPTION_MARKER;

    private ?Connection $conn = null;

    private ?Connection $second = null;

    /** @var (callable(string): void)|null Called by the encryptor with each plain value, inside the chunk transaction */
    private $onEncrypt;

    protected function tearDown(): void
    {
        foreach ([$this->second, $this->conn] as $conn) {
            if ($conn instanceof Connection) {
                while ($conn->isTransactionActive()) {
                    $conn->rollBack();
                }
                $conn->close();
            }
        }
        $this->conn      = null;
        $this->second    = null;
        $this->onEncrypt = null;
    }

    /**
     * The lost-update race of KA-H1's family: an application write to a row of the chunk being encrypted, made by
     * another process while the chunk runs, waits for the chunk's commit and lands after it. Before the row locks the
     * command overwrote it with the encrypted old value.
     */
    #[DataProvider('databases')]
    public function testApplicationWriteDuringChunkWaitsAndIsNotOverwritten(string $label, string $url): void
    {
        $this->connect($label, $url);
        $this->seed(6, static fn (int $i): string => 'plain' . $i);

        $writer          = null;
        $pipes           = [];
        $this->onEncrypt = function (string $value) use ($url, &$writer, &$pipes): void {
            if ($value !== 'plain2' || $writer !== null) {
                return;
            }
            $writer = proc_open([PHP_BINARY, __DIR__ . '/Support/concurrent_writer.php', $url, 'update-name:2:app-write'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout (test helper: the child sets a 10 s lock timeout and is reaped by proc_close)
            $this->assertTrue(is_resource($writer));
            $this->assertSame("ready\n", fgets($pipes[1]));
            // The writer sends its UPDATE 100 ms after "ready": give it time to reach the row lock and wait on it.
            usleep(500_000);
        };

        $tester = $this->runCommand($this->encryptCommand(), ['batchSize' => '3']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertTrue(is_resource($writer));
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($writer), $out . $err);
        $this->assertSame(1, preg_match('/^waited:(\d+)$/m', $out, $m), $out);
        $this->assertGreaterThanOrEqual(200, (int) $m[1], 'The application write must wait for the chunk lock');

        $names = $this->names();
        $this->assertSame('app-write', $names[2], 'The application write must survive (no lost update)');
        foreach ([1, 3, 4, 5, 6] as $id) {
            $this->assertSame('enc:plain' . $id . self::MARKER, $names[$id]);
        }
        $this->assertTrue($this->conn?->isConnected() ?? false);
        $this->assertSame(0, $this->conn->getTransactionNestingLevel());
    }

    /**
     * A row of the chunk is locked by another transaction for longer than the lock timeout on every attempt, and no
     * chunk committed before: CONFLICT_001 (nothing changed, running again is safe).
     */
    #[DataProvider('databases')]
    public function testLockedFirstChunkAnswersConflict001AndChangesNothing(string $label, string $url): void
    {
        $this->connect($label, $url);
        $this->seed(4, static fn (int $i): string => 'plain' . $i);
        $this->lockRowOnSecondConnection(2);

        $tester = $this->runCommand($this->encryptCommand(), ['batchSize' => '3', '--lock-timeout' => '1']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('after 3 attempt(s)', $tester->getDisplay());
        $this->assertStringContainsString('[' . ConflictCode::RETRY_SAFE . ']', $tester->getDisplay());
        $this->assertStringContainsString('Nothing was changed', $tester->getDisplay());
        $this->second?->rollBack();
        foreach ($this->names() as $id => $name) {
            $this->assertSame('plain' . $id, $name);
        }
        $this->assertSame(0, $this->conn?->getTransactionNestingLevel());
    }

    /**
     * The second chunk stays locked: the first chunk is committed, the run stops with CONFLICT_002 (read again, do
     * not repeat blindly), and running the command again resumes where it stopped.
     */
    #[DataProvider('databases')]
    public function testLockedLaterChunkAnswersConflict002AndRerunResumes(string $label, string $url): void
    {
        $this->connect($label, $url);
        $this->seed(6, static fn (int $i): string => 'enc:plain' . $i . self::MARKER);
        $this->lockRowOnSecondConnection(5);
        $previousTimeout = $this->sessionLockTimeout();

        $tester = $this->runCommand($this->decryptCommand(), ['batchSize' => '3', '--lock-timeout' => '1']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('[' . ConflictCode::COMMITTED . ']', $tester->getDisplay());
        $this->assertStringContainsString('Values decrypted before the conflict: 3', $tester->getDisplay());
        $this->assertSame($previousTimeout, $this->sessionLockTimeout(), 'The session lock timeout is restored');
        $names = $this->names();
        foreach ([1, 2, 3] as $id) {
            $this->assertSame('plain' . $id, $names[$id]);
        }
        foreach ([4, 5, 6] as $id) {
            $this->assertSame('enc:plain' . $id . self::MARKER, $names[$id], 'The failed chunk is rolled back');
        }

        $this->second?->rollBack();
        $tester = $this->runCommand($this->decryptCommand(), ['batchSize' => '3']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Values decrypted: 3', $tester->getDisplay());
        foreach ($this->names() as $id => $name) {
            $this->assertSame('plain' . $id, $name);
        }
    }

    /**
     * Replay: the lock is released while the chunk waits for its next attempt. The chunk then commits once, and what
     * the failed attempt counted is dropped (exactly one effect per value).
     */
    #[DataProvider('databases')]
    public function testChunkReplayedAfterLockReleaseCountsOnce(string $label, string $url): void
    {
        $this->connect($label, $url);
        $this->seed(3, static fn (int $i): string => 'plain' . $i);
        $this->lockRowOnSecondConnection(3);
        $second = $this->second;
        $run    = new ChunkedRun(1, 3, static function () use ($second): void {
            $second?->rollBack();
        });
        $conn  = $this->conn;
        $table = $conn?->getDatabasePlatform()->quoteIdentifier('user') ?? '';
        $seen  = 0;

        $this->exposingCommand()->process($conn ?? throw new LogicException('No connection'), $table, [$this->quote('id'), $this->quote('name')], [$this->quote('id')], 10, static function (array $row) use ($conn, $table, $run, &$seen): void {
            ++$seen;
            $run->add('encrypted');
            $conn->executeStatement('UPDATE ' . $table . ' SET name = ? WHERE id = ?', ['enc:' . $row['name'] . self::MARKER, $row['id']]);
        }, $run);

        $this->assertSame(3, $run->total('encrypted'), 'Only the committed attempt counts');
        $this->assertSame(3, $seen, 'The first attempt timed out on the lock before seeing any row');
        $this->assertSame(1, $run->committedChunks());
        foreach ($this->names() as $id => $name) {
            $this->assertSame('enc:plain' . $id . self::MARKER, $name);
        }
    }

    /**
     * A real deadlock between the chunk and an application transaction that writes two rows of the chunk in the
     * opposite order: whichever side the server picks as the victim, nothing is lost. When the application wins, the
     * chunk is replayed and encrypts the application's value.
     */
    #[DataProvider('databases')]
    public function testDeadlockWithApplicationTransactionLosesNothing(string $label, string $url): void
    {
        $this->connect($label, $url);
        $this->seed(3, static fn (int $i): string => 'plain' . $i);

        $pipes  = [];
        $writer = proc_open([PHP_BINARY, __DIR__ . '/Support/concurrent_writer.php', $url, 'deadlock:2:1'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes); // @phpstan-ignore frankenphp.classic.noUnlimitedIoTimeout (test helper: the child sets a 10 s lock timeout and is reaped by proc_close)
        $this->assertTrue(is_resource($writer));
        $this->assertSame("locked\n", fgets($pipes[1]));

        $tester = $this->runCommand($this->encryptCommand(), ['batchSize' => '3', '--lock-timeout' => '5']);
        $out    = (string) stream_get_contents($pipes[1]);
        $err    = (string) stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($writer), $out . $err);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay() . $out);
        $rows = $this->conn?->fetchAllAssociative('SELECT id, name, address FROM ' . $this->table() . ' ORDER BY id') ?? [];
        if (str_starts_with($out, 'committed')) {
            $this->assertSame('enc:app-write' . self::MARKER, $rows[1]['name'], 'The application write is kept (then encrypted)');
            $this->assertSame('enc:app-address' . self::MARKER, $rows[0]['address'], 'address is an encrypted column too');
        } else {
            $this->assertStringStartsWith('rolled-back:', $out);
            $this->assertSame('enc:plain2' . self::MARKER, $rows[1]['name']);
        }
        $this->assertSame('enc:plain1' . self::MARKER, $rows[0]['name']);
        $this->assertSame('enc:plain3' . self::MARKER, $rows[2]['name']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function databases(): iterable
    {
        return DatabaseUrls::provider();
    }

    private function connect(string $label, string $url): void
    {
        if ($url === '') {
            $this->markTestSkipped($label . ': set its DE_*_URL variable to run the real-database tests.');
        }
        $conn         = DatabaseUrls::connect($url);
        $this->conn   = $conn;
        $this->second = DatabaseUrls::connect($url);
        $this->limitLockWait($this->second, 5);
        $table = $this->table();
        $conn->executeStatement('DROP TABLE IF EXISTS ' . $table);
        $conn->executeStatement('CREATE TABLE ' . $table . ' (id INT NOT NULL PRIMARY KEY, name VARCHAR(255) NULL, address VARCHAR(255) NULL)');
    }

    /**
     * @param callable(int): string $name
     */
    private function seed(int $count, callable $name): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->conn?->insert($this->table(), ['id' => $i, 'name' => $name($i), 'address' => null]);
        }
    }

    private function lockRowOnSecondConnection(int $id): void
    {
        $this->second?->beginTransaction();
        $this->second?->executeStatement('UPDATE ' . $this->table() . ' SET address = address WHERE id = ?', [$id]);
    }

    private function limitLockWait(Connection $conn, int $seconds): void
    {
        $platform = $conn->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            $conn->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $seconds);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $conn->executeStatement("SET lock_timeout = '" . $seconds . "s'");
        }
    }

    private function sessionLockTimeout(): string
    {
        $platform = $this->conn?->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            return (string) $this->conn?->fetchOne('SELECT @@SESSION.innodb_lock_wait_timeout');
        }

        return (string) $this->conn?->fetchOne('SHOW lock_timeout');
    }

    private function quote(string $identifier): string
    {
        return $this->conn?->getDatabasePlatform()->quoteIdentifier($identifier) ?? $identifier;
    }

    private function table(): string
    {
        return $this->conn?->getDatabasePlatform()->quoteIdentifier('user') ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function names(): array
    {
        $names = [];
        foreach ($this->conn?->fetchAllAssociative('SELECT id, name FROM ' . $this->table() . ' ORDER BY id') ?? [] as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    private function encryptor(): EncryptorInterface
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(function (string $d): string {
            if ($this->onEncrypt !== null) {
                ($this->onEncrypt)($d);
            }

            return 'enc:' . $d;
        });
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $d): string => substr($d, 4));

        return $encryptor;
    }

    private function encryptCommand(): DoctrineEncryptDatabaseCommand
    {
        return new DoctrineEncryptDatabaseCommand($this->entityManager(), new AttributeReader(), new DoctrineEncryptSubscriber(), null, new EncryptorRegistry(['default' => $this->encryptor()], 'default'));
    }

    private function decryptCommand(): DoctrineDecryptDatabaseCommand
    {
        return new DoctrineDecryptDatabaseCommand($this->entityManager(), new AttributeReader(), new DoctrineEncryptSubscriber(), null, new EncryptorRegistry(['default' => $this->encryptor()], 'default'));
    }

    private function exposingCommand(): ChunkProcessingCommand
    {
        return new ChunkProcessingCommand($this->entityManager(), new AttributeReader(), new DoctrineEncryptSubscriber());
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(Command $command, array $input): CommandTester
    {
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        try {
            $tester->execute($input + ['config' => 'default', '--force' => true], ['interactive' => false]);
        } catch (ChunkLockConflictException $e) {
            $this->fail('The command must answer the conflict itself: ' . $e->getMessage());
        } catch (Throwable $e) {
            $this->fail($e::class . ' (transient: ' . ((new TransientFailureClassifier())->isTransient($e) ? 'yes' : 'no') . '): ' . $e->getMessage());
        }

        return $tester;
    }

    private function entityManager(): EntityManagerInterface
    {
        $metadata = new class {
            public string $name             = User::class;
            public bool $isMappedSuperclass = false;

            public function getTableName(): string
            {
                return 'user';
            }

            /** @return list<string> */
            public function getIdentifierFieldNames(): array
            {
                return ['id'];
            }

            public function getColumnName(string $fieldName): string
            {
                return $fieldName;
            }
        };

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($this->conn);

        return $em;
    }
}
