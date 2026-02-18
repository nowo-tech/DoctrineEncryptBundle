<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use ParagonIE\Halite\KeyFactory;
use Symfony\Component\Console\Attribute\AsCommand;
//
use Symfony\Component\Console\Input\InputInterface;
// attributes
use Symfony\Component\Console\Output\OutputInterface;

/*
 * The GenerateSecretKeyCommand class checks the type of encryptor used and generates and saves an
 * encryption key if it is a HaliteEncryptor.
 **/
#[AsCommand(
    name: 'doctrine:encrypt:generate-secret-key',
    description: 'Generate and save a Halite secret key for encryption',
    hidden: false,
    aliases: ['doctrine:encrypt:generate-secret-key']
)]
class GenerateSecretKeyCommand extends AbstractCommand
{
    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Nowo\DoctrineEncryptBundle\Mapping\AttributeReader $attributeReader,
        \Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber $subscriber,
        private readonly string $secretKeyPath
    ) {
        parent::__construct($entityManager, $attributeReader, $subscriber);
    }
    /**
     * The function checks the type of encryptor used and generates and saves an encryption key if it is a
     * HaliteEncryptor.
     *
     * @param InputInterface input An instance of the InputInterface class, which represents the input
     * arguments and options for the command.
     * @param OutputInterface output The `` parameter is an instance of the `OutputInterface` class.
     * It is used to write output messages to the console or other output streams. You can use methods like
     * `writeln()` or `write()` on the `` object to display messages.
     *
     * @return int The method is returning the value of the constant `AbstractCommand::SUCCESS`, which is
     * typically used to indicate a successful execution of the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $encryptor = $this->subscriber->getEncryptor();
        if ($encryptor === null || get_class($encryptor) !== HaliteEncryptor::class) {
            $output->writeln('<comment>Secret key generation is only supported for Halite encryptor.</comment>');

            return self::SUCCESS;
        }
        $encryptionKey = KeyFactory::generateEncryptionKey();
        KeyFactory::save($encryptionKey, $this->secretKeyPath);
        $output->writeln('<info>Halite secret key saved to: ' . $this->secretKeyPath . '</info>');

        return self::SUCCESS;
    }
}
