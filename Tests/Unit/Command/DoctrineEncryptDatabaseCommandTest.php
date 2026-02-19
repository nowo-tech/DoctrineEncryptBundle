<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures\DummyEncryptorForCommand;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class DoctrineEncryptDatabaseCommandTest extends TestCase
{
    private function createCommandWithApplication(DoctrineEncryptDatabaseCommand $command): DoctrineEncryptDatabaseCommand
    {
        $application = new Application();
        $command->setApplication($application);
        return $command;
    }

    public function testExecuteExitsWithoutEncryptingWhenUserDeclinesConfirmation(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('entities found', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenGivenEncryptorDoesNotExist(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $encryptor = $this->createMock(EncryptorInterface::class);
        $registry = new EncryptorRegistry(['default' => $encryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);

        $tester->execute(['encryptor' => 'NonExistentEncryptor']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Given encryptor does not exists', $tester->getDisplay());
        $this->assertStringContainsString('Supported encryptors', $tester->getDisplay());
    }

    public function testExecuteAcceptsCustomEncryptorClassWhenClassExists(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $subscriber = new DoctrineEncryptSubscriber(new EncryptorRegistry(['default' => new DummyEncryptorForCommand()], 'default'));

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute(['encryptor' => DummyEncryptorForCommand::class], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteRunsEncryptionLoopWhenUserConfirms(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $querySelect = $this->createMock(Query::class);
        $querySelect->method('toIterable')->willReturn(new \ArrayIterator([[new User('u', 'a')]]));
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

        $command = new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Encryption finished', $tester->getDisplay());
        $this->assertStringContainsString('Processing', $tester->getDisplay());
    }

    public function testCommandDefinitionHasEncryptorAndBatchSizeArguments(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));
        $command = new DoctrineEncryptDatabaseCommand(
            $em,
            new AttributeReader(),
            new DoctrineEncryptSubscriber(new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default'))
        );
        $def = $command->getDefinition();
        $this->assertTrue($def->hasArgument('encryptor'));
        $this->assertTrue($def->hasArgument('batchSize'));
    }
}
