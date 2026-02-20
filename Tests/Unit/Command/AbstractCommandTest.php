<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\EntityWithConfigAlias;
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

    public function testGetEncryptionablePropertiesForConfigReturnsOnlyPropertiesForGivenConfig(): void
    {
        $metadata = new \stdClass();
        $metadata->name = EntityWithConfigAlias::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return array<\ReflectionProperty> */
            public function exposeGetEncryptionablePropertiesForConfig($entityMetaData, string $configName, string $defaultConfigName): array
            {
                return $this->getEncryptionablePropertiesForConfig($entityMetaData, $configName, $defaultConfigName);
            }
        };

        $defaultOnly = $command->exposeGetEncryptionablePropertiesForConfig($metadata, 'default', 'default');
        $this->assertCount(1, $defaultOnly);
        $this->assertSame('defaultField', $defaultOnly[0]->getName());

        $otherOnly = $command->exposeGetEncryptionablePropertiesForConfig($metadata, 'other_config', 'default');
        $this->assertCount(1, $otherOnly);
        $this->assertSame('otherField', $otherOnly[0]->getName());

        $none = $command->exposeGetEncryptionablePropertiesForConfig($metadata, 'nonexistent', 'default');
        $this->assertCount(0, $none);
    }

    public function testGetEncryptionableEntityMetaDataForConfigReturnsOnlyEntitiesWithPropertiesForConfig(): void
    {
        $metadataUser = new \stdClass();
        $metadataUser->name = User::class;
        $metadataUser->isMappedSuperclass = false;

        $metadataEntityWithAlias = new \stdClass();
        $metadataEntityWithAlias->name = EntityWithConfigAlias::class;
        $metadataEntityWithAlias->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataUser, $metadataEntityWithAlias]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetEncryptionableEntityMetaDataForConfig(string $configName, string $defaultConfigName): array
            {
                return $this->getEncryptionableEntityMetaDataForConfig($configName, $defaultConfigName);
            }
        };

        $forDefault = $command->exposeGetEncryptionableEntityMetaDataForConfig('default', 'default');
        $this->assertCount(2, $forDefault);
        $names = array_map(fn ($m) => $m->name, $forDefault);
        $this->assertContains(User::class, $names);
        $this->assertContains(EntityWithConfigAlias::class, $names);

        $forOther = $command->exposeGetEncryptionableEntityMetaDataForConfig('other_config', 'default');
        $this->assertCount(1, $forOther);
        $this->assertSame(EntityWithConfigAlias::class, $forOther[0]->name);
    }

    public function testGetEncryptionablePropertiesReturnsOnlyPropertiesWithEncryptedAttribute(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class ($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return array<\ReflectionProperty> */
            public function exposeGetEncryptionableProperties($entityMetaData): array
            {
                return $this->getEncryptionableProperties($entityMetaData);
            }
        };

        $properties = $command->exposeGetEncryptionableProperties($metadata);
        $this->assertCount(2, $properties);
        $names = array_map(fn ($p) => $p->getName(), $properties);
        $this->assertContains('name', $names);
        $this->assertContains('address', $names);

        $metadataPlain = new \stdClass();
        $metadataPlain->name = \stdClass::class;
        $metadataPlain->isMappedSuperclass = false;
        $propertiesPlain = $command->exposeGetEncryptionableProperties($metadataPlain);
        $this->assertCount(0, $propertiesPlain);
    }
}
