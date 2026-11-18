<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Exception\ChunkLockConflictException;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Mapping\ListenerFieldResolver;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

use function array_column;
use function array_fill;
use function array_key_exists;
use function array_values;
use function count;
use function ctype_digit;
use function implode;
use function in_array;
use function is_int;
use function is_numeric;
use function is_string;
use function sprintf;

/**
 * Base class for bundle console commands.
 *
 * Provides shared helpers: entity iteration, encrypted property discovery, and config-filtered metadata.
 *
 * Encrypted fields are the properties marked with #[Encrypted], the ones selected in `encrypted_fields`, and (for the
 * raw SQL database commands) the listener-encrypted fields inside followed embeddables and the columns mapped with an
 * encrypted DBAL type.
 */
abstract class AbstractCommand extends Command
{
    private ?EncryptedFieldMap $encryptedFieldMap; // @igor-ignore - Set once by the container (setter injection), immutable afterwards.

    private ?ListenerFieldResolver $listenerFieldResolver; // @igor-ignore - Reflection cache built from the immutable field map.

    /**
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param AttributeReader $attributeReader Reader for Encrypted attributes
     * @param DoctrineEncryptSubscriber $subscriber Encrypt/decrypt event subscriber
     * @param EncryptorInterface|null $defaultEncryptor Used by encrypt/decrypt database commands when using multi-config
     * @param EncryptorRegistryInterface|null $encryptorRegistry Used by encrypt/decrypt database commands and status command to resolve config names
     */
    public function __construct(
        public EntityManagerInterface $entityManager,
        public AttributeReader $attributeReader,
        public DoctrineEncryptSubscriber $subscriber,
        public ?EncryptorInterface $defaultEncryptor = null,
        public ?EncryptorRegistryInterface $encryptorRegistry = null
    ) {
        // @igor-ignore - Container wiring (setter injection below), never per-request state.
        $this->encryptedFieldMap = null;
        // @igor-ignore - Reflection cache derived from the immutable field map.
        $this->listenerFieldResolver = null;
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
        // @igor-ignore - Rebuilt from the new map.
        $this->listenerFieldResolver = null;
    }

    /**
     * Fields selected in encrypted_fields (null when the container did not inject the map).
     */
    protected function getEncryptedFieldMap(): ?EncryptedFieldMap
    {
        return $this->encryptedFieldMap;
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

            $defaultName = $this->encryptorRegistry?->getDefaultName() ?? 'default';
            if (count($properties) === 0 && $this->getEncryptedTypeFields($entityMetaData, $defaultName) === [] && $this->getListenerEmbeddedFields($entityMetaData, $defaultName) === []) {
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
     * @param string $defaultConfigName Default config name (e.g. from EncryptorRegistryInterface::getDefaultName())
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
            if (count($properties) > 0 || $this->getEncryptedColumnFieldsForConfig($entityMetaData, $configName, $defaultConfigName) !== []) {
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
        // Listener fields inside embeddables live in the owner's table; encrypted DBAL types store the same format.
        foreach ($this->getEncryptedColumnFieldsForConfig($entityMetaData, $configName, $defaultConfigName) as $fieldName) {
            $columnName = $this->getColumnNameFromMetadata($entityMetaData, $fieldName);
            if (!isset($seen[$columnName])) {
                $columns[]         = ['field' => $fieldName, 'column' => $columnName];
                $seen[$columnName] = true;
            }
        }

        return ['table' => $table, 'idColumns' => $idColumns, 'columns' => $columns];
    }

    /**
     * Field names of listener-encrypted embedded fields and of encrypted DBAL type columns that use the given config.
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     *
     * @return list<string>
     */
    protected function getEncryptedColumnFieldsForConfig($entityMetaData, string $configName, string $defaultConfigName): array
    {
        $fields = [];
        foreach ([...$this->getListenerEmbeddedFields($entityMetaData, $defaultConfigName), ...$this->getEncryptedTypeFields($entityMetaData, $defaultConfigName)] as $field) {
            if ($field['config'] === $configName && !in_array($field['field'], $fields, true)) {
                $fields[] = $field['field'];
            }
        }

        return $fields;
    }

    /**
     * Listener-encrypted fields inside embeddables the listener follows (#[Encrypted] / encrypted_fields on the
     * embeddable class, followed through #[ORM\Embedded] / encrypted_fields "embedded"), e.g. "address.street", with
     * their resolved profile. Their columns are in the owner's table, so the raw SQL commands process them.
     *
     * @param ClassMetadata<object>|object $entityMetaData Doctrine entity metadata
     *
     * @return list<array{field: string, config: string}>
     */
    protected function getListenerEmbeddedFields($entityMetaData, string $defaultConfigName): array
    {
        if (!$entityMetaData instanceof ClassMetadata || $entityMetaData->embeddedClasses === []) {
            return [];
        }

        // @igor-ignore - Reflection cache derived from the immutable field map, built once.
        $this->listenerFieldResolver ??= new ListenerFieldResolver($this->encryptedFieldMap ?? new EncryptedFieldMap());
        $fields = [];
        foreach ($this->listenerFieldResolver->embeddedFields($entityMetaData) as $field) {
            $fields[] = ['field' => $field['field'], 'config' => $field['config'] === 'default' ? $defaultConfigName : $field['config']];
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
     * Reads the --lock-timeout option (seconds a chunk waits for a row lock; 0 = the server's own setting).
     *
     * @return int|null Timeout in seconds, or null when the option is not a non-negative integer
     */
    protected function resolveLockTimeout(InputInterface $input): ?int
    {
        if (!$input->hasOption('lock-timeout')) {
            return ChunkedRun::DEFAULT_LOCK_TIMEOUT_SECONDS;
        }
        $raw = $input->getOption('lock-timeout') ?? ChunkedRun::DEFAULT_LOCK_TIMEOUT_SECONDS;
        if (is_string($raw) && ctype_digit($raw)) {
            $raw = (int) $raw;
        }

        return is_int($raw) && $raw >= 0 ? $raw : null;
    }

    /**
     * Writes the answer to a chunk that could not be locked: what was committed and what to do next.
     */
    protected function reportChunkConflict(OutputInterface $output, ChunkLockConflictException $conflict): void
    {
        $output->writeln('');
        $output->writeln('<error>' . $conflict->getMessage() . '</error>');
        if ($conflict->conflictCode->retrySafe()) {
            $output->writeln('Nothing was changed. Run the command again when the application writes less (or in maintenance mode).');

            return;
        }
        $output->writeln('Earlier chunks are committed and this chunk was rolled back. Run the command again to resume: values already processed are skipped.');
    }

    /**
     * Processes a table in chunks of $batchSize rows, so the whole table is never loaded in memory, one transaction
     * per chunk (REQ-RUNTIME-003 rules 1, 4 and 9):
     *
     * 1. the identifiers of the next chunk are read without locks, by keyset (identifier columns greater than the last
     *    identifier of the previous chunk, in identifier order), so rows inserted or deleted meanwhile never shift the
     *    chunks;
     * 2. inside the chunk transaction the rows are read again by identifier with SELECT ... FOR UPDATE (in identifier
     *    order; WITH (UPDLOCK, ROWLOCK) on SQL Server; SQLite locks the whole database file), and $processRow sees the
     *    current, locked values: an application write either commits before the read (and is seen) or waits for the
     *    chunk's commit (and lands after it). No application write is overwritten;
     * 3. the lock wait is bounded by the run's lock timeout (MySQL / MariaDB innodb_lock_wait_timeout for the session,
     *    restored afterwards; PostgreSQL SET LOCAL lock_timeout);
     * 4. a lock conflict (deadlock, lock wait timeout, serialization failure) rolls the chunk back and replays it, up to
     *    the run's attempts, then throws ChunkLockConflictException (CONFLICT_001 / CONFLICT_002). Any other failure
     *    rolls the chunk back and is rethrown unchanged. Earlier chunks stay committed.
     *
     * $processRow may therefore run more than once for a row: it must count through $run (ChunkedRun::add() / record()).
     *
     * Identifier columns are never modified by the callers, so the keyset stays stable across chunks.
     *
     * @param array<int, string> $selectCols Quoted columns to select
     * @param array<int, string> $quotedIdCols Quoted identifier columns (keyset, ORDER BY, row lookup)
     * @param callable(array<string, mixed>): void $processRow Called for each locked row
     * @param ChunkedRun|null $run Lock timeout, attempts and committed counters of the run (default: a new run)
     *
     * @throws ChunkLockConflictException When a chunk stays in a lock conflict after all its attempts
     * @throws InvalidArgumentException When no identifier column is given
     */
    protected function processTableInBatches(Connection $conn, string $quotedTable, array $selectCols, array $quotedIdCols, int $batchSize, callable $processRow, ?ChunkedRun $run = null): void
    {
        if ($quotedIdCols === []) {
            throw new InvalidArgumentException('Chunked processing needs the identifier columns of the table.');
        }
        $run ??= new ChunkedRun();
        $platform = $conn->getDatabasePlatform();
        $idList   = implode(', ', $quotedIdCols);
        $after    = null;
        $restore  = $this->applySessionLockTimeout($conn, $run->lockTimeoutSeconds);

        try {
            do {
                [$where, $params] = $this->keysetCondition($quotedIdCols, $after);
                $sqlIds           = 'SELECT ' . $idList . ' FROM ' . $quotedTable . $where . ' ORDER BY ' . $idList;
                $ids              = $conn->executeQuery($platform->modifyLimitQuery($sqlIds, $batchSize), $params, array_fill(0, count($params), ParameterType::STRING))->fetchAllNumeric();
                if ($ids === []) {
                    break;
                }

                $this->processChunk($conn, $quotedTable, $selectCols, $quotedIdCols, $ids, $processRow, $run);
                $after = $ids[count($ids) - 1];
            } while (count($ids) >= $batchSize);
        } finally {
            $restore();
        }
    }

    /**
     * Runs one chunk in its own transaction (transaction-doctrine runner: savepoint-aware rollback to the entry level,
     * transient failures classified), replaying it after a lock conflict.
     *
     * @param array<int, string> $selectCols
     * @param array<int, string> $quotedIdCols
     * @param non-empty-list<list<mixed>> $ids Identifier values of the chunk's rows, in identifier order
     * @param callable(array<string, mixed>): void $processRow
     */
    private function processChunk(Connection $conn, string $quotedTable, array $selectCols, array $quotedIdCols, array $ids, callable $processRow, ChunkedRun $run): void
    {
        $runner         = DoctrineTransactionRunner::forConnection($conn, null);
        [$sql, $params] = $this->lockedChunkSelect($conn, $quotedTable, $selectCols, $quotedIdCols, $ids);
        $types          = array_fill(0, count($params), ParameterType::STRING);

        for ($attempt = 1;; ++$attempt) {
            $run->beginAttempt();
            try {
                $rowCount = $runner->run(function () use ($conn, $sql, $params, $types, $processRow, $run): int {
                    $this->applyTransactionLockTimeout($conn, $run->lockTimeoutSeconds);
                    $rows = $conn->executeQuery($sql, $params, $types)->fetchAllAssociative();
                    foreach ($rows as $row) {
                        $processRow($row);
                    }

                    return count($rows);
                });
            } catch (TransientStorageFailure $failure) {
                $run->beginAttempt();
                if ($attempt >= $run->maxAttempts) {
                    throw ChunkLockConflictException::afterAttempts($quotedTable, $attempt, $run->committedChunks() > 0, $failure);
                }
                $run->backoff($attempt);

                continue;
            } catch (Throwable $e) {
                $run->beginAttempt();

                throw $e;
            }

            $run->commitAttempt($rowCount);

            return;
        }
    }

    /**
     * The keyset condition "identifier after $after" (row-value comparison expanded, so every platform runs it):
     * (a > ?) OR (a = ? AND b > ?) ...
     *
     * @param array<int, string> $quotedIdCols
     * @param list<mixed>|null $after Identifier values of the last row of the previous chunk (null = first chunk)
     *
     * @return array{string, list<mixed>} The " WHERE ..." clause (empty for the first chunk) and its parameters
     */
    private function keysetCondition(array $quotedIdCols, ?array $after): array
    {
        if ($after === null) {
            return ['', []];
        }
        $quotedIdCols = array_values($quotedIdCols);
        $disjuncts    = [];
        $params       = [];
        foreach ($quotedIdCols as $i => $column) {
            $parts = [];
            for ($j = 0; $j < $i; ++$j) {
                $parts[]  = $quotedIdCols[$j] . ' = ?';
                $params[] = $after[$j] ?? null;
            }
            $parts[]     = $column . ' > ?';
            $params[]    = $after[$i] ?? null;
            $disjuncts[] = '(' . implode(' AND ', $parts) . ')';
        }

        return [' WHERE ' . implode(' OR ', $disjuncts), $params];
    }

    /**
     * SELECT of the chunk's rows by identifier, in identifier order, locked for update.
     *
     * @param array<int, string> $selectCols
     * @param array<int, string> $quotedIdCols
     * @param non-empty-list<list<mixed>> $ids
     *
     * @return array{string, list<mixed>}
     */
    private function lockedChunkSelect(Connection $conn, string $quotedTable, array $selectCols, array $quotedIdCols, array $ids): array
    {
        $platform     = $conn->getDatabasePlatform();
        $quotedIdCols = array_values($quotedIdCols);
        $params       = [];
        if (count($quotedIdCols) === 1) {
            foreach ($ids as $id) {
                $params[] = $id[0] ?? null;
            }
            $where = $quotedIdCols[0] . ' IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')';
        } else {
            $disjuncts = [];
            foreach ($ids as $id) {
                $parts = [];
                foreach ($quotedIdCols as $i => $column) {
                    $parts[]  = $column . ' = ?';
                    $params[] = $id[$i] ?? null;
                }
                $disjuncts[] = '(' . implode(' AND ', $parts) . ')';
            }
            $where = implode(' OR ', $disjuncts);
        }

        $from = $quotedTable;
        $lock = ' FOR UPDATE';
        if ($platform instanceof SQLServerPlatform) {
            $from .= ' WITH (UPDLOCK, ROWLOCK)';
            $lock = '';
        } elseif ($platform instanceof SQLitePlatform) {
            // SQLite has no row locks: a write transaction locks the database file, and a concurrent writer gets
            // SQLITE_BUSY (a lock wait timeout) instead of overwriting the chunk.
            $lock = '';
        }

        return ['SELECT ' . implode(', ', $selectCols) . ' FROM ' . $from . ' WHERE ' . $where . ' ORDER BY ' . implode(', ', $quotedIdCols) . $lock, $params];
    }

    /**
     * MySQL / MariaDB: sets innodb_lock_wait_timeout for the session and returns the callback that restores it.
     *
     * @return Closure(): void
     */
    private function applySessionLockTimeout(Connection $conn, int $seconds): Closure
    {
        if ($seconds === 0 || !$conn->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return static function (): void {
            };
        }

        $previous = $conn->fetchOne('SELECT @@SESSION.innodb_lock_wait_timeout');
        $conn->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . $seconds);

        return static function () use ($conn, $previous): void {
            if (is_numeric($previous) && $conn->isConnected()) {
                $conn->executeStatement('SET SESSION innodb_lock_wait_timeout = ' . (int) $previous);
            }
        };
    }

    /**
     * PostgreSQL: SET LOCAL lock_timeout for the current chunk transaction.
     */
    private function applyTransactionLockTimeout(Connection $conn, int $seconds): void
    {
        if ($seconds > 0 && $conn->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $conn->executeStatement("SET LOCAL lock_timeout = '" . $seconds . "s'");
        }
    }
}
