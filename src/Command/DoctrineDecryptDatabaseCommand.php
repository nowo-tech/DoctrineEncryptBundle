<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'doctrine:decrypt:database',
    description: 'Decrypt whole database on tables which are encrypted',
    hidden: false,
    aliases: ['doctrine:decrypt:database']
)]
class DoctrineDecryptDatabaseCommand extends AbstractCommand
{
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = $this->getHelper('question');
        $batchSize = (int) $input->getArgument('batchSize');
        $configArg = $input->getArgument('config');

        $registry = $this->encryptorRegistry;
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

        $configList = implode(', ', $configsToProcess);
        $confirmationQuestion = new ConfirmationQuestion(
            '<question>' . count($configsToProcess) . ' config(s): [' . $configList . '], ' . $propertyCount . ' encrypted properties.' . PHP_EOL .
            'Which are going to be decrypted. Wrong settings can mess up your data.' . PHP_EOL .
            'I advise you to make <bg=yellow;options=bold>a backup</bg=yellow;options=bold>. ' . PHP_EOL .
            'Continue? (y/yes)</question>',
            false
        );

        if (!$question->ask($input, $output, $confirmationQuestion)) {
            return AbstractCommand::SUCCESS;
        }

        $output->writeln('' . PHP_EOL . 'Decrypting all fields. This can take several minutes depending on the database size.');

        $valueCounter = 0;

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
                $propertiesForConfig = $this->getEncryptionablePropertiesForConfig($metaData, $configName, $defaultConfigName);

                $output->writeln(sprintf('  Processing <comment>%s</comment>', $metaData->name));
                $progressBar = new ProgressBar($output, $totalCount);

                foreach ($iterator as $row) {
                    $entity = $row[0];
                    $entityReflectionClass = new \ReflectionClass($entity);

                    foreach ($propertiesForConfig as $property) {
                        $methodName = ucfirst($property->getName());
                        $getter = 'get' . $methodName;
                        $setter = 'set' . $methodName;

                        if ($entityReflectionClass->hasMethod($getter) && $entityReflectionClass->hasMethod($setter)) {
                            $unencrypted = $entity->$getter();
                            $entity->$setter($unencrypted);
                            $valueCounter++;
                        }
                    }

                    $this->subscriber->setEncryptor(null);
                    $this->entityManager->persist($entity);

                    if (($i % $batchSize) === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }
                    $progressBar->advance(1);
                    $i++;

                    $this->subscriber->setEncryptor($encryptorForConfig);
                }

                $progressBar->finish();
                $output->writeln('');
                $this->subscriber->setEncryptor(null);
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->subscriber->setEncryptor($encryptorForConfig);
            }
        }

        $output->writeln('' . PHP_EOL . 'Decryption finished. Values found: <info>' . $valueCounter . '</info>, decrypted: <info>' . $this->subscriber->decryptCounter . '</info>.' . PHP_EOL . 'All values are now decrypted.');

        return AbstractCommand::SUCCESS;
    }
}
