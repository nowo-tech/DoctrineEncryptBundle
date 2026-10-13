<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Functional\SqlQueryCollector;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function count;

/**
 * doctrine:encrypt:database / doctrine:decrypt:database must read the table in batchSize chunks
 * (never the whole table at once) and commit one transaction per chunk.
 */
final class DatabaseCommandBatchingTest extends TestCase
{
    private const MARKER = DoctrineEncryptSubscriber::ENCRYPTION_MARKER;

    private Connection $conn;

    private SqlQueryCollector $queries;

    protected function setUp(): void
    {
        $this->queries = new SqlQueryCollector();
        $config        = new Configuration();
        $config->setMiddlewares([new Middleware($this->queries)]);
        $this->conn = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->conn->executeStatement('CREATE TABLE "user" (id INTEGER PRIMARY KEY, name TEXT NULL, address TEXT NULL)');
    }

    public function testEncryptProcessesTableInBatchesWithOneTransactionPerBatch(): void
    {
        $this->insertRows(7, static fn (int $i): string => 'plain' . $i);

        $tester = $this->runCommand($this->createEncryptCommand($this->prefixEncryptor()), ['config' => 'default', 'batchSize' => '3']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Values encrypted: 7 values', $tester->getDisplay());
        $this->assertSame(3, $this->countLimitedSelects(3), 'ceil(7 / 3) = 3 chunked SELECTs with LIMIT 3');
        $this->assertSame(0, $this->countQueries(static fn (string $sql): bool => str_starts_with($sql, 'SELECT') && str_contains($sql, 'FROM "user"') && !str_contains($sql, 'LIMIT') && !str_contains($sql, 'COUNT(*)')), 'The whole table must never be selected without LIMIT');
        $this->assertSame(3, $this->countMessages('Committing transaction'));
        foreach ($this->names() as $i => $name) {
            $this->assertSame('enc:plain' . $i . self::MARKER, $name);
        }
    }

    public function testDecryptProcessesTableInBatchesWithOneTransactionPerBatch(): void
    {
        $this->insertRows(5, static fn (int $i): string => 'enc:plain' . $i . self::MARKER);

        $tester = $this->runCommand($this->createDecryptCommand($this->prefixEncryptor()), ['config' => 'default', 'batchSize' => '2']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Values decrypted: 5', $tester->getDisplay());
        foreach ($this->names() as $i => $name) {
            $this->assertSame('plain' . $i, $name);
        }
        $this->assertSame(3, $this->countLimitedSelects(2));
        $this->assertSame(3, $this->countMessages('Committing transaction'));
    }

    public function testConfiguredDefaultBatchSizeIsUsedWhenArgumentIsOmitted(): void
    {
        $this->insertRows(4, static fn (int $i): string => 'plain' . $i);

        $command = new DoctrineEncryptDatabaseCommand($this->createEntityManager(), new AttributeReader(), new DoctrineEncryptSubscriber(), null, $this->registry($this->prefixEncryptor()), 2);
        $tester  = $this->runCommand($command, ['config' => 'default']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        // 4 rows / 2: two full chunks + one empty read that ends the loop.
        $this->assertSame(3, $this->countLimitedSelects(2));
    }

    public function testFailingBatchIsRolledBackAndPreviousBatchesStayCommitted(): void
    {
        $this->insertRows(5, static fn (int $i): string => 'plain' . $i);
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static function (string $data): string {
            if ($data === 'plain4') {
                throw new RuntimeException('encryptor failure');
            }

            return 'enc:' . $data;
        });

        try {
            $this->runCommand($this->createEncryptCommand($encryptor), ['config' => 'default', 'batchSize' => '2']);
            $this->fail('The encryptor exception must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('encryptor failure', $e->getMessage());
        }

        $names = $this->names();
        $this->assertSame('enc:plain1' . self::MARKER, $names[1]);
        $this->assertSame('enc:plain2' . self::MARKER, $names[2]);
        $this->assertSame('plain3', $names[3], 'Row of the failed batch must be rolled back');
        $this->assertSame('plain4', $names[4]);
        $this->assertSame('plain5', $names[5]);
        $this->assertSame(1, $this->countMessages('Rolling back transaction'));
        $this->assertFalse($this->conn->isTransactionActive());
    }

    public function testNonDecryptableValueIsPreservedReportedAndNotDoubleEncrypted(): void
    {
        $this->insertRows(3, static fn (int $i): string => ($i === 2 ? 'other-key:plain2' : 'enc:plain' . $i) . self::MARKER);
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static fn (string $d): string => 'enc:' . $d);
        $encryptor->method('decrypt')->willReturnCallback(static function (string $d): string {
            if (!str_starts_with($d, 'enc:')) {
                throw new RuntimeException('Invalid version tag');
            }

            return substr($d, 4);
        });

        $tester = $this->runCommand($this->createDecryptCommand($encryptor), ['config' => 'default', 'batchSize' => '2']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('1 value(s) could not be decrypted and were left unchanged', $tester->getDisplay());
        $this->assertStringContainsString('user.name (id=2) [RuntimeException]', $tester->getDisplay());
        $this->assertStringContainsString('Values decrypted: 2', $tester->getDisplay());
        $names = $this->names();
        $this->assertSame('plain1', $names[1]);
        $this->assertSame('other-key:plain2' . self::MARKER, $names[2], 'Non-decryptable value must be stored unchanged, marker included');
        $this->assertSame('plain3', $names[3]);

        // A later encrypt run must not encrypt the (still marked) ciphertext again.
        $tester = $this->runCommand($this->createEncryptCommand($encryptor), ['config' => 'default']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $names = $this->names();
        $this->assertSame('other-key:plain2' . self::MARKER, $names[2]);
        $this->assertSame('enc:plain1' . self::MARKER, $names[1]);
        $this->assertSame('enc:plain3' . self::MARKER, $names[3]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBatchSizeProvider(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'not a number' => ['abc'];
    }

    #[DataProvider('invalidBatchSizeProvider')]
    public function testInvalidBatchSizeIsRejected(string $batchSize): void
    {
        $this->insertRows(1, static fn (int $i): string => 'plain' . $i);

        foreach ([$this->createEncryptCommand($this->prefixEncryptor()), $this->createDecryptCommand($this->prefixEncryptor())] as $command) {
            $tester = $this->runCommand($command, ['config' => 'default', 'batchSize' => $batchSize]);

            $this->assertSame(Command::FAILURE, $tester->getStatusCode());
            $this->assertStringContainsString('batchSize argument must be a positive integer', $tester->getDisplay());
        }
        $this->assertSame('plain1', $this->names()[1]);
    }

    /**
     * @param callable(int): string $name
     */
    private function insertRows(int $count, callable $name): void
    {
        for ($i = 1; $i <= $count; ++$i) {
            $this->conn->insert('"user"', ['id' => $i, 'name' => $name($i), 'address' => null]);
        }
        $this->queries->queries = [];
    }

    /**
     * @return array<int, string>
     */
    private function names(): array
    {
        $names = [];
        foreach ($this->conn->fetchAllAssociative('SELECT id, name FROM "user" ORDER BY id') as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    private function countLimitedSelects(int $limit): int
    {
        return $this->countQueries(static fn (string $sql): bool => str_starts_with($sql, 'SELECT') && str_contains($sql, 'ORDER BY "id"') && str_contains($sql, 'LIMIT ' . $limit));
    }

    /**
     * @param callable(string): bool $filter
     */
    private function countQueries(callable $filter): int
    {
        return count(array_filter($this->queries->queries, static fn (array $q): bool => $filter((string) $q['sql'])));
    }

    private function countMessages(string $message): int
    {
        return count(array_filter($this->queries->queries, static fn (array $q): bool => str_contains((string) $q['sql'], $message)));
    }

    private function prefixEncryptor(): EncryptorInterface
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static fn (string $d): string => 'enc:' . $d);
        $encryptor->method('decrypt')->willReturnCallback(static fn (string $d): string => substr($d, 4));

        return $encryptor;
    }

    private function registry(EncryptorInterface $encryptor): EncryptorRegistry
    {
        return new EncryptorRegistry(['default' => $encryptor], 'default');
    }

    private function createEncryptCommand(EncryptorInterface $encryptor): DoctrineEncryptDatabaseCommand
    {
        return new DoctrineEncryptDatabaseCommand($this->createEntityManager(), new AttributeReader(), new DoctrineEncryptSubscriber(), null, $this->registry($encryptor));
    }

    private function createDecryptCommand(EncryptorInterface $encryptor): DoctrineDecryptDatabaseCommand
    {
        return new DoctrineDecryptDatabaseCommand($this->createEntityManager(), new AttributeReader(), new DoctrineEncryptSubscriber(), null, $this->registry($encryptor));
    }

    /**
     * @param array<string, string> $input
     */
    private function runCommand(Command $command, array $input): CommandTester
    {
        $command->setApplication(new Application());
        $tester = new CommandTester($command);
        $tester->execute($input + ['--force' => true], ['interactive' => false]);

        return $tester;
    }

    private function createEntityManager(): EntityManagerInterface
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
