<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractCommandTest extends TestCase
{
    public function testGetEncryptionableEntityMetaDataReturnsOnlyEntitiesWithEncryptedProperties(): void
    {
        $metadataWithEncrypted = new \stdClass();
        $metadataWithEncrypted->name = User::class;
        $metadataWithEncrypted->isMappedSuperclass = false;

        $metadataPlain = new \stdClass();
        $metadataPlain->name = \stdClass::class;
        $metadataPlain->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataWithEncrypted, $metadataPlain]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetEncryptionableEntityMetaData(): array
            {
                return $this->getEncryptionableEntityMetaData();
            }
        };

        $metaDataArray = $command->exposeGetEncryptionableEntityMetaData();

        $this->assertCount(1, $metaDataArray);
        $this->assertSame(User::class, $metaDataArray[0]->name);
    }

    public function testGetEncryptionableEntityMetaDataSkipsMappedSuperclass(): void
    {
        $metadataSuper = new \stdClass();
        $metadataSuper->name = User::class;
        $metadataSuper->isMappedSuperclass = true;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataSuper]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetEncryptionableEntityMetaData(): array
            {
                return $this->getEncryptionableEntityMetaData();
            }
        };

        $metaDataArray = $command->exposeGetEncryptionableEntityMetaData();

        $this->assertCount(0, $metaDataArray);
    }

    public function testGetEntityIteratorReturnsIterable(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn(new \ArrayIterator([]));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('createQuery')
            ->with('SELECT o FROM ' . User::class . ' o')
            ->willReturn($query);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetEntityIterator(string $entityName): iterable
            {
                return $this->getEntityIterator($entityName);
            }
        };

        $iterator = $command->exposeGetEntityIterator(User::class);

        $this->assertIsIterable($iterator);
    }

    public function testGetTableCountReturnsCount(): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(42);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('createQuery')
            ->with('SELECT COUNT(o) FROM ' . User::class . ' o')
            ->willReturn($query);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetTableCount(string $entityName): int
            {
                return $this->getTableCount($entityName);
            }
        };

        $count = $command->exposeGetTableCount(User::class);

        $this->assertSame(42, $count);
    }
}
