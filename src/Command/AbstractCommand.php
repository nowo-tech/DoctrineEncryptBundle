<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;

use function array_key_exists;
use function count;
use function sprintf;

/**
 * Base class for bundle console commands.
 *
 * Provides shared helpers: entity iteration, encrypted property discovery, and config-filtered metadata.
 */
abstract class AbstractCommand extends Command
{
    /**
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param AttributeReader $attributeReader Reader for Encrypted attributes
     * @param DoctrineEncryptSubscriber $subscriber Encrypt/decrypt event subscriber
     * @param EncryptorInterface|null $defaultEncryptor Used by encrypt/decrypt database commands when using multi-config
     * @param EncryptorRegistry|null $encryptorRegistry Used by encrypt/decrypt database commands and status command to resolve config names
     */
    public function __construct(
        public EntityManagerInterface $entityManager,
        public AttributeReader $attributeReader,
        public DoctrineEncryptSubscriber $subscriber,
        public ?EncryptorInterface $defaultEncryptor = null,
        public ?EncryptorRegistry $encryptorRegistry = null
    ) {
        parent::__construct();
    }

    /**
     * Returns an iterable over all entities of the given class (for batch processing).
     *
     * @param string $entityName Fully qualified entity class name
     */
    protected function getEntityIterator(string $entityName): iterable
    {
        $query = $this->entityManager->createQuery(sprintf('SELECT o FROM %s o', $entityName));

        return $query->toIterable();
    }

    /**
     * Returns the number of records for the given entity.
     *
     * @param string $entityName Fully qualified entity class name
     */
    protected function getTableCount(string $entityName): int
    {
        $query = $this->entityManager->createQuery(sprintf('SELECT COUNT(o) FROM %s o', $entityName));

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Returns metadata for all entities that have at least one property marked with Encrypted.
     */
    protected function getEncryptionableEntityMetaData(): array
    {
        $validMetaData = [];
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metaDataArray as $entityMetaData) {
            if (isset($entityMetaData->isMappedSuperclass) && $entityMetaData->isMappedSuperclass) {
                continue;
            }

            $properties = $this->getEncryptionableProperties($entityMetaData);

            if (count($properties) === 0) {
                continue;
            }

            $validMetaData[] = $entityMetaData;
        }

        return $validMetaData;
    }

    /**
     * Returns properties of the entity that are marked with the Encrypted attribute.
     *
     * @param object $entityMetaData Doctrine entity metadata (ClassMetadata)
     *
     * @return array<ReflectionProperty>
     */
    protected function getEncryptionableProperties($entityMetaData): array
    {
        // Create reflectionClass for each meta data object
        $reflectionClass = new ReflectionClass($entityMetaData->name);
        $propertyArray   = $reflectionClass->getProperties();
        $properties      = [];
        foreach ($propertyArray as $property) {
            if ($this->attributeReader->getPropertyAnnotation($property, Encrypted::class)) {
                $properties[] = $property;
            }
        }

        // return properties
        return $properties;
    }

    /**
     * Returns encrypted properties with their effective config name (resolves "default" to $defaultConfigName).
     *
     * @param object $entityMetaData Doctrine entity metadata (ClassMetadata)
     * @param string $defaultConfigName Default config name (e.g. from EncryptorRegistry::getDefaultName())
     *
     * @return array<int, array{property: ReflectionProperty, config: string}>
     */
    protected function getEncryptionablePropertiesWithConfig($entityMetaData, string $defaultConfigName): array
    {
        $reflectionClass = new ReflectionClass($entityMetaData->name);
        $result          = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $annotation = $this->attributeReader->getPropertyAnnotation($property, Encrypted::class);
            if ($annotation instanceof Encrypted) {
                $config   = $annotation->config === 'default' ? $defaultConfigName : $annotation->config;
                $result[] = ['property' => $property, 'config' => $config];
            }
        }

        return $result;
    }

    /**
     * Returns properties marked with Encrypted that belong to the given config.
     * When a property has config "default", it is included if $defaultConfigName === $configName.
     *
     * @param object $entityMetaData Doctrine entity metadata
     * @param string $configName Config name (e.g. personal_data, financial_data)
     * @param string $defaultConfigName Registry default config name (for resolving Encrypted('default'))
     *
     * @return array<ReflectionProperty>
     */
    protected function getEncryptionablePropertiesForConfig($entityMetaData, string $configName, string $defaultConfigName): array
    {
        $reflectionClass = new ReflectionClass($entityMetaData->name);
        $propertyArray   = $reflectionClass->getProperties();
        $properties      = [];
        foreach ($propertyArray as $property) {
            $annotation = $this->attributeReader->getPropertyAnnotation($property, Encrypted::class);
            if ($annotation instanceof Encrypted) {
                $effectiveConfig = $annotation->config === 'default' ? $defaultConfigName : $annotation->config;
                if ($effectiveConfig === $configName) {
                    $properties[] = $property;
                }
            }
        }

        return $properties;
    }

    /**
     * Returns metadata for entities that have at least one Encrypted property for the given config.
     *
     * @param string $configName Config alias (e.g. personal_data)
     * @param string $defaultConfigName Registry default config name (for resolving Encrypted('default'))
     */
    protected function getEncryptionableEntityMetaDataForConfig(string $configName, string $defaultConfigName): array
    {
        $validMetaData = [];
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metaDataArray as $entityMetaData) {
            if (isset($entityMetaData->isMappedSuperclass) && $entityMetaData->isMappedSuperclass) {
                continue;
            }

            $properties = $this->getEncryptionablePropertiesForConfig($entityMetaData, $configName, $defaultConfigName);
            if (count($properties) > 0) {
                $validMetaData[] = $entityMetaData;
            }
        }

        return $validMetaData;
    }

    /**
     * Returns table name, identifier column names, and encrypted column names for the given entity and config.
     * Used for raw SQL encrypt/decrypt (no Doctrine lifecycle events).
     *
     * @param object $entityMetaData Doctrine ClassMetadata
     * @param string $configName Config name (e.g. personal_data)
     * @param string $defaultConfigName Default config name for resolving Encrypted('default')
     *
     * @return array{table: string, idColumns: array<int, string>, columns: array<int, array{field: string, column: string}>}
     */
    protected function getEncryptedTableInfo($entityMetaData, string $configName, string $defaultConfigName): array
    {
        $table = $entityMetaData->getTableName();
        // Resolve identifier column names from field names so we use actual DB column names (Doctrine may expose field names in some versions)
        $idColumns = [];
        if (method_exists($entityMetaData, 'getIdentifierFieldNames')) {
            foreach ($entityMetaData->getIdentifierFieldNames() as $idFieldName) {
                $idColumns[] = $this->getColumnNameFromMetadata($entityMetaData, $idFieldName);
            }
        } else {
            $idColumns = $entityMetaData->getIdentifierColumnNames();
        }
        $properties = $this->getEncryptionablePropertiesForConfig($entityMetaData, $configName, $defaultConfigName);
        $columns    = [];
        foreach ($properties as $property) {
            $fieldName  = $property->getName();
            $columnName = $this->getColumnNameFromMetadata($entityMetaData, $fieldName);
            $columns[]  = ['field' => $fieldName, 'column' => $columnName];
        }

        return ['table' => $table, 'idColumns' => $idColumns, 'columns' => $columns];
    }

    /**
     * Returns the database column name for a field (compatible with Doctrine ORM 2.x and 3.x).
     *
     * @param object $entityMetaData Doctrine ClassMetadata
     * @param string $fieldName Property/field name
     */
    protected function getColumnNameFromMetadata($entityMetaData, string $fieldName): string
    {
        if (method_exists($entityMetaData, 'getColumnName')) {
            return $entityMetaData->getColumnName($fieldName);
        }
        $mapping = $entityMetaData->getFieldMapping($fieldName);

        return $mapping['columnName'] ?? $fieldName;
    }

    /**
     * Get a value from a DB row by column name (exact or case-insensitive match).
     * Drivers may return different key casing (e.g. SQLite, PostgreSQL).
     *
     * @param array<string, mixed> $row
     */
    protected function getRowValue(array $row, string $columnName): mixed
    {
        if (array_key_exists($columnName, $row)) {
            return $row[$columnName];
        }
        $lower = strtolower($columnName);
        foreach ($row as $k => $v) {
            if (strtolower((string) $k) === $lower) {
                return $v;
            }
        }

        return null;
    }
}
