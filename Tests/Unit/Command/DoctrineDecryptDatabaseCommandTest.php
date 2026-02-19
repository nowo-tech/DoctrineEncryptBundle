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

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('entities found', $tester->getDisplay());
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

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('2 entities found', $tester->getDisplay());
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

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);

        $tester->execute(['encryptor' => 'NonExistentEncryptor']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Given encryptor does not exists', $tester->getDisplay());
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

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Decryption finished', $tester->getDisplay());
        $this->assertStringContainsString('Processing', $tester->getDisplay());
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

        $dummy = new DummyEncryptorForCommand();
        $registry = new EncryptorRegistry(['default' => $dummy], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $command = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber);
        $this->createCommandWithApplication($command);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute(['encryptor' => DummyEncryptorForCommand::class], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCommandDefinitionHasEncryptorAndBatchSizeArguments(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));
        $command = new DoctrineDecryptDatabaseCommand(
            $em,
            new AttributeReader(),
            new DoctrineEncryptSubscriber(new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default'))
        );
        $def = $command->getDefinition();
        $this->assertTrue($def->hasArgument('encryptor'));
        $this->assertTrue($def->hasArgument('batchSize'));
    }
}
