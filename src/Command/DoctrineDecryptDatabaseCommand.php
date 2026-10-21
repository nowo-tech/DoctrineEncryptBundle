<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;

use function array_slice;
use function assert;
use function count;
use function in_array;
use function is_scalar;
use function sprintf;
use function strlen;

use const PHP_EOL;

/**
 * Decrypts all encrypted values in the database using raw SQL (no Doctrine lifecycle events).
 *
 * Can process a single config (e.g. personal_data) or all configs when no argument is given.
 */
#[AsCommand(name: 'doctrine:decrypt:database', description: 'Decrypt whole database on tables which are encrypted', aliases: ['doctrine:decrypt:database'], hidden: false)]
final class DoctrineDecryptDatabaseCommand extends AbstractCommand
{
    private const ENCRYPTION_MARKER = DoctrineEncryptSubscriber::ENCRYPTION_MARKER;

    /** Maximum number of non-decryptable values listed individually in the output. */
    private const MAX_REPORTED_FAILURES = 50;

    /**
     * @param EntityManagerInterface $entityManager Entity manager to process
     * @param AttributeReader $attributeReader Reads #[Encrypted] properties
     * @param DoctrineEncryptSubscriber $subscriber Encrypt/decrypt Doctrine subscriber
     * @param EncryptorInterface|null $defaultEncryptor Encryptor of the default profile
     * @param EncryptorRegistry|null $encryptorRegistry Encryptors per profile alias
     * @param int $defaultBatchSize Default batch size (`batch_size` config)
     * @param string $kernelEnvironment Kernel environment
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        AttributeReader $attributeReader,
        DoctrineEncryptSubscriber $subscriber,
        ?EncryptorInterface $defaultEncryptor,
        ?EncryptorRegistry $encryptorRegistry,
        private readonly int $defaultBatchSize = 5,
        private readonly string $kernelEnvironment = 'dev',
    ) {
        parent::__construct($entityManager, $attributeReader, $subscriber, $defaultEncryptor, $encryptorRegistry);
    }

    protected function configure(): void
    {
        $def = $this->getDefinition();
        if (!$def->hasArgument('config')) {
            $this->addArgument('config', InputArgument::OPTIONAL, 'Config name to use (e.g. personal_data, financial_data). If omitted, all configs are processed in turn.');
        }
        if (!$def->hasArgument('batchSize')) {
            $this->addArgument('batchSize', InputArgument::OPTIONAL, 'The update batch size (smaller = less memory; configurable via nowo_doctrine_encrypt.batch_size)', (string) $this->defaultBatchSize);
        }
        if (!$def->hasOption('force')) {
            $this->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation (use with --no-interaction).');
        }
        if (!$def->hasOption('allow-prod')) {
            $this->addOption('allow-prod', null, InputOption::VALUE_NONE, 'Allow mass decrypt when kernel environment is prod (dangerous; prefer restoring from backup).');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->kernelEnvironment === 'prod' && !$input->getOption('allow-prod')) {
            $output->writeln('<error>Refusing doctrine:decrypt:database in prod. Re-run with --allow-prod only after an explicit backup, or run in a non-prod environment.</error>');

            return self::FAILURE;
        }

        $question  = $this->getHelper('question');
        $configArg = $input->getArgument('config');
        $batchSize = $this->resolveBatchSize($input, $this->defaultBatchSize);
        if ($batchSize === null) {
            $output->writeln('<error>The batchSize argument must be a positive integer.</error>');

            return self::FAILURE;
        }

        $registry          = $this->encryptorRegistry;
        $defaultConfigName = $registry?->getDefaultName();

        if ($configArg !== null && $configArg !== '') {
            if (!$registry instanceof EncryptorRegistry || !$registry->has($configArg)) {
                $available = $registry instanceof EncryptorRegistry ? implode(', ', $registry->getConfigNames()) : 'default';
                $output->writeln('<error>Unknown config "' . $configArg . '". Available: ' . $available . '</error>');

                return self::FAILURE;
            }
            $configsToProcess = [$configArg];
        } elseif (!$registry instanceof EncryptorRegistry) {
            $configsToProcess = $defaultConfigName !== null ? [$defaultConfigName] : [];
        } else {
            $configsToProcess = array_values(array_filter($registry->getConfigNames(), static fn (string $n): bool => $n !== 'default'));
            if ($configsToProcess === []) {
                $configsToProcess = [$registry->getDefaultName()];
            }
        }

        if ($configsToProcess === []) {
            $output->writeln('<error>No encryptor configured. Configure nowo_doctrine_encrypt in config.</error>');

            return self::FAILURE;
        }

        $defaultConfigName ??= $configsToProcess[0];

        $propertyCount = 0;
        foreach ($configsToProcess as $configName) {
            foreach ($this->getEncryptionableEntityMetaDataForConfig($configName, $defaultConfigName) as $metaData) {
                $propertyCount += count($this->getEncryptionablePropertiesForConfig($metaData, $configName, $defaultConfigName));
            }
        }

        $encryptor = $registry instanceof EncryptorRegistry ? $registry->get($configsToProcess[0]) : $this->subscriber->getEncryptor();
        if (!$encryptor instanceof EncryptorInterface && $this->defaultEncryptor instanceof EncryptorInterface) {
            $encryptor = $this->defaultEncryptor;
        }
        if (!$encryptor instanceof EncryptorInterface) {
            $output->writeln('<error>No encryptor configured.</error>');

            return self::FAILURE;
        }

        $configList           = implode(', ', $configsToProcess);
        $confirmationQuestion = new ConfirmationQuestion(
            '<question>' . count($configsToProcess) . ' config(s): [' . $configList . '], ' . $propertyCount . ' encrypted properties.' . PHP_EOL .
            'Which are going to be decrypted. Wrong settings can mess up your data.' . PHP_EOL .
            'I advise you to make <bg=yellow;options=bold>a backup</bg=yellow;options=bold>. ' . PHP_EOL .
            'Continue? (y/yes)</question>',
            false,
        );

        assert($question instanceof QuestionHelper);
        $proceed = $input->getOption('force') || $question->ask($input, $output, $confirmationQuestion);
        if (!$proceed) {
            return self::SUCCESS;
        }

        $output->writeln('' . PHP_EOL . 'Decrypting all fields with raw SQL (no Doctrine events). This can take several minutes depending on the database size.');

        $conn         = $this->entityManager->getConnection();
        $platform     = $conn->getDatabasePlatform();
        $valueCounter = 0;
        /** @var list<string> $failures One entry per value that could not be decrypted (no value content) */
        $failures = [];

        foreach ($configsToProcess as $configName) {
            $encryptorForConfig = $registry instanceof EncryptorRegistry ? $registry->get($configName) : $this->defaultEncryptor;
            if (!$encryptorForConfig instanceof EncryptorInterface) {
                continue;
            }

            $metaDataArray = $this->getEncryptionableEntityMetaDataForConfig($configName, $defaultConfigName);
            $output->writeln(sprintf('Config <comment>%s</comment> (%s)', $configName, $encryptorForConfig::class));

            foreach ($metaDataArray as $metaData) {
                $tableInfo = $this->getEncryptedTableInfo($metaData, $configName, $defaultConfigName);
                if ($tableInfo['columns'] === []) {
                    continue;
                }

                $table      = $tableInfo['table'];
                $idColumns  = $tableInfo['idColumns'];
                $encColumns = array_column($tableInfo['columns'], 'column');

                $quotedTable   = $platform->quoteIdentifier($table);
                $quotedIdCols  = array_map($platform->quoteIdentifier(...), $idColumns);
                $quotedEncCols = array_map($platform->quoteIdentifier(...), $encColumns);

                // Select only ID columns + encrypted columns for this config (each config is independent),
                // in chunks of batchSize rows, one transaction per chunk.
                $selectCols = array_merge($quotedIdCols, $quotedEncCols);
                $totalCount = $this->countTableRows($conn, $quotedTable);

                $output->writeln(sprintf('  Processing <comment>%s</comment> (%s)', $metaData->name, $table));
                $progressBar = new ProgressBar($output, $totalCount);

                $this->processTableInBatches($conn, $quotedTable, $selectCols, $quotedIdCols, $batchSize, function (array $row) use ($idColumns, $tableInfo, $encryptorForConfig, $conn, $platform, $quotedTable, $progressBar, $table, &$valueCounter, &$failures): void {
                    $idValues = [];
                    foreach ($idColumns as $idCol) {
                        $idValues[$idCol] = $this->getRowValue($row, $idCol);
                    }
                    if (in_array(null, $idValues, true)) {
                        $progressBar->advance(1);

                        return;
                    }
                    $updates = [];
                    $params  = [];
                    $types   = [];
                    foreach ($tableInfo['columns'] as $colInfo) {
                        $col   = $colInfo['column'];
                        $value = $this->getRowValue($row, $col);
                        if ($value === null || $value === '') {
                            continue;
                        }
                        if (!str_ends_with((string) $value, self::ENCRYPTION_MARKER)) {
                            continue;
                        }
                        $ciphertext = substr((string) $value, 0, -strlen(self::ENCRYPTION_MARKER));
                        try {
                            $plain = $encryptorForConfig->decrypt($ciphertext);
                        } catch (Throwable $e) {
                            // Never write the stripped ciphertext back as "plain" (a later encrypt run would
                            // encrypt the ciphertext): keep the stored value, <ENC> marker included, and report it.
                            $failures[] = sprintf('%s.%s (%s) [%s]', $table, $col, $this->formatIdValues($idValues), $e::class);

                            continue;
                        }
                        $updates[] = $platform->quoteIdentifier($col) . ' = ?';
                        $params[]  = $plain;
                        $types[]   = ParameterType::STRING;
                        ++$valueCounter;
                    }
                    if ($updates !== []) {
                        $setClause  = implode(', ', $updates);
                        $whereParts = [];
                        foreach ($idColumns as $idCol) {
                            $whereParts[] = $platform->quoteIdentifier($idCol) . ' = ?';
                            $params[]     = $idValues[$idCol];
                            $types[]      = ParameterType::STRING;
                        }
                        $sqlUpdate = 'UPDATE ' . $quotedTable . ' SET ' . $setClause . ' WHERE ' . implode(' AND ', $whereParts);
                        $conn->executeStatement($sqlUpdate, $params, $types);
                    }
                    $progressBar->advance(1);
                });

                $progressBar->finish();
                $output->writeln('');
            }
        }

        if ($failures !== []) {
            $output->writeln('' . PHP_EOL . '<error>' . count($failures) . ' value(s) could not be decrypted and were left unchanged (still encrypted):</error>');
            foreach (array_slice($failures, 0, self::MAX_REPORTED_FAILURES) as $failure) {
                $output->writeln('  - ' . $failure);
            }
            if (count($failures) > self::MAX_REPORTED_FAILURES) {
                $output->writeln(sprintf('  ... and %d more', count($failures) - self::MAX_REPORTED_FAILURES));
            }
            $output->writeln('Decryption finished with errors. Values decrypted: <info>' . $valueCounter . '</info>, failed: <error>' . count($failures) . '</error>. Check the encryptor / key of the listed values.');

            return self::FAILURE;
        }

        $output->writeln('' . PHP_EOL . 'Decryption finished. Values decrypted: <info>' . $valueCounter . '</info>.' . PHP_EOL . 'All values are now decrypted (raw SQL, no Doctrine events).');

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $idValues
     */
    private function formatIdValues(array $idValues): string
    {
        $parts = [];
        foreach ($idValues as $column => $value) {
            $parts[] = $column . '=' . (is_scalar($value) ? (string) $value : get_debug_type($value));
        }

        return implode(', ', $parts);
    }
}
