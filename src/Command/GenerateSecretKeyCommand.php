<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use ParagonIE\Halite\KeyFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Generates encryption keys for Halite and Defuse configs.
 *
 * - Without argument: checks all configs; creates a key in each secret_directory_path when missing.
 * - With config argument: only creates/overwrites the key for that config (asks confirmation if key already exists).
 */
#[AsCommand(
    name: 'doctrine:encrypt:generate-secret-key',
    description: 'Generate encryption keys for Halite/Defuse configs (all configs or a given alias)',
    hidden: false,
    aliases: ['doctrine:encrypt:generate-secret-key']
)]
class GenerateSecretKeyCommand extends AbstractCommand
{
    /**
     * @param array<string, array{path: string, encryptor_class: string}> $keyPaths config alias => path (may contain %kernel.project_dir%) and encryptor_class (Halite|Defuse)
     */
    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Nowo\DoctrineEncryptBundle\Mapping\AttributeReader $attributeReader,
        \Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber $subscriber,
        private readonly KernelInterface $kernel,
        private readonly array $keyPaths
    ) {
        parent::__construct($entityManager, $attributeReader, $subscriber);
    }

    protected function configure(): void
    {
        $def = $this->getDefinition();
        if (!$def->hasArgument('config')) {
            $this->addArgument('config', InputArgument::OPTIONAL, 'Config alias to generate key for (e.g. default, personal_data). If omitted, keys are created for all configs where the key file is missing.');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = $this->kernel->getProjectDir();
        $resolved = [];
        foreach ($this->keyPaths as $name => $info) {
            $path = str_replace('%kernel.project_dir%', $projectDir, $info['path']);
            $resolved[$name] = ['path' => $path, 'encryptor_class' => $info['encryptor_class']];
        }

        $configArg = $input->getArgument('config');
        if ($configArg !== null && $configArg !== '') {
            if (!isset($resolved[$configArg])) {
                $output->writeln('<error>Unknown config "' . $configArg . '". Available: ' . implode(', ', array_keys($resolved)) . '</error>');
                return self::FAILURE;
            }
            return $this->generateForConfig($configArg, $resolved[$configArg], $input, $output);
        }

        $created = 0;
        foreach ($resolved as $name => $info) {
            if ($this->supportsKeyGeneration($info['encryptor_class'])) {
                if (!file_exists($info['path'])) {
                    $this->createKey($info['path'], $info['encryptor_class'], $output);
                    $output->writeln(sprintf('  <info>%s</info>', $info['path']));
                    $created++;
                } else {
                    $output->writeln(sprintf('<comment>Config "%s": key already exists at %s</comment>', $name, $info['path']));
                }
            } else {
                $output->writeln(sprintf('<comment>Config "%s": key generation not supported for encryptor "%s" (only Halite and Defuse).</comment>', $name, $info['encryptor_class']));
            }
        }

        if ($created > 0) {
            $output->writeln(sprintf('<info>Created %d key(s).</info>', $created));
        }
        return self::SUCCESS;
    }

    private function generateForConfig(string $configName, array $info, InputInterface $input, OutputInterface $output): int
    {
        $path = $info['path'];
        $encryptorClass = $info['encryptor_class'];

        if (!$this->supportsKeyGeneration($encryptorClass)) {
            $output->writeln(sprintf('<error>Key generation is only supported for Halite and Defuse. Config "%s" uses "%s".</error>', $configName, $encryptorClass));
            return self::FAILURE;
        }

        if (file_exists($path)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(sprintf('Key file already exists at %s. Overwrite? (y/yes) ', $path), false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');
                return self::SUCCESS;
            }
        }

        $this->createKey($path, $encryptorClass, $output);
        $output->writeln(sprintf('<info>Key for config "%s" saved to: %s</info>', $configName, $path));
        return self::SUCCESS;
    }

    private function supportsKeyGeneration(string $encryptorClass): bool
    {
        return $encryptorClass === 'Halite' || $encryptorClass === 'Defuse'
            || $encryptorClass === HaliteEncryptor::class || $encryptorClass === DefuseEncryptor::class;
    }

    private function createKey(string $path, string $encryptorClass, OutputInterface $output): void
    {
        $isHalite = $encryptorClass === 'Halite' || $encryptorClass === HaliteEncryptor::class;
        if ($isHalite) {
            $encryptionKey = KeyFactory::generateEncryptionKey();
            KeyFactory::save($encryptionKey, $path);
        } else {
            $key = bin2hex(random_bytes(255));
            $dir = \dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($path, $key);
        }
    }
}
