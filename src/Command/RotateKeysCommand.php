<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Rotates encryption keys: optional backup (DB + key files), full decrypt, key change (files or .env), then re-encrypt.
 *
 * Each step asks for confirmation unless --no-interaction is used. Use --backup to back up the database and key files before rotating.
 */
#[AsCommand(
    name: 'doctrine:encrypt:rotate-keys',
    description: 'Rotate encryption keys: backup (DB + keys), decrypt DB, change keys, re-encrypt',
    hidden: false,
    aliases: ['doctrine:encrypt:rotate-keys']
)]
class RotateKeysCommand extends AbstractCommand
{
    private const BACKUP_DIR_PREFIX = 'encrypt_rotation_backup_';
    private const BACKUP_KEYS_SUBDIR = 'keys';

    /**
     * @param array<string, array{path: string|null, encryptor_class: string}> $keyPaths
     */
    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Nowo\DoctrineEncryptBundle\Mapping\AttributeReader $attributeReader,
        \Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber $subscriber,
        ?\Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface $defaultEncryptor,
        ?EncryptorRegistry $encryptorRegistry,
        private readonly KernelInterface $kernel,
        private readonly array $keyPaths
    ) {
        parent::__construct($entityManager, $attributeReader, $subscriber, $defaultEncryptor, $encryptorRegistry);
    }

    protected function configure(): void
    {
        $def = $this->getDefinition();
        if (!$def->hasOption('backup')) {
            $this->addOption('backup', null, InputOption::VALUE_NONE, 'Back up database and current key files to a timestamped directory before rotating.');
        }
        if (!$def->hasOption('backup-db-cmd')) {
            $this->addOption('backup-db-cmd', null, InputOption::VALUE_REQUIRED, 'Symfony command to run for DB backup (e.g. app:backup-db). Only used when --backup is set. For SQLite the DB file is copied automatically.');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $noInteraction = $input->getOption('no-interaction');
        $doBackup = $input->getOption('backup');
        $backupDbCmd = $input->getOption('backup-db-cmd');
        $projectDir = $this->kernel->getProjectDir();

        $resolved = $this->resolveKeyPaths($projectDir);
        $configNames = $this->encryptorRegistry?->getConfigNames() ?? [];
        $configNames = array_values(array_filter($configNames, static fn (string $n) => $n !== 'default' || count($configNames) === 1));

        if ($configNames === []) {
            $output->writeln('<error>No encryptor configs found. Configure nowo_doctrine_encrypt in config.</error>');
            return self::FAILURE;
        }

        $output->writeln('<info>Key rotation has 4 steps. You will be asked to confirm before each step so you can verify the result.</info>');
        $output->writeln('');
        if ($doBackup) {
            $output->writeln('  Step 1: Back up database and key file(s).');
        }
        $output->writeln('  ' . ($doBackup ? 'Step 2' : 'Step 1') . ': Decrypt only (data stays in plain text until you confirm next step).');
        $output->writeln('  ' . ($doBackup ? 'Step 3' : 'Step 2') . ': Change keys (new key files or .env).');
        $output->writeln('  ' . ($doBackup ? 'Step 4' : 'Step 3') . ': Re-encrypt only (with the new keys).');
        $output->writeln('');

        // Step 1: Backup (optional) – database + key files
        if ($doBackup) {
            if (!$this->confirm($input, $output, $noInteraction, 'Run step 1: backup database and key files?', false)) {
                $output->writeln('<comment>Rotation aborted.</comment>');
                return self::SUCCESS;
            }
            $backupDir = $this->runBackup($resolved, $projectDir, $output, $input, $backupDbCmd);
            if ($backupDir !== null) {
                $output->writeln(sprintf('<info>Backup completed: %s</info>', $backupDir));
            } else {
                $output->writeln('<comment>Backup had no effect or failed. Continuing.</comment>');
            }
            $output->writeln('');
        }

        // Step 2: Decrypt only – no re-encryption until you confirm later
        $stepDecrypt = $doBackup ? 'Step 2' : 'Step 1';
        if (!$this->confirm($input, $output, $noInteraction, $stepDecrypt . ': Decrypt database only (data will stay in plain text until you confirm key change and re-encrypt). Continue?', false)) {
            $output->writeln('<comment>Rotation aborted.</comment>');
            return self::SUCCESS;
        }
        $output->writeln('<info>Decrypting database (decrypt only, no re-encryption)...</info>');
        $exitCode = $this->runCommand($input, $output, 'doctrine:decrypt:database', ['--no-interaction' => true, '--force' => true]);
        if ($exitCode !== self::SUCCESS) {
            $output->writeln('<error>Decrypt failed. Aborting.</error>');
            return $exitCode;
        }
        $output->writeln('');
        $output->writeln('----------');
        $output->writeln('<info>Step 1 (decrypt) finished. Database is in plain text. Verify the data if you need, then answer the next question to continue.</info>');
        $output->writeln('----------');
        $output->writeln('');
        $stepKeys = $doBackup ? 'Step 3' : 'Step 2';
        if (!$this->confirm($input, $output, $noInteraction, $stepKeys . ': Change keys (generate new key files / update .env)? After this, data is still in plain text.', false)) {
            $output->writeln('<comment>Rotation paused. Database is decrypted. Run doctrine:encrypt:database when ready, or re-run rotate-keys.</comment>');
            return self::SUCCESS;
        }
        $output->writeln('');

        // Step 3: Change keys
        $envConfigs = [];
        $keysGenerated = 0;
        foreach ($configNames as $name) {
            $info = $resolved[$name] ?? null;
            if ($info === null) {
                continue;
            }
            if ($info['path'] === null) {
                $envConfigs[] = $name;
                continue;
            }
            $output->writeln(sprintf('  Generating new key for config <comment>%s</comment>...', $name));
            $exitCode = $this->runCommand($input, $output, 'doctrine:encrypt:generate-secret-key', [
                'config' => $name,
                '--no-interaction' => true,
                '--force' => true,
            ]);
            if ($exitCode !== self::SUCCESS) {
                $output->writeln(sprintf('<error>Failed to generate key for config "%s". Aborting.</error>', $name));
                return $exitCode;
            }
            $output->writeln(sprintf('  <info>New key written to: %s</info>', $info['path']));
            $keysGenerated++;
        }
        if ($keysGenerated > 0) {
            $output->writeln(sprintf('<info>%d key file(s) have been replaced with new keys.</info>', $keysGenerated));
        }

        if ($envConfigs !== []) {
            $output->writeln('<comment>Config(s) using env var: ' . implode(', ', $envConfigs) . '</comment>');
            $output->writeln('Update the encryption key in your .env (e.g. APP_ENCRYPT_KEY), then continue.');
            if (!$noInteraction) {
                $helper = $this->getHelper('question');
                $q = new Question('Press Enter when .env is updated to continue (or Ctrl+C to abort)...', '');
                $helper->ask($input, $output, $q);
            } else {
                $output->writeln('<comment>Running with --no-interaction: ensure .env is updated before re-encryption.</comment>');
            }
        }
        $output->writeln('');
        $output->writeln('----------');
        $output->writeln('<info>Step 2 (key change) finished. New key files are in place. Data is still in plain text. Answer the next question to re-encrypt.</info>');
        $output->writeln('----------');
        $output->writeln('');
        $stepEncrypt = $doBackup ? 'Step 4' : 'Step 3';
        if (!$this->confirm($input, $output, $noInteraction, $stepEncrypt . ': Re-encrypt database with the new keys only. Continue?', false)) {
            $output->writeln('<comment>Rotation paused. Database is decrypted. Run doctrine:encrypt:database when ready.</comment>');
            return self::SUCCESS;
        }
        $output->writeln('');

        // Step 4: Re-encrypt only
        $output->writeln('<info>Re-encrypting database (encrypt only, with the new keys)...</info>');
        $exitCode = $this->runCommand($input, $output, 'doctrine:encrypt:database', ['--no-interaction' => true, '--force' => true]);
        if ($exitCode !== self::SUCCESS) {
            $output->writeln('<error>Re-encrypt failed. Restore key backup and run doctrine:decrypt:database if needed.</error>');
            return $exitCode;
        }

        $output->writeln('');
        $output->writeln('<info>Key rotation completed successfully.</info>');
        return self::SUCCESS;
    }

    /**
     * @return array<string, array{path: string|null, encryptor_class: string}>
     */
    private function resolveKeyPaths(string $projectDir): array
    {
        $resolved = [];
        foreach ($this->keyPaths as $name => $info) {
            $path = $info['path'] === null ? null : str_replace('%kernel.project_dir%', $projectDir, $info['path']);
            $resolved[$name] = ['path' => $path, 'encryptor_class' => $info['encryptor_class']];
        }
        return $resolved;
    }

    /**
     * Backs up database (SQLite file copy or run --backup-db-cmd) and key files into a timestamped directory.
     *
     * @param array<string, array{path: string|null, encryptor_class: string}> $resolved
     */
    private function runBackup(array $resolved, string $projectDir, OutputInterface $output, InputInterface $input, ?string $backupDbCmd): ?string
    {
        $varDir = $projectDir . '/var';
        if (!is_dir($varDir)) {
            @mkdir($varDir, 0o755, true);
        }
        $timestamp = date('Y-m-d_His');
        $backupDir = $varDir . '/' . self::BACKUP_DIR_PREFIX . $timestamp;
        if (!@mkdir($backupDir, 0o755, true) && !is_dir($backupDir)) {
            return null;
        }

        $hasBackup = false;

        // 1) Database backup
        $conn = $this->entityManager->getConnection();
        $params = $conn->getParams();
        $driver = $params['driver'] ?? '';

        if ($driver === 'pdo_sqlite' && !empty($params['path'])) {
            $dbPath = $params['path'];
            if (str_starts_with($dbPath, 'file:')) {
                $dbPath = substr($dbPath, 5);
            }
            $dbPath = str_replace('%kernel.project_dir%', $projectDir, $dbPath);
            if (file_exists($dbPath)) {
                $destDb = $backupDir . '/database.sqlite.gz';
                if ($this->copyFileGzip($dbPath, $destDb)) {
                    $output->writeln(sprintf('  <info>Database (SQLite) backed up compressed to: %s</info>', $destDb));
                    $hasBackup = true;
                }
            }
        } elseif ($backupDbCmd !== null && $backupDbCmd !== '') {
            $output->writeln(sprintf('  Running database backup command: <comment>%s</comment>', $backupDbCmd));
            $exitCode = $this->runCommand($input, $output, $backupDbCmd, ['--no-interaction' => true]);
            if ($exitCode === self::SUCCESS) {
                $hasBackup = true;
            } else {
                $output->writeln(sprintf('  <comment>Database backup command exited with code %d. Ensure your backup command writes to %s or another safe path.</comment>', $exitCode, $backupDir));
            }
        } else {
            $output->writeln('  <comment>Database not backed up automatically (not SQLite and no --backup-db-cmd). Back up the DB manually if needed.</comment>');
        }

        // 2) Key files backup
        $keysDir = $backupDir . '/' . self::BACKUP_KEYS_SUBDIR;
        if (!@mkdir($keysDir, 0o755, true) && !is_dir($keysDir)) {
            return $hasBackup ? $backupDir : null;
        }
        $copied = 0;
        foreach ($resolved as $name => $info) {
            $path = $info['path'];
            if ($path === null || !file_exists($path)) {
                continue;
            }
            $basename = basename($path);
            $dest = $keysDir . '/' . $name . '_' . $basename;
            if (@copy($path, $dest)) {
                $copied++;
            }
        }
        if ($copied > 0) {
            $output->writeln(sprintf('  <info>Key files backed up to: %s</info>', $keysDir));
            $hasBackup = true;
        }

        return $hasBackup ? $backupDir : null;
    }

    /**
     * Copies a file to a gzip-compressed destination.
     */
    private function copyFileGzip(string $sourcePath, string $destGzPath): bool
    {
        $fp = @fopen($sourcePath, 'rb');
        if ($fp === false) {
            return false;
        }
        $gz = @gzopen($destGzPath, 'wb9');
        if ($gz === false) {
            fclose($fp);
            return false;
        }
        $ok = true;
        while (!feof($fp)) {
            $chunk = fread($fp, 8192);
            if ($chunk !== false && gzwrite($gz, $chunk) === 0) {
                $ok = false;
                break;
            }
        }
        gzclose($gz);
        fclose($fp);

        return $ok;
    }

    private function confirm(InputInterface $input, OutputInterface $output, bool $noInteraction, string $message, bool $default): bool
    {
        if ($noInteraction) {
            return true;
        }
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('<question>' . $message . '</question> [y/N] ', $default);
        return (bool) $helper->ask($input, $output, $question);
    }

    /**
     * Run a subcommand and return its exit code. Uses find()->run() so the parent command
     * continues after the subcommand finishes (avoid Application::run() exiting the process).
     *
     * @param array<string, mixed> $args
     */
    private function runCommand(InputInterface $input, OutputInterface $output, string $commandName, array $args = []): int
    {
        $app = $this->getApplication();
        if ($app === null) {
            return self::FAILURE;
        }
        $args['command'] = $commandName;
        $childInput = new ArrayInput($args);
        $childInput->setInteractive($input->isInteractive());
        $command = $app->find($commandName);
        return $command->run($childInput, $output);
    }
}
