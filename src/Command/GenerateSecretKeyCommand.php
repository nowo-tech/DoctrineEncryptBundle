<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Command;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use ParagonIE\Halite\KeyFactory;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\HttpKernel\KernelInterface;

use function dirname;
use function sprintf;

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
    aliases: ['doctrine:encrypt:generate-secret-key'],
)]
class GenerateSecretKeyCommand extends AbstractCommand
{
    /**
     * @param array<string, array{path: string|null, encryptor_class: string}> $keyPaths config alias => path (null when using secret_key_env_var with %env(APP_ENCRYPT_KEY)%), encryptor_class
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

    /**
     * Adds optional config argument to generate key for a single config.
     */
    protected function configure(): void
    {
        $def = $this->getDefinition();
        if (!$def->hasArgument('config')) {
            $this->addArgument('config', InputArgument::OPTIONAL, 'Config alias to generate key for (e.g. default, personal_data). If omitted, keys are created for all configs where the key file is missing.');
        }
        if (!$def->hasOption('force')) {
            $this->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing key file(s) without asking.');
        }
    }

    /**
     * Generates key file(s) for path-based configs or outputs key value for env-based configs.
     *
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     *
     * @return int Command exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = $this->kernel->getProjectDir();
        $resolved   = [];
        foreach ($this->keyPaths as $name => $info) {
            $path            = $info['path'] === null ? null : str_replace('%kernel.project_dir%', $projectDir, $info['path']);
            $resolved[$name] = ['path' => $path, 'encryptor_class' => $info['encryptor_class']];
        }

        $configArg = $input->getArgument('config');
        if ($configArg !== null && $configArg !== '') {
            if (!isset($resolved[$configArg])) {
                $output->writeln('<error>Unknown config "' . $configArg . '". Available: ' . implode(', ', array_keys($resolved)) . '</error>');

                return self::FAILURE;
            }

            return $this->generateForConfig($configArg, $resolved[$configArg], $input, $output, $input->getOption('force'));
        }

        $created = 0;
        foreach ($resolved as $name => $info) {
            if ($info['path'] === null) {
                $this->outputEnvKeyInfo($name, $info, $output);
                continue;
            }
            if ($this->supportsKeyGeneration($info['encryptor_class'])) {
                if (!file_exists($info['path'])) {
                    $this->createKey($info['path'], $info['encryptor_class'], $output);
                    $output->writeln(sprintf('  <info>%s</info>', $info['path']));
                    ++$created;
                } else {
                    $output->writeln(sprintf('<comment>Config "%s": key already exists at %s</comment>', $name, $info['path']));
                }
            } else {
                $output->writeln(sprintf('<comment>Config "%s": key generation not supported for encryptor "%s" (Halite, Defuse, MysqlAes).</comment>', $name, $info['encryptor_class']));
            }
        }

        if ($created > 0) {
            $output->writeln(sprintf('<info>Created %d key(s).</info>', $created));
        }

        return self::SUCCESS;
    }

    /**
     * Generates or outputs key for a single config.
     *
     * @param string $configName Config alias
     * @param array $info Resolved path and encryptor_class
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     */
    private function generateForConfig(string $configName, array $info, InputInterface $input, OutputInterface $output, bool $force = false): int
    {
        $path           = $info['path'];
        $encryptorClass = $info['encryptor_class'];

        if ($path === null) {
            $this->outputEnvKeyInfo($configName, $info, $output);

            return self::SUCCESS;
        }
        if (!$this->supportsKeyGeneration($encryptorClass)) {
            $output->writeln(sprintf('<error>Key generation is only supported for Halite, Defuse and MysqlAes. Config "%s" uses "%s".</error>', $configName, $encryptorClass));

            return self::FAILURE;
        }

        if (file_exists($path) && !$force) {
            $helper   = $this->getHelper('question');
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
        return $encryptorClass === 'Halite' || $encryptorClass === 'Defuse' || $encryptorClass === 'MysqlAes'
            || $encryptorClass === HaliteEncryptor::class || $encryptorClass === DefuseEncryptor::class
            || $encryptorClass === MysqlAesEncryptor::class;
    }

    /**
     * Creates and saves a new key file for the given path and encryptor type.
     *
     * @param string $path Key file path
     * @param string $encryptorClass Halite or Defuse (or FQCN)
     * @param OutputInterface $output Console output
     */
    private function createKey(string $path, string $encryptorClass, OutputInterface $output): void
    {
        $isHalite   = $encryptorClass === 'Halite' || $encryptorClass === HaliteEncryptor::class;
        $isMysqlAes = $encryptorClass === 'MysqlAes' || $encryptorClass === MysqlAesEncryptor::class;
        if ($isHalite) {
            $encryptionKey = KeyFactory::generateEncryptionKey();
            KeyFactory::save($encryptionKey, $path);
        } elseif ($isMysqlAes) {
            $passphrase = bin2hex(random_bytes(16));
            $dir        = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($path, $passphrase . "\n");
        } else {
            $key = bin2hex(random_bytes(255));
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            file_put_contents($path, $key);
        }
    }

    /**
     * When config has no path (uses secret_key_env_var with %env(APP_ENCRYPT_KEY)%): generate and emit the key value and indicate the variable is not set yet.
     */
    private function outputEnvKeyInfo(string $configName, array $info, OutputInterface $output): void
    {
        if (!$this->supportsKeyGeneration($info['encryptor_class'])) {
            $output->writeln(sprintf('<comment>Config "%s": key generation not supported for "%s".</comment>', $configName, $info['encryptor_class']));

            return;
        }

        $keyValue = $this->generateKeyValueForEnv($info['encryptor_class']);
        $output->writeln(sprintf('<info>Config "%s":</info>', $configName));
        $output->writeln('<comment>The encryption key variable is not set yet. Add it to your .env with the value below.</comment>');
        $output->writeln('');
        $output->writeln(sprintf('<comment>%s</comment>', $keyValue));
        $output->writeln('');
    }

    /**
     * Generates a key value in the same format as stored in file (Halite/Defuse: hex string).
     *
     * @param string $encryptorClass Halite or Defuse (or FQCN)
     *
     * @return string Key value suitable for env var
     */
    private function generateKeyValueForEnv(string $encryptorClass): string
    {
        $isHalite = $encryptorClass === 'Halite' || $encryptorClass === HaliteEncryptor::class;
        if ($isHalite) {
            $encryptionKey = KeyFactory::generateEncryptionKey();
            $tmp           = tempnam(sys_get_temp_dir(), 'halite_key_');
            if ($tmp === false) {
                throw new RuntimeException('Could not create temp file for key.');
            }
            try {
                KeyFactory::save($encryptionKey, $tmp);
                $value = trim(file_get_contents($tmp));

                return $value;
            } finally {
                @unlink($tmp);
            }
        }

        if ($encryptorClass === 'MysqlAes' || $encryptorClass === MysqlAesEncryptor::class) {
            return bin2hex(random_bytes(16));
        }

        return bin2hex(random_bytes(255));
    }
}
