<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Console command to encrypt all currently plain values in the database for entities with Encrypted properties.
 *
 * Can process a single config (e.g. personal_data) or all configs when no argument is given.
 */
#[AsCommand(
    name: 'doctrine:encrypt:database',
    description: 'Encrypt whole database on tables which are not encrypted yet',
    hidden: false,
    aliases: ['doctrine:encrypt:database']
)]
class DoctrineEncryptDatabaseCommand extends AbstractCommand
{
    /**
     * Adds optional config and batchSize arguments.
     *
     * @return void
     */
    protected function configure(): void
    {
        $def = $this->getDefinition();
        if (!$def->hasArgument('config')) {
            $this->addArgument('config', InputArgument::OPTIONAL, 'Config name to use (e.g. personal_data, financial_data). If omitted, all configs are processed in turn.');
        }
        if (!$def->hasArgument('batchSize')) {
            $this->addArgument('batchSize', InputArgument::OPTIONAL, 'The update/flush batch size', 20);
        }
    }

    /**
     * Encrypts unencrypted values for the selected config(s), with optional progress bar and confirmation.
     *
     * @param InputInterface  $input  Console input
     * @param OutputInterface $output Console output
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = $this->getHelper('question');
        $batchSize = (int) $input->getArgument('batchSize');
        $configArg = $input->getArgument('config');

        $registry = $this->encryptorRegistry;
        $defaultConfigName = $registry?->getDefaultName();

        // Resolve which config(s) to process
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
                $configsToProcess = array_values(array_filter($registry->getConfigNames(), fn (string $n) => $n !== 'default'));
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
        $totalEntities = 0;
        foreach ($configsToProcess as $configName) {
            $metaDataArray = $this->getEncryptionableEntityMetaDataForConfig($configName, $defaultConfigName);
            $totalEntities += count($metaDataArray);
        }

        $encryptor = $registry ? $registry->get($configsToProcess[0]) : $this->subscriber->getEncryptor();
        if ($encryptor === null && $this->defaultEncryptor !== null) {
            $encryptor = $this->defaultEncryptor;
        }
        if ($encryptor === null) {
            $output->writeln('<error>No encryptor configured.</error>');

            return self::FAILURE;
        }

        $configList = implode(', ', $configsToProcess);
        $confirmationQuestion = new ConfirmationQuestion(
            '<question>' . $totalEntities . ' entity(ies) with encrypted properties for config(s): [' . $configList . '].' . PHP_EOL .
            'Encryptor(s) will be used per config. Wrong settings can mess up your data.' . PHP_EOL .
            'I advise you to make <bg=yellow;options=bold>a backup</bg=yellow;options=bold>. ' . PHP_EOL .
            'Continue? (y/yes)</question>',
            false
        );

        if (!$question->ask($input, $output, $confirmationQuestion)) {
            return AbstractCommand::SUCCESS;
        }

        $output->writeln('' . PHP_EOL . 'Encrypting all fields. This can take several minutes depending on the database size.');

        foreach ($configsToProcess as $configName) {
            $encryptorForConfig = $registry ? $registry->get($configName) : $this->defaultEncryptor;
            if ($encryptorForConfig === null) {
                continue;
            }
            $this->subscriber->setEncryptor($encryptorForConfig);

            $metaDataArray = $this->getEncryptionableEntityMetaDataForConfig($configName, $defaultConfigName);
            $output->writeln(sprintf('Config <comment>%s</comment> (%s)', $configName, $encryptorForConfig::class));

            foreach ($metaDataArray as $metaData) {
                $i = 0;
                $iterator = $this->getEntityIterator($metaData->name);
                $totalCount = $this->getTableCount($metaData->name);

                $output->writeln(sprintf('  Processing <comment>%s</comment>', $metaData->name));
                $progressBar = new ProgressBar($output, $totalCount);

                foreach ($iterator as $row) {
                    $entity = is_array($row) ? $row[0] : $row;
                    $this->subscriber->processFields($entity, true, $configName);

                    if (($i % $batchSize) === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }
                    $progressBar->advance(1);
                    $i++;
                }

                $progressBar->finish();
                $output->writeln('');
                $this->entityManager->flush();
            }

            $this->subscriber->setEncryptor(null);
        }

        $output->writeln('Encryption finished. Values encrypted: <info>' . $this->subscriber->encryptCounter . ' values</info>.' . PHP_EOL . 'All values are now encrypted.');

        return AbstractCommand::SUCCESS;
    }
}
