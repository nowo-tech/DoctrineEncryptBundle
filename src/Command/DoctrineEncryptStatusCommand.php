<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function sprintf;

/**
 * Console command that lists entities, their encrypted properties with config, and the configured encryptor configs.
 */
#[AsCommand(name: 'doctrine:encrypt:status', description: 'Get status of doctrine encrypt bundle and the database', aliases: ['doctrine:encrypt:status'], hidden: false)]
class DoctrineEncryptStatusCommand extends AbstractCommand
{
    /**
     * @param array<string, array{path: string|null, encryptor_class: string}> $keyPaths
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        AttributeReader $attributeReader,
        DoctrineEncryptSubscriber $subscriber,
        ?EncryptorInterface $defaultEncryptor = null,
        ?EncryptorRegistry $encryptorRegistry = null,
        /**
         * Config name => [path, encryptor_class]; used to show encryptor class in configured configs.
         */
        private readonly array $keyPaths = []
    ) {
        parent::__construct($entityManager, $attributeReader, $subscriber, $defaultEncryptor, $encryptorRegistry);
    }

    /**
     * Outputs each entity with its encrypted properties and config, then a summary and the configured configs.
     *
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     *
     * @return int Command::SUCCESS
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $defaultConfigName = $this->encryptorRegistry?->getDefaultName() ?? 'default';

        $metaDataArray          = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $entitiesWithEncryption = 0;
        $totalCount             = 0;

        foreach ($metaDataArray as $metaData) {
            if (isset($metaData->isMappedSuperclass) && $metaData->isMappedSuperclass) {
                continue;
            }

            $propertiesWithConfig = $this->getEncryptionablePropertiesWithConfig($metaData, $defaultConfigName);
            $count                = count($propertiesWithConfig);

            if ($count > 0) {
                ++$entitiesWithEncryption;
                $totalCount += $count;
                $output->writeln(sprintf('<info>%s</info> has <info>%d</info> encrypted property(ies):', $metaData->name, $count));
                foreach ($propertiesWithConfig as ['property' => $property, 'config' => $config]) {
                    $output->writeln(sprintf('  - <comment>%s</comment> (config: <comment>%s</comment>)', $property->getName(), $config));
                }
            } else {
                $output->writeln(sprintf('<info>%s</info> has no properties which are encrypted.', $metaData->name));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>%d</info> entit(y/ies) with encryption, <info>%d</info> encrypted properties in total (out of <info>%d</info> entities).',
            $entitiesWithEncryption,
            $totalCount,
            count($metaDataArray),
        ));

        $this->outputConfiguredConfigs($output, $defaultConfigName);

        return AbstractCommand::SUCCESS;
    }

    private function outputConfiguredConfigs(OutputInterface $output, string $defaultConfigName): void
    {
        $output->writeln('');
        $output->writeln('<info>Configured encryptor configs:</info>');

        if (!$this->encryptorRegistry instanceof EncryptorRegistry) {
            $output->writeln('  (registry not available)');

            return;
        }

        $configNames = $this->encryptorRegistry->getConfigNames();
        // Exclude the 'default' alias when there are other configs (so we don't list "default" and "personal_data" when default is personal_data)
        $namesToShow = array_values(array_filter($configNames, static fn (string $name): bool => $name !== 'default' || count($configNames) === 1));

        if (count($namesToShow) === 0) {
            $output->writeln('  (none)');

            return;
        }

        $keyPaths = $this->keyPaths;
        foreach ($namesToShow as $name) {
            $encryptorClass = $keyPaths[$name]['encryptor_class'] ?? null;
            $label          = $encryptorClass ? sprintf('%s (%s)', $name, $encryptorClass) : $name;
            $defaultLabel   = $name === $defaultConfigName ? ' [default]' : '';
            $output->writeln(sprintf('  - %s%s', $label, $defaultLabel));
        }
    }
}
