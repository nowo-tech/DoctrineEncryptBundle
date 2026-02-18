<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
// attributes
use Symfony\Component\Console\Output\OutputInterface;

/*
 * The DoctrineEncryptStatusCommand class retrieves metadata for entities and counts the number of
 * encrypted properties in each entity.
 **/
#[AsCommand(
    name: 'doctrine:encrypt:status',
    description: 'Get status of doctrine encrypt bundle and the database',
    hidden: false,
    aliases: ['doctrine:encrypt:status']
)]
class DoctrineEncryptStatusCommand extends AbstractCommand
{
    /**
     * This PHP function retrieves metadata for entities and counts the number of encrypted properties in
     * each entity.
     *
     * @param InputInterface input The `` parameter is an instance of the `InputInterface` class. It
     * represents the input arguments and options provided by the user when executing the command.
     * @param OutputInterface output The `` parameter is an instance of the `OutputInterface` class.
     * It is used to write output messages to the console or other output streams.
     *
     * @return int The method is returning an integer value. In this case, it is returning the constant
     * value `AbstractCommand::SUCCESS`, which typically indicates a successful execution of the command.
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
