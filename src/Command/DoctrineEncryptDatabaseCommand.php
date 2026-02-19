<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
// attributes
use Symfony\Component\Console\Question\ConfirmationQuestion;

/*
 * The DoctrineEncryptDatabaseCommand class is a PHP command that encrypts fields in a database using a
 * specified encryptor.
 **/
#[AsCommand(
    name: 'doctrine:encrypt:database',
    description: 'Encrypt whole database on tables which are not encrypted yet',
    hidden: false,
    aliases: ['doctrine:encrypt:database']
)]
class DoctrineEncryptDatabaseCommand extends AbstractCommand
{
    /**
     * The function "configure" adds optional arguments for the encryptor and batch size to the command.
     */
    protected function configure(): void
    {
        $this
          ->addArgument('encryptor', InputArgument::OPTIONAL, 'The encryptor you want to decrypt the database with')
          ->addArgument('batchSize', InputArgument::OPTIONAL, 'The update/flush batch size', 20);
    }

    /**
     * This PHP function encrypts fields in a database using a specified encryptor.
     *
     * @param InputInterface input The `` parameter is an instance of the `InputInterface` class,
     * which represents the input arguments and options provided by the user when executing the command.
     * @param OutputInterface output The `` parameter is an instance of the `OutputInterface` class,
     * which is used to write output to the console. It provides methods like `writeln()` to write text to
     * the console output.
     *
     * @return int The method is returning an integer value, which is either `AbstractCommand::SUCCESS` or
     * a value specified by the programmer.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get entity manager, question helper, subscriber service and annotation reader
        $question = $this->getHelper('question');
        $batchSize = $input->getArgument('batchSize');

        // Get list of supported encryptors
        $supportedExtensions = DoctrineEncryptExtension::SUPPORTED_ENCRYPTOR_CLASSES;

        // If encryptor has been set use that encryptor else use default
        if ($input->getArgument('encryptor')) {
            if (isset($supportedExtensions[$input->getArgument('encryptor')])) {
                $reflection = new \ReflectionClass($supportedExtensions[$input->getArgument('encryptor')]);
                $encryptor = $reflection->newInstance();
                $this->subscriber->setEncryptor($encryptor);
            } else {
                if (class_exists($input->getArgument('encryptor'))) {
                    $reflection = new \ReflectionClass($input->getArgument('encryptor'));
                    $this->subscriber->setEncryptor($reflection->newInstance());
                } else {
                    $output->writeln('Given encryptor does not exists');
                    $output->writeln('Supported encryptors: ' . implode(', ', array_keys($supportedExtensions)));

                    return self::FAILURE;
                }
            }
        }

        // Get entity manager metadata
        $metaDataArray = $this->getEncryptionableEntityMetaData();
        $confirmationQuestion = new ConfirmationQuestion(
            '<question>' . count($metaDataArray) . ' entities found which are containing properties with the encryption tag.' . PHP_EOL . '' .
            'Which are going to be encrypted with [' . get_class($this->subscriber->getEncryptor()) . ']. ' . PHP_EOL . '' .
            'Wrong settings can mess up your data and it will be unrecoverable. ' . PHP_EOL . '' .
            'I advise you to make <bg=yellow;options=bold>a backup</bg=yellow;options=bold>. ' . PHP_EOL . '' .
            'Continue with this action? (y/yes)</question>',
            false
        );

        if (!$question->ask($input, $output, $confirmationQuestion)) {
            return AbstractCommand::SUCCESS;
        }

        // Start decrypting database
        $output->writeln('' . PHP_EOL . 'Encrypting all fields can take up to several minutes depending on the database size.');

        // Loop through entity manager meta data
        foreach ($metaDataArray as $metaData) {
            $i = 0;
            $iterator = $this->getEntityIterator($metaData->name);
            $totalCount = $this->getTableCount($metaData->name);

            $output->writeln(sprintf('Processing <comment>%s</comment>', $metaData->name));
            $progressBar = new ProgressBar($output, $totalCount);
            foreach ($iterator as $row) {
                $this->subscriber->processFields((is_array($row) ? $row[0] : $row));

                if (($i % $batchSize) === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    $progressBar->advance($batchSize);
                }
                $i++;
            }

            $progressBar->finish();
            $output->writeln('');
            $this->entityManager->flush();
        }

        // Say it is finished
        $output->writeln('Encryption finished. Values encrypted: <info>' . $this->subscriber->encryptCounter . ' values</info>.' . PHP_EOL . 'All values are now encrypted.');
        return AbstractCommand::SUCCESS;
    }
}
