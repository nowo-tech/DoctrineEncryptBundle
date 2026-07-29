<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use ArrayIterator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\Query;
use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\EntityWithConfigAlias;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractCommandTest extends TestCase
{
    public function testGetEncryptionableEntityMetaDataReturnsOnlyEntitiesWithEncryptedProperties(): void
    {
        $metadataWithEncrypted                     = new stdClass();
        $metadataWithEncrypted->name               = User::class;
        $metadataWithEncrypted->isMappedSuperclass = false;

        $metadataPlain                     = new stdClass();
        $metadataPlain->name               = stdClass::class;
        $metadataPlain->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataWithEncrypted, $metadataPlain]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return list<object{name: class-string, isMappedSuperclass: bool}> */
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
        $metadataSuper                     = new stdClass();
        $metadataSuper->name               = User::class;
        $metadataSuper->isMappedSuperclass = true;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataSuper]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return list<object{name: class-string, isMappedSuperclass: bool}> */
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
        $query->method('toIterable')->willReturn(new ArrayIterator([]));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('createQuery')
            ->with('SELECT o FROM ' . User::class . ' o')
            ->willReturn($query);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return iterable<object> */
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

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
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
        $metadata                     = new stdClass();
        $metadata->name               = EntityWithConfigAlias::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return array<ReflectionProperty> */
            public function exposeGetEncryptionablePropertiesForConfig(object $entityMetaData, string $configName, string $defaultConfigName): array
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
        $metadataUser                     = new stdClass();
        $metadataUser->name               = User::class;
        $metadataUser->isMappedSuperclass = false;

        $metadataEntityWithAlias                     = new stdClass();
        $metadataEntityWithAlias->name               = EntityWithConfigAlias::class;
        $metadataEntityWithAlias->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataUser, $metadataEntityWithAlias]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return list<object{name: class-string, isMappedSuperclass: bool}> */
            public function exposeGetEncryptionableEntityMetaDataForConfig(string $configName, string $defaultConfigName): array
            {
                return $this->getEncryptionableEntityMetaDataForConfig($configName, $defaultConfigName);
            }
        };

        $forDefault = $command->exposeGetEncryptionableEntityMetaDataForConfig('default', 'default');
        $this->assertCount(2, $forDefault);
        $names = array_map(static fn ($m) => $m->name, $forDefault);
        $this->assertContains(User::class, $names);
        $this->assertContains(EntityWithConfigAlias::class, $names);

        $forOther = $command->exposeGetEncryptionableEntityMetaDataForConfig('other_config', 'default');
        $this->assertCount(1, $forOther);
        $this->assertSame(EntityWithConfigAlias::class, $forOther[0]->name);
    }

    public function testGetEncryptionablePropertiesReturnsOnlyPropertiesWithEncryptedAttribute(): void
    {
        $metadata                     = new stdClass();
        $metadata->name               = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return array<ReflectionProperty> */
            public function exposeGetEncryptionableProperties(object $entityMetaData): array
            {
                return $this->getEncryptionableProperties($entityMetaData);
            }
        };

        $properties = $command->exposeGetEncryptionableProperties($metadata);
        $this->assertCount(2, $properties);
        $names = array_map(static fn (ReflectionProperty $p): string => $p->getName(), $properties);
        $this->assertContains('name', $names);
        $this->assertContains('address', $names);

        $metadataPlain                     = new stdClass();
        $metadataPlain->name               = stdClass::class;
        $metadataPlain->isMappedSuperclass = false;
        $propertiesPlain                   = $command->exposeGetEncryptionableProperties($metadataPlain);
        $this->assertCount(0, $propertiesPlain);
    }

    public function testGetEncryptedTableInfoReturnsTableIdColumnsAndEncryptedColumns(): void
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

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return array{table: string, idColumns: array<int, string>, columns: array<int, array{field: string, column: string}>} */
            public function exposeGetEncryptedTableInfo(object $entityMetaData, string $configName, string $defaultConfigName): array
            {
                return $this->getEncryptedTableInfo($entityMetaData, $configName, $defaultConfigName);
            }
        };

        $info = $command->exposeGetEncryptedTableInfo($metadata, 'default', 'default');
        $this->assertSame('user', $info['table']);
        $this->assertSame(['id'], $info['idColumns']);
        $this->assertCount(2, $info['columns']);
        $columns = array_column($info['columns'], 'column', 'field');
        $this->assertSame('name', $columns['name']);
        $this->assertSame('address', $columns['address']);
    }

    public function testGetEncryptedTableInfoUsesGetIdentifierColumnNamesWhenGetIdentifierFieldNamesNotPresent(): void
    {
        $metadata = new class {
            public string $name             = User::class;
            public bool $isMappedSuperclass = false;

            public function getTableName(): string
            {
                return 'tbl_user';
            }

            /** @return list<string> */
            public function getIdentifierColumnNames(): array
            {
                return ['id_col'];
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

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @return array{table: string, idColumns: array<int, string>, columns: array<int, array{field: string, column: string}>} */
            public function exposeGetEncryptedTableInfo(object $entityMetaData, string $configName, string $defaultConfigName): array
            {
                return $this->getEncryptedTableInfo($entityMetaData, $configName, $defaultConfigName);
            }
        };

        $info = $command->exposeGetEncryptedTableInfo($metadata, 'default', 'default');
        $this->assertSame('tbl_user', $info['table']);
        $this->assertSame(['id_col'], $info['idColumns']);
    }

    public function testGetColumnNameFromMetadataUsesGetFieldMappingWhenGetColumnNameNotPresent(): void
    {
        $metadata = new class {
            public string $name = User::class;

            /** @return array<string, mixed> */
            public function getFieldMapping(string $fieldName): array
            {
                return ['columnName' => 'mapped_' . $fieldName];
            }
        };

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetColumnNameFromMetadata(object $entityMetaData, string $fieldName): string
            {
                return $this->getColumnNameFromMetadata($entityMetaData, $fieldName);
            }
        };

        $this->assertSame('mapped_name', $command->exposeGetColumnNameFromMetadata($metadata, 'name'));
    }

    public function testGetColumnNameFromMetadataUsesFieldNameWhenMappingHasNoColumnName(): void
    {
        $metadata = new class {
            /** @return array<string, mixed> */
            public function getFieldMapping(string $fieldName): array
            {
                return [];
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function exposeGetColumnNameFromMetadata(object $entityMetaData, string $fieldName): string
            {
                return $this->getColumnNameFromMetadata($entityMetaData, $fieldName);
            }
        };

        $this->assertSame('name', $command->exposeGetColumnNameFromMetadata($metadata, 'name'));
    }

    public function testGetRowValueReturnsValueByExactKey(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @param array<string, mixed> $row */
            public function exposeGetRowValue(array $row, string $columnName): mixed
            {
                return $this->getRowValue($row, $columnName);
            }
        };

        $row = ['id' => 1, 'name' => 'Alice'];
        $this->assertSame(1, $command->exposeGetRowValue($row, 'id'));
        $this->assertSame('Alice', $command->exposeGetRowValue($row, 'name'));
    }

    public function testGetRowValueReturnsValueByCaseInsensitiveKey(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @param array<string, mixed> $row */
            public function exposeGetRowValue(array $row, string $columnName): mixed
            {
                return $this->getRowValue($row, $columnName);
            }
        };

        $row = ['ID' => 42, 'Name' => 'Bob'];
        $this->assertSame(42, $command->exposeGetRowValue($row, 'id'));
        $this->assertSame('Bob', $command->exposeGetRowValue($row, 'name'));
    }

    public function testGetRowValueReturnsNullWhenColumnMissing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($this->createMock(ClassMetadataFactory::class));

        $command = new class($em, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class)) extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            /** @param array<string, mixed> $row */
            public function exposeGetRowValue(array $row, string $columnName): mixed
            {
                return $this->getRowValue($row, $columnName);
            }
        };

        $this->assertNull($command->exposeGetRowValue(['a' => 1], 'missing'));
    }
}
