<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\DBAL\ParameterType;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Throwable;

use function count;
use function in_array;
use function sprintf;
use function strlen;

use const PHP_EOL;

/**
 * Decrypts all encrypted values in the database using raw SQL (no Doctrine lifecycle events).
 *
 * Can process a single config (e.g. personal_data) or all configs when no argument is given.
 */
#[AsCommand(
    name: 'doctrine:decrypt:database',
    description: 'Decrypt whole database on tables which are encrypted',
    hidden: false,
    aliases: ['doctrine:decrypt:database'],
)]
class DoctrineDecryptDatabaseCommand extends AbstractCommand
{
    private const ENCRYPTION_MARKER = DoctrineEncryptSubscriber::ENCRYPTION_MARKER;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Nowo\DoctrineEncryptBundle\Mapping\AttributeReader $attributeReader,
        DoctrineEncryptSubscriber $subscriber,
        ?\Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface $defaultEncryptor,
        ?\Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry $encryptorRegistry,
        int $defaultBatchSize = 5
    ) {
        parent::__construct($entityManager, $attributeReader, $subscriber, $defaultEncryptor, $encryptorRegistry);
        $this->defaultBatchSize = $defaultBatchSize;
    }

    private int $defaultBatchSize = 5;

    protected function configure(): void
    {
        $def = $this->getDefinition();
        if (!$def->hasArgument('config')) {
            $this->addArgument('config', InputArgument::OPTIONAL, 'Config name to use (e.g. personal_data, financial_data). If omitted, all configs are processed in turn.');
        }
        if (!$def->hasArgument('batchSize')) {
            $this->addArgument('batchSize', InputArgument::OPTIONAL, 'The update batch size (smaller = less memory; configurable via nowo_doctrine_encrypt.batch_size)', $this->defaultBatchSize);
        }
        if (!$def->hasOption('force')) {
            $this->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation (use with --no-interaction).');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question  = $this->getHelper('question');
        $batchSize = (int) $input->getArgument('batchSize');
        $configArg = $input->getArgument('config');

        $registry          = $this->encryptorRegistry;
        $defaultConfigName = $registry?->getDefaultName();

        if ($configArg !== null && $configArg !== '') {
            if ($registry === null || !$registry->has($configArg)) {
                $available = $registry ? implode(', ', $registry->getConfigNames()) : 'default';
                $output->writeln('<error>Unknown config "' . $configArg . '". Available: ' . $available . '</error>');

                return self::FAILURE;
            }
            $configsToProcess = [$configArg];
        } else {
            if ($registry === null) {
                $configsToProcess = $defaultConfigName !== null ? [$defaultConfigName] : [];
            } else {
                $configsToProcess = array_values(array_filter($registry->getConfigNames(), static fn (string $n) => $n !== 'default'));
                if ($configsToProcess === []) {
                    $configsToProcess = [$registry->getDefaultName()];
                }
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

        $encryptor = $registry ? $registry->get($configsToProcess[0]) : $this->subscriber->getEncryptor();
        if ($encryptor === null && $this->defaultEncryptor !== null) {
            $encryptor = $this->defaultEncryptor;
        }
        if ($encryptor === null) {
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

        $proceed = $input->getOption('force') || $question->ask($input, $output, $confirmationQuestion);
        if (!$proceed) {
            return self::SUCCESS;
        }

        $output->writeln('' . PHP_EOL . 'Decrypting all fields with raw SQL (no Doctrine events). This can take several minutes depending on the database size.');

        $conn         = $this->entityManager->getConnection();
        $platform     = $conn->getDatabasePlatform();
        $valueCounter = 0;

        foreach ($configsToProcess as $configName) {
            $encryptorForConfig = $registry ? $registry->get($configName) : $this->defaultEncryptor;
            if ($encryptorForConfig === null) {
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
                $quotedIdCols  = array_map(static fn (string $c) => $platform->quoteIdentifier($c), $idColumns);
                $quotedEncCols = array_map(static fn (string $c) => $platform->quoteIdentifier($c), $encColumns);

                // Select only ID columns + encrypted columns for this config (each config is independent)
                $selectCols = array_merge($quotedIdCols, $quotedEncCols);
                $sqlSelect  = 'SELECT ' . implode(', ', $selectCols) . ' FROM ' . $quotedTable;
                $result     = $conn->executeQuery($sqlSelect);
                $rows       = $result->fetchAllAssociative();
                $totalCount = count($rows);

                $output->writeln(sprintf('  Processing <comment>%s</comment> (%s)', $metaData->name, $table));
                $progressBar = new ProgressBar($output, $totalCount);

                foreach ($rows as $row) {
                    $idValues = [];
                    foreach ($idColumns as $idCol) {
                        $idValues[$idCol] = $this->getRowValue($row, $idCol);
                    }
                    if (in_array(null, $idValues, true)) {
                        $progressBar->advance(1);
                        continue;
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
                            $plain = $ciphertext;
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
                }

                $progressBar->finish();
                $output->writeln('');
            }
        }

        $output->writeln('' . PHP_EOL . 'Decryption finished. Values decrypted: <info>' . $valueCounter . '</info>.' . PHP_EOL . 'All values are now decrypted (raw SQL, no Doctrine events).');

        return self::SUCCESS;
    }
}
