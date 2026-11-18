<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use ArrayObject;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Command\ChunkedRun;
use Nowo\DoctrineEncryptBundle\Exception\ChunkLockConflictException;
use Nowo\DoctrineEncryptBundle\Exception\ExceptionInterface;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures\ChunkProcessingCommand;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Locked, replayable chunks of the database commands (REQ-RUNTIME-003): keyset reads, locked re-reads by identifier,
 * lock timeouts per platform, replay after a lock conflict and the CONFLICT_001 / CONFLICT_002 answer.
 */
final class ChunkedProcessingTest extends TestCase
{
    private Connection $sqlite;

    protected function setUp(): void
    {
        $this->sqlite = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    public function testChunkedRunRejectsInvalidSettings(): void
    {
        try {
            new ChunkedRun(-1);
            $this->fail('A negative lock timeout must be rejected');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('lock timeout', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one attempt');
        new ChunkedRun(1, 0);
    }

    public function testChunkedRunKeepsOnlyCommittedAttempts(): void
    {
        $rows = [];
        $run  = new ChunkedRun();
        $run->onChunkCommitted(static function (int $n) use (&$rows): void {
            $rows[] = $n;
        });
        $this->assertSame(ChunkedRun::DEFAULT_LOCK_TIMEOUT_SECONDS, $run->lockTimeoutSeconds);
        $this->assertSame(ChunkedRun::DEFAULT_MAX_ATTEMPTS, $run->maxAttempts);

        $run->add('encrypted', 2);
        $run->record('failures', 'lost');
        $run->beginAttempt();
        $run->add('encrypted');
        $run->add('encrypted');
        $run->record('failures', 'kept');
        $run->commitAttempt(5);
        $run->add('encrypted', 7);

        $this->assertSame(2, $run->total('encrypted'));
        $this->assertSame(0, $run->total('other'));
        $this->assertSame(['kept'], $run->records('failures'));
        $this->assertSame([], $run->records('other'));
        $this->assertSame(1, $run->committedChunks());
        $this->assertSame([5], $rows);

        $run->beginAttempt();
        $run->record('failures', 'second');
        $run->commitAttempt(1);
        $this->assertSame(['kept', 'second'], $run->records('failures'));
        $this->assertSame(2, $run->total('encrypted'), 'What was pending before beginAttempt() is dropped');

        // Default backoff waits 100 ms per attempt; attempt 0 does not wait.
        $run->backoff(0);
    }

    public function testCustomBackoffReceivesTheAttempt(): void
    {
        $seen = [];
        $run  = new ChunkedRun(0, 3, static function (int $attempt) use (&$seen): void {
            $seen[] = $attempt;
        });
        $run->backoff(2);
        $this->assertSame([2], $seen);
    }

    public function testConflictExceptionCarriesCodeAndTable(): void
    {
        $previous = new RuntimeException('storage');
        $safe     = ChunkLockConflictException::afterAttempts('"user"', 3, false, $previous);
        $this->assertContains(ExceptionInterface::class, class_implements($safe));
        $this->assertSame(ConflictCode::RetrySafe, $safe->conflictCode);
        $this->assertSame('"user"', $safe->table);
        $this->assertSame($previous, $safe->getPrevious());
        $this->assertStringContainsString('after 3 attempt(s)', $safe->getMessage());
        $this->assertStringContainsString('[CONFLICT_001]', $safe->getMessage());

        $committed = ChunkLockConflictException::afterAttempts('"user"', 1, true, $previous);
        $this->assertSame(ConflictCode::Committed, $committed->conflictCode);
        $this->assertStringContainsString('[CONFLICT_002]', $committed->getMessage());
    }

    public function testNoIdentifierColumnIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->command()->process($this->sqlite, '"t"', ['"v"'], [], 2, static function (): void {
        });
    }

    public function testSingleIdentifierChunksUseKeysetAndSeeEveryRowOnce(): void
    {
        $this->sqlite->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        foreach ([3, 1, 7, 5, 9] as $id) {
            $this->sqlite->insert('t', ['id' => $id, 'v' => 'v' . $id]);
        }
        $seen   = [];
        $chunks = [];
        $run    = new ChunkedRun();
        $run->onChunkCommitted(static function (int $n) use (&$chunks): void {
            $chunks[] = $n;
        });

        $this->command()->process($this->sqlite, 't', ['id', 'v'], ['id'], 2, function (array $row) use (&$seen): void {
            // Rows inserted or deleted between chunks never shift the keyset.
            if ($row['id'] === 3) {
                $this->sqlite->delete('t', ['id' => 1]);
                $this->sqlite->insert('t', ['id' => 4, 'v' => 'v4']);
            }
            $seen[] = (int) $row['id'];
        }, $run);

        $this->assertSame([1, 3, 4, 5, 7, 9], $seen);
        $this->assertSame([2, 2, 2], $chunks);
        $this->assertSame(3, $run->committedChunks());
    }

    public function testDefaultRunIsCreatedWhenNoneIsGiven(): void
    {
        $this->sqlite->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $this->sqlite->insert('t', ['id' => 1, 'v' => 'a']);
        $seen = 0;
        $this->command()->process($this->sqlite, 't', ['id', 'v'], ['id'], 5, static function () use (&$seen): void {
            ++$seen;
        });
        $this->assertSame(1, $seen);
    }

    public function testCompositeIdentifierChunksUseExpandedKeyset(): void
    {
        $this->sqlite->executeStatement('CREATE TABLE t (a INTEGER NOT NULL, b INTEGER NOT NULL, v TEXT, PRIMARY KEY (a, b))');
        foreach ([[1, 1], [1, 2], [2, 1], [2, 3], [3, 0]] as [$a, $b]) {
            $this->sqlite->insert('t', ['a' => $a, 'b' => $b, 'v' => $a . '-' . $b]);
        }
        $seen = [];
        $this->command()->process($this->sqlite, 't', ['a', 'b', 'v'], ['a', 'b'], 2, static function (array $row) use (&$seen): void {
            $seen[] = $row['v'];
        }, new ChunkedRun());

        $this->assertSame(['1-1', '1-2', '2-1', '2-3', '3-0'], $seen);
    }

    public function testTransientFailureReplaysTheChunkAndCountsOnce(): void
    {
        $this->sqlite->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $this->sqlite->insert('t', ['id' => 1, 'v' => 'a']);
        $this->sqlite->insert('t', ['id' => 2, 'v' => 'b']);
        $attempts = [];
        $run      = new ChunkedRun(0, 3, static function (int $attempt) use (&$attempts): void {
            $attempts[] = $attempt;
        });
        $calls = 0;

        $this->command()->process($this->sqlite, 't', ['id', 'v'], ['id'], 5, function (array $row) use ($run, &$calls): void {
            ++$calls;
            $run->add('done');
            $this->sqlite->update('t', ['v' => 'x' . $row['v']], ['id' => $row['id']]);
            if ($calls === 2) {
                throw $this->deadlock();
            }
        }, $run);

        $this->assertSame([1], $attempts);
        $this->assertSame(2, $run->total('done'), 'The rolled-back attempt does not count');
        $this->assertSame(['xa', 'xb'], $this->sqlite->fetchFirstColumn('SELECT v FROM t ORDER BY id'), 'The rolled-back attempt left no write');
        $this->assertSame(0, $this->sqlite->getTransactionNestingLevel());
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function exhaustedProvider(): iterable
    {
        yield 'first chunk: nothing committed' => [false, ConflictCode::RETRY_SAFE];
        yield 'later chunk: part committed' => [true, ConflictCode::COMMITTED];
    }

    #[DataProvider('exhaustedProvider')]
    public function testExhaustedAttemptsThrowConflictWithItsCode(bool $failLaterChunk, string $code): void
    {
        $this->sqlite->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        foreach ([1, 2, 3] as $id) {
            $this->sqlite->insert('t', ['id' => $id, 'v' => 'v' . $id]);
        }
        $run = new ChunkedRun(0, 2, static function (): void {
        });

        try {
            $this->command()->process($this->sqlite, 't', ['id', 'v'], ['id'], 2, function (array $row) use ($run, $failLaterChunk): void {
                $run->add('done');
                if (!$failLaterChunk || $row['id'] === 3) {
                    throw $this->deadlock();
                }
            }, $run);
            $this->fail('The conflict must be thrown');
        } catch (ChunkLockConflictException $e) {
            $this->assertSame($code, $e->conflictCode->value);
            $this->assertStringContainsString('after 2 attempt(s)', $e->getMessage());
            $this->assertInstanceOf(TransientStorageFailure::class, $e->getPrevious());
        }
        $this->assertSame($failLaterChunk ? 2 : 0, $run->total('done'));
        $this->assertSame(0, $this->sqlite->getTransactionNestingLevel());
    }

    public function testOtherFailureIsRethrownAndDropsPendingCounts(): void
    {
        $this->sqlite->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');
        $this->sqlite->insert('t', ['id' => 1, 'v' => 'a']);
        $run = new ChunkedRun();

        try {
            $this->command()->process($this->sqlite, 't', ['id', 'v'], ['id'], 5, static function () use ($run): never {
                $run->add('done');

                throw new RuntimeException('encryptor failure');
            }, $run);
            $this->fail('The failure must be rethrown');
        } catch (RuntimeException $e) {
            $this->assertSame('encryptor failure', $e->getMessage());
        }
        $run->commitAttempt(0);
        $this->assertSame(0, $run->total('done'));
    }

    /**
     * @return iterable<string, array{AbstractPlatform, string, list<string>}>
     */
    public static function platformProvider(): iterable
    {
        yield 'MySQL: FOR UPDATE, session timeout set and restored' => [
            new MySQLPlatform(),
            'SELECT `id`, `v` FROM `t` WHERE `id` IN (?, ?) ORDER BY `id` FOR UPDATE',
            ['SELECT @@SESSION.innodb_lock_wait_timeout', 'SET SESSION innodb_lock_wait_timeout = 7', 'SET SESSION innodb_lock_wait_timeout = 50'],
        ];
        yield 'PostgreSQL: FOR UPDATE, SET LOCAL lock_timeout' => [
            new PostgreSQLPlatform(),
            'SELECT "id", "v" FROM "t" WHERE "id" IN (?, ?) ORDER BY "id" FOR UPDATE',
            ["SET LOCAL lock_timeout = '7s'"],
        ];
        yield 'SQL Server: UPDLOCK, ROWLOCK hint' => [
            new SQLServerPlatform(),
            'SELECT [id], [v] FROM [t] WITH (UPDLOCK, ROWLOCK) WHERE [id] IN (?, ?) ORDER BY [id]',
            [],
        ];
    }

    /**
     * @param list<string> $expectedStatements
     */
    #[DataProvider('platformProvider')]
    public function testLockedSelectAndLockTimeoutPerPlatform(AbstractPlatform $platform, string $expectedSelect, array $expectedStatements): void
    {
        [$conn, $queries, $statements] = $this->recordingConnection($platform, '50', true);
        $quote                         = $platform->quoteIdentifier(...);

        $this->command()->process($conn, $quote('t'), [$quote('id'), $quote('v')], [$quote('id')], 5, static function (): void {
        }, new ChunkedRun(7));

        $this->assertContains($expectedSelect, $queries->getArrayCopy());
        $this->assertSame($expectedStatements, array_values(array_filter($statements->getArrayCopy(), static fn (string $s): bool => str_contains($s, 'timeout'))));
    }

    public function testMySqlTimeoutIsNotRestoredWhenUnknownOrDisconnected(): void
    {
        foreach ([[false, true], ['50', false]] as [$previous, $connected]) {
            [$conn, , $statements] = $this->recordingConnection(new MySQLPlatform(), $previous, $connected);
            $this->command()->process($conn, '`t`', ['`id`'], ['`id`'], 5, static function (): void {
            }, new ChunkedRun(3));
            $this->assertSame(['SELECT @@SESSION.innodb_lock_wait_timeout', 'SET SESSION innodb_lock_wait_timeout = 3'], $statements->getArrayCopy());
        }
    }

    public function testZeroLockTimeoutKeepsTheServerSetting(): void
    {
        foreach ([new MySQLPlatform(), new PostgreSQLPlatform()] as $platform) {
            [$conn, , $statements] = $this->recordingConnection($platform, '50', true);
            $this->command()->process($conn, 't', ['id'], ['id'], 5, static function (): void {
            }, new ChunkedRun(0));
            $this->assertSame([], $statements->getArrayCopy());
        }
    }

    public function testCompositeLockedSelectMatchesEachIdentifierPair(): void
    {
        [$conn, $queries] = $this->recordingConnection(new PostgreSQLPlatform(), '0', true, [[1, 2], [3, 4]]);
        $this->command()->process($conn, 't', ['a', 'b', 'v'], ['a', 'b'], 5, static function (): void {
        }, new ChunkedRun(0));

        $this->assertContains('SELECT a, b, v FROM t WHERE (a = ? AND b = ?) OR (a = ? AND b = ?) ORDER BY a, b FOR UPDATE', $queries->getArrayCopy());
    }

    public function testResolveLockTimeout(): void
    {
        $command = $this->command();
        $this->assertSame(ChunkedRun::DEFAULT_LOCK_TIMEOUT_SECONDS, $command->lockTimeout(new ArrayInput([])), 'No option: the default');

        $definition = new InputDefinition([new InputOption('lock-timeout', null, InputOption::VALUE_REQUIRED)]);
        $this->assertSame(ChunkedRun::DEFAULT_LOCK_TIMEOUT_SECONDS, $command->lockTimeout(new ArrayInput([], $definition)));
        $this->assertSame(0, $command->lockTimeout(new ArrayInput(['--lock-timeout' => '0'], $definition)));
        $this->assertSame(30, $command->lockTimeout(new ArrayInput(['--lock-timeout' => '30'], $definition)));
        $this->assertSame(4, $command->lockTimeout(new ArrayInput(['--lock-timeout' => 4], $definition)));
        $this->assertNull($command->lockTimeout(new ArrayInput(['--lock-timeout' => '-1'], $definition)));
        $this->assertNull($command->lockTimeout(new ArrayInput(['--lock-timeout' => 'abc'], $definition)));
    }

    public function testReportChunkConflict(): void
    {
        $output = new BufferedOutput();
        $this->command()->report($output, ChunkLockConflictException::afterAttempts('"t"', 3, false, new RuntimeException()));
        $this->assertStringContainsString('[CONFLICT_001]', $output->fetch());

        $this->command()->report($output, ChunkLockConflictException::afterAttempts('"t"', 3, true, new RuntimeException()));
        $text = $output->fetch();
        $this->assertStringContainsString('[CONFLICT_002]', $text);
        $this->assertStringContainsString('Run the command again to resume', $text);
    }

    private function deadlock(): DeadlockException
    {
        $driver = new class('Deadlock found', 1213) extends Exception implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '40001';
            }
        };

        return new DeadlockException($driver, null);
    }

    /**
     * @param list<list<mixed>> $ids
     *
     * @return array{Connection, ArrayObject<int, string>, ArrayObject<int, string>}
     */
    private function recordingConnection(AbstractPlatform $platform, mixed $previousTimeout, bool $connected, array $ids = [[1], [2]]): array
    {
        /** @var ArrayObject<int, string> $queries */
        $queries = new ArrayObject();
        /** @var ArrayObject<int, string> $statements */
        $statements = new ArrayObject();
        $conn       = $this->createMock(Connection::class);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->method('isConnected')->willReturn($connected);
        $conn->method('fetchOne')->willReturnCallback(static function (string $sql) use ($queries, $statements, $previousTimeout): mixed {
            $statements[] = $sql;
            $queries[]    = $sql;

            return $previousTimeout;
        });
        $conn->method('executeStatement')->willReturnCallback(static function (string $sql) use ($statements): int {
            $statements[] = $sql;

            return 0;
        });
        $conn->method('executeQuery')->willReturnCallback(function (string $sql) use ($queries, $ids): Result {
            $queries[] = $sql;
            $result    = $this->createMock(Result::class);
            $result->method('fetchAllNumeric')->willReturn($ids);
            $result->method('fetchAllAssociative')->willReturn([]);

            return $result;
        });

        return [$conn, $queries, $statements];
    }

    private function command(): ChunkProcessingCommand
    {
        return new ChunkProcessingCommand($this->createMock(EntityManagerInterface::class), new AttributeReader(), new DoctrineEncryptSubscriber());
    }
}
