<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
// attributes
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command that lists entities and the count of encrypted properties per entity.
 */
#[AsCommand(
    name: 'doctrine:encrypt:status',
    description: 'Get status of doctrine encrypt bundle and the database',
    hidden: false,
    aliases: ['doctrine:encrypt:status']
)]
class DoctrineEncryptStatusCommand extends AbstractCommand
{
    /**
     * Outputs each entity and its encrypted property count, then a total summary.
     *
     * @param InputInterface  $input  Console input
     * @param OutputInterface $output Console output
     * @return int Command::SUCCESS
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        $totalCount = 0;
        foreach ($metaDataArray as $metaData) {
            if (isset($metaData->isMappedSuperclass) && $metaData->isMappedSuperclass) {
                continue;
            }

            $count = 0;
            $encryptedPropertiesCount = count($this->getEncryptionableProperties($metaData));
            if ($encryptedPropertiesCount > 0) {
                $totalCount += $encryptedPropertiesCount;
                $count += $encryptedPropertiesCount;
            }

            if ($count > 0) {
                $output->writeln(sprintf('<info>%s</info> has <info>%d</info> properties which are encrypted.', $metaData->name, $count));
            } else {
                $output->writeln(sprintf('<info>%s</info> has no properties which are encrypted.', $metaData->name));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>%d</info> entities found which are containing <info>%d</info> encrypted properties.', count($metaDataArray), $totalCount));
        return AbstractCommand::SUCCESS;
    }
}
