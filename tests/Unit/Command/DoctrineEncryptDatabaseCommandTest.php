<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures\DummyEncryptorForCommand;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

use function count;

class DoctrineEncryptDatabaseCommandTest extends TestCase
{
    /**
     * Creates a metadata-like object for commands that use raw SQL (getEncryptedTableInfo).
     * Must have getTableName(), getIdentifierFieldNames(), getColumnName() and ->name, ->isMappedSuperclass.
     */
    private function createMetadataForUser(): object
    {
        return new class {
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
    }

    private function createConnectionMock(array $rows = []): Connection
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn($rows);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnCallback(static fn (string $s): string => '"' . $s . '"');
        $conn = $this->createMock(Connection::class);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->method('executeQuery')->willReturn($result);

        return $conn;
    }

    private function createCommandWithApplication(DoctrineEncryptDatabaseCommand $command): DoctrineEncryptDatabaseCommand
    {
        $application = new Application();
        $command->setApplication($application);

        return $command;
    }

    public function testExecuteExitsWithoutEncryptingWhenUserDeclinesConfirmation(): void
    {
        $metadata                     = new stdClass();
        $metadata->name               = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor  = $this->createMock(EncryptorInterface::class);
        $registry   = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('entity(ies)', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenNoEncryptorConfigured(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $subscriber = new DoctrineEncryptSubscriber(null);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, null);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No encryptor configured', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenGivenConfigDoesNotExist(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor  = $this->createMock(EncryptorInterface::class);
        $registry   = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);

        $tester->execute(['config' => 'NonExistentConfig']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown config', $tester->getDisplay());
        $this->assertStringContainsString('Available', $tester->getDisplay());
    }

    public function testExecuteAcceptsConfigName(): void
    {
        $metadata                     = new stdClass();
        $metadata->name               = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $registry   = new EncryptorRegistry(['default' => new DummyEncryptorForCommand()], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute(['config' => 'default'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteRunsEncryptionLoopWhenUserConfirms(): void
    {
        $metadata        = $this->createMetadataForUser();
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($this->createConnectionMock());

        $encryptor  = $this->createMock(EncryptorInterface::class);
        $registry   = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Encryption finished', $tester->getDisplay());
        $this->assertStringContainsString('Processing', $tester->getDisplay());
    }

    public function testCommandDefinitionHasConfigAndBatchSizeArguments(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $command  = new DoctrineEncryptDatabaseCommand(
            $em,
            new AttributeReader(),
            new DoctrineEncryptSubscriber($registry),
            null,
            $registry,
        );
        $def = $command->getDefinition();
        $this->assertTrue($def->hasArgument('config'));
        $this->assertTrue($def->hasArgument('batchSize'));
    }

    public function testExecuteUsesCustomBatchSizeWhenProvided(): void
    {
        $metadata        = $this->createMetadataForUser();
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($this->createConnectionMock());

        $registry   = new EncryptorRegistry(['default' => new DummyEncryptorForCommand()], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute(['config' => 'default', 'batchSize' => '50'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWhenMultipleConfigsShowsAllInConfirmation(): void
    {
        $metadata                     = new stdClass();
        $metadata->name               = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry  = new EncryptorRegistry([
            'default'        => $encryptor,
            'personal_data'  => $encryptor,
            'financial_data' => $encryptor,
        ], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('personal_data', $display);
        $this->assertStringContainsString('financial_data', $display);
    }

    /** Asserts command definition (configure() is run from parent constructor when command is created). */
    public function testConfigureAddsArguments(): void
    {
        $em         = $this->createMock(EntityManagerInterface::class);
        $registry   = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $command    = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);

        $def = $command->getDefinition();
        $this->assertTrue($def->hasArgument('config'));
        $this->assertTrue($def->hasArgument('batchSize'));
        $this->assertSame(5, $def->getArgument('batchSize')->getDefault());
    }

    public function testExecuteEncryptLoopCallsExecuteStatementWhenRowHasPlainValue(): void
    {
        $metadata        = $this->createMetadataForUser();
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $rows = [['id' => 1, 'name' => 'plain_name', 'address' => null]];
        $conn = $this->createConnectionMock($rows);
        $conn->expects($this->once())->method('executeStatement')->with(
            $this->stringContains('UPDATE'),
            $this->callback(static function (array $params): bool {
                return count($params) >= 2 && str_ends_with((string) $params[0], DoctrineEncryptSubscriber::ENCRYPTION_MARKER);
            }),
            $this->anything(),
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($conn);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->with('plain_name')->willReturn('encrypted_value');
        $registry   = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute(['config' => 'default'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Values encrypted: 1 values', $tester->getDisplay());
    }

    public function testExecuteEncryptLoopSkipsRowWhenIdIsNull(): void
    {
        $metadata        = $this->createMetadataForUser();
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $rows = [['id' => null, 'name' => 'plain', 'address' => null]];
        $conn = $this->createConnectionMock($rows);
        $conn->expects($this->never())->method('executeStatement');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($conn);

        $encryptor  = $this->createMock(EncryptorInterface::class);
        $registry   = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute(['config' => 'default'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Values encrypted: 0', $tester->getDisplay());
    }
}
