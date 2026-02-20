<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures\DummyEncryptorForCommand;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DoctrineDecryptDatabaseCommandTest extends TestCase
{
    private function createCommandWithApplication(DoctrineDecryptDatabaseCommand $command): DoctrineDecryptDatabaseCommand
    {
        $application = new Application();
        $command->setApplication($application);
        return $command;
    }

    public function testExecuteExitsWithoutDecryptingWhenUserDeclinesConfirmation(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('config(s)', $tester->getDisplay());
    }

    public function testExecuteSkipsMappedSuperclassWhenCountingProperties(): void
    {
        $mappedSuper = new \stdClass();
        $mappedSuper->name = 'BaseEntity';
        $mappedSuper->isMappedSuperclass = true;

        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$mappedSuper, $metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('encrypted properties', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenNoEncryptorConfigured(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $subscriber = new DoctrineEncryptSubscriber(null);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, null);
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

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);

        $tester->execute(['config' => 'NonExistentConfig']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown config', $tester->getDisplay());
    }

    public function testExecuteRunsDecryptionLoopWhenUserConfirms(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $user = new User('u', 'a');
        $querySelect = $this->createMock(Query::class);
        $querySelect->method('toIterable')->willReturn(new \ArrayIterator([[$user]]));
        $queryCount = $this->createMock(Query::class);
        $queryCount->method('getSingleScalarResult')->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('createQuery')
            ->willReturnCallback(function (string $dql) use ($querySelect, $queryCount) {
                return str_contains($dql, 'COUNT') ? $queryCount : $querySelect;
            });
        $em->expects($this->atLeastOnce())->method('flush');
        $em->expects($this->atLeastOnce())->method('clear');

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Decryption finished', $tester->getDisplay());
        $this->assertStringContainsString('Processing', $tester->getDisplay());
    }

    public function testExecuteAcceptsConfigName(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $dummy = new DummyEncryptorForCommand();
        $registry = new EncryptorRegistry(['default' => $dummy], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute(['config' => 'default'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCommandDefinitionHasConfigAndBatchSizeArguments(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $command = new DoctrineDecryptDatabaseCommand(
            $em,
            new AttributeReader(),
            new DoctrineEncryptSubscriber($registry),
            null,
            $registry
        );
        $def = $command->getDefinition();
        $this->assertTrue($def->hasArgument('config'));
        $this->assertTrue($def->hasArgument('batchSize'));
    }

    public function testExecuteUsesCustomBatchSizeWhenProvided(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $querySelect = $this->createMock(Query::class);
        $querySelect->method('toIterable')->willReturn(new \ArrayIterator([]));
        $queryCount = $this->createMock(Query::class);
        $queryCount->method('getSingleScalarResult')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('createQuery')
            ->willReturnCallback(function (string $dql) use ($querySelect, $queryCount) {
                return str_contains($dql, 'COUNT') ? $queryCount : $querySelect;
            });

        $registry = new EncryptorRegistry(['default' => new DummyEncryptorForCommand()], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute(['config' => 'default', 'batchSize' => '50'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Decryption finished', $tester->getDisplay());
    }

    public function testExecuteWhenMultipleConfigsShowsAllInConfirmation(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry([
            'default' => $encryptor,
            'personal_data' => $encryptor,
            'financial_data' => $encryptor,
        ], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
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
        $em = $this->createMock(EntityManagerInterface::class);
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);

        $def = $command->getDefinition();
        $this->assertTrue($def->hasArgument('config'));
        $this->assertTrue($def->hasArgument('batchSize'));
        $this->assertSame(20, $def->getArgument('batchSize')->getDefault());
    }
}
