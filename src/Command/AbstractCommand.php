<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

use function array_column;
use function array_key_exists;
use function count;
use function ctype_digit;
use function is_int;
use function is_string;
use function sprintf;

/**
 * Base class for bundle console commands.
 *
 * Provides shared helpers: entity iteration, encrypted property discovery, and config-filtered metadata.
 *
 * Encrypted fields are the properties marked with #[Encrypted], the ones selected in `encrypted_fields`, and (for the
 * raw SQL database commands) the columns mapped with an encrypted DBAL type.
 */
abstract class AbstractCommand extends Command
{
    private ?EncryptedFieldMap $encryptedFieldMap; // @igor-ignore - Set once by the container (setter injection), immutable afterwards.

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
        // @igor-ignore - Container wiring (setter injection below), never per-request state.
        $this->encryptedFieldMap = null;
        parent::__construct();
    }

    /**
     * Fields selected in the bundle configuration (encrypted_fields), injected by the container.
     */
    #[Required]
    public function setEncryptedFieldMap(EncryptedFieldMap $encryptedFieldMap): void
    {
        // @igor-ignore - Container wiring at service creation, immutable afterwards.
        $this->encryptedFieldMap = $encryptedFieldMap;
    }

    /**
     * Returns an iterable over all entities of the given class (for batch processing).
     *
     * @param string $entityName Fully qualified entity class name
     *
     * @return iterable<object>
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
     *
     * @return list<ClassMetadata<object>>
     */
    protected function getEncryptionableEntityMetaData(): array
    {
        $validMetaData = [];
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metaDataArray as $entityMetaData) {
            if ($entityMetaData->isMappedSuperclass || $this->isEmbeddable($entityMetaData)) {
                continue;
            }

            $properties = $this->getEncryptionableProperties($entityMetaData);

            if (count($properties) === 0 && $this->getEncryptedTypeFields($entityMetaData, $this->encryptorRegistry?->getDefaultName() ?? 'default') === []) {
                continue;
            }

            $validMetaData[] = $entityMetaData;
        }

        return $validMetaData;
    }

    /**
     * Returns properties of the entity that are marked with the Encrypted attribute.
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata (ClassMetadata)
     *
     * @return array<ReflectionProperty>
     */
    protected function getEncryptionableProperties($entityMetaData): array
    {
        return array_column($this->encryptedPropertiesWithRawConfig($entityMetaData), 'property');
    }

    /**
     * Returns encrypted properties with their effective config name (resolves "default" to $defaultConfigName).
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata (ClassMetadata)
     * @param string $defaultConfigName Default config name (e.g. from EncryptorRegistry::getDefaultName())
     *
     * @return array<int, array{property: ReflectionProperty, config: string}>
     */
    protected function getEncryptionablePropertiesWithConfig($entityMetaData, string $defaultConfigName): array
    {
        $result = [];
        foreach ($this->encryptedPropertiesWithRawConfig($entityMetaData) as ['property' => $property, 'config' => $config]) {
            $result[] = ['property' => $property, 'config' => $config === 'default' ? $defaultConfigName : $config];
        }

        return $result;
    }

    /**
     * Returns properties marked with Encrypted that belong to the given config.
     * When a property has config "default", it is included if $defaultConfigName === $configName.
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     * @param string $configName Config name (e.g. personal_data, financial_data)
     * @param string $defaultConfigName Registry default config name (for resolving Encrypted('default'))
     *
     * @return array<ReflectionProperty>
     */
    protected function getEncryptionablePropertiesForConfig($entityMetaData, string $configName, string $defaultConfigName): array
    {
        $properties = [];
        foreach ($this->encryptedPropertiesWithRawConfig($entityMetaData) as ['property' => $property, 'config' => $config]) {
            $effectiveConfig = $config === 'default' ? $defaultConfigName : $config;
            if ($effectiveConfig === $configName) {
                $properties[] = $property;
            }
        }

        return $properties;
    }

    /**
     * Properties marked with #[Encrypted] (declared or inherited, as before) followed by the ones selected in
     * encrypted_fields, with their profile alias as written ("default" not resolved).
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     *
     * @return list<array{property: ReflectionProperty, config: string}>
     */
    private function encryptedPropertiesWithRawConfig($entityMetaData): array
    {
        /** @var object{name: class-string} $entityMetaData */
        $reflectionClass = new ReflectionClass($entityMetaData->name);
        $result          = [];
        $known           = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $annotation = $this->attributeReader->getPropertyAnnotation($property, Encrypted::class);
            if ($annotation instanceof Encrypted) {
                $result[]                    = ['property' => $property, 'config' => $annotation->config];
                $known[$property->getName()] = true;
            }
        }

        if (!$this->encryptedFieldMap instanceof EncryptedFieldMap) {
            return $result;
        }

        foreach ($this->encryptedFieldMap->getProperties($reflectionClass->getName()) as $name => $config) {
            if (isset($known[$name])) {
                continue;
            }
            for ($class = $reflectionClass; $class instanceof ReflectionClass; $class = $class->getParentClass() ?: null) {
                if ($class->hasProperty($name)) {
                    $result[]     = ['property' => $class->getProperty($name), 'config' => $config];
                    $known[$name] = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Whether the metadata describes an embeddable (no table of its own: the raw SQL commands skip it).
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     */
    protected function isEmbeddable(object $entityMetaData): bool
    {
        return $entityMetaData instanceof ClassMetadata && $entityMetaData->isEmbeddedClass;
    }

    /**
     * Fields mapped with an encrypted DBAL type (encrypted_string, encrypted_text, encrypted_json or a subclass),
     * embedded fields included ("address.street"), with their resolved profile.
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     * @param string $defaultConfigName Registry default config name (for types without a profile suffix)
     *
     * @return list<array{field: string, config: string, type: string}>
     */
    protected function getEncryptedTypeFields($entityMetaData, string $defaultConfigName): array
    {
        if (!$entityMetaData instanceof ClassMetadata) {
            return [];
        }

        $fields = [];
        foreach ($entityMetaData->getFieldNames() as $fieldName) {
            $typeName = $entityMetaData->getTypeOfField($fieldName);
            if (!is_string($typeName) || !Type::hasType($typeName)) {
                continue;
            }
            $type = Type::getType($typeName);
            if (!$type instanceof AbstractEncryptedType) {
                continue;
            }
            $profile  = $type->getProfile();
            $fields[] = ['field' => $fieldName, 'config' => $profile === 'default' ? $defaultConfigName : $profile, 'type' => $typeName];
        }

        return $fields;
    }

    /**
     * Returns metadata for entities that have at least one Encrypted property for the given config.
     *
     * @param string $configName Config alias (e.g. personal_data)
     * @param string $defaultConfigName Registry default config name (for resolving Encrypted('default'))
     *
     * @return list<ClassMetadata<object>>
     */
    protected function getEncryptionableEntityMetaDataForConfig(string $configName, string $defaultConfigName): array
    {
        $validMetaData = [];
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metaDataArray as $entityMetaData) {
            if ($entityMetaData->isMappedSuperclass || $this->isEmbeddable($entityMetaData)) {
                continue;
            }

            $properties = $this->getEncryptionablePropertiesForConfig($entityMetaData, $configName, $defaultConfigName);
            if (count($properties) > 0 || $this->getEncryptedTypeFieldsForConfig($entityMetaData, $configName, $defaultConfigName) !== []) {
                $validMetaData[] = $entityMetaData;
            }
        }

        return $validMetaData;
    }

    /**
     * Returns table name, identifier column names, and encrypted column names for the given entity and config.
     * Used for raw SQL encrypt/decrypt (no Doctrine lifecycle events).
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine ClassMetadata
     * @param string $configName Config name (e.g. personal_data)
     * @param string $defaultConfigName Default config name for resolving Encrypted('default')
     *
     * @return array{table: string, idColumns: array<int, string>, columns: array<int, array{field: string, column: string}>}
     */
    protected function getEncryptedTableInfo($entityMetaData, string $configName, string $defaultConfigName): array
    {
        /** @var ClassMetadata<object>&object{name: class-string} $entityMetaData */
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
        $seen       = [];
        foreach ($properties as $property) {
            $fieldName         = $property->getName();
            $columnName        = $this->getColumnNameFromMetadata($entityMetaData, $fieldName);
            $columns[]         = ['field' => $fieldName, 'column' => $columnName];
            $seen[$columnName] = true;
        }
        // Encrypted DBAL types store the same format, so the raw SQL commands handle their columns too.
        foreach ($this->getEncryptedTypeFieldsForConfig($entityMetaData, $configName, $defaultConfigName) as $fieldName) {
            $columnName = $this->getColumnNameFromMetadata($entityMetaData, $fieldName);
            if (!isset($seen[$columnName])) {
                $columns[]         = ['field' => $fieldName, 'column' => $columnName];
                $seen[$columnName] = true;
            }
        }

        return ['table' => $table, 'idColumns' => $idColumns, 'columns' => $columns];
    }

    /**
     * Field names mapped with an encrypted DBAL type that use the given config.
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     *
     * @return list<string>
     */
    protected function getEncryptedTypeFieldsForConfig($entityMetaData, string $configName, string $defaultConfigName): array
    {
        $fields = [];
        foreach ($this->getEncryptedTypeFields($entityMetaData, $defaultConfigName) as $field) {
            if ($field['config'] === $configName) {
                $fields[] = $field['field'];
            }
        }

        return $fields;
    }

    /**
     * Returns the database column name for a field (compatible with Doctrine ORM 2.x and 3.x).
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine ClassMetadata
     * @param string $fieldName Property/field name
     */
    protected function getColumnNameFromMetadata($entityMetaData, string $fieldName): string
    {
        if (method_exists($entityMetaData, 'getColumnName')) {
            return $entityMetaData->getColumnName($fieldName);
        }
        /** @var ClassMetadata<object> $entityMetaData */
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

    /**
     * Reads the batchSize argument (falls back to $default when absent) and validates it.
     *
     * @return int|null Positive batch size, or null when the argument is not a positive integer
     */
    protected function resolveBatchSize(InputInterface $input, int $default): ?int
    {
        $raw = $input->getArgument('batchSize') ?? $default;
        if (is_string($raw) && ctype_digit($raw)) {
            $raw = (int) $raw;
        }

        return is_int($raw) && $raw > 0 ? $raw : null;
    }

    /**
     * Counts the rows of a table (used to size progress bars without loading the table).
     */
    protected function countTableRows(Connection $conn, string $quotedTable): int
    {
        return (int) $conn->fetchOne('SELECT COUNT(*) FROM ' . $quotedTable);
    }

    /**
     * Processes a table in chunks of $batchSize rows, ordered by the identifier columns (offset pagination),
     * so the whole table is never loaded in memory. Each chunk runs inside its own transaction: it is
     * committed once $processRow has been called for every row of the chunk, and rolled back on error.
     *
     * Identifier columns are never modified by the callers, so the ordering stays stable across chunks.
     *
     * @param array<int, string> $selectCols Quoted columns to select
     * @param array<int, string> $quotedIdCols Quoted identifier columns (ORDER BY)
     * @param callable(array<string, mixed>): void $processRow Called for each row
     */
    protected function processTableInBatches(Connection $conn, string $quotedTable, array $selectCols, array $quotedIdCols, int $batchSize, callable $processRow): void
    {
        $sqlSelect = 'SELECT ' . implode(', ', $selectCols) . ' FROM ' . $quotedTable;
        if ($quotedIdCols !== []) {
            $sqlSelect .= ' ORDER BY ' . implode(', ', $quotedIdCols);
        }
        $platform = $conn->getDatabasePlatform();
        $offset   = 0;

        do {
            $rows = $conn->executeQuery($platform->modifyLimitQuery($sqlSelect, $batchSize, $offset))->fetchAllAssociative();
            if ($rows === []) {
                break;
            }

            $conn->beginTransaction();
            try {
                foreach ($rows as $row) {
                    $processRow($row);
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollBack();

                throw $e;
            }

            $offset += $batchSize;
        } while (count($rows) >= $batchSize);
    }
}
