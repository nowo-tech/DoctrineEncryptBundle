<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand as EncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\GenerateSecretKeyCommand;
use Nowo\DoctrineEncryptBundle\Command\RotateKeysCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

class RotateKeysCommandTest extends TestCase
{
    private function createRotateCommand(
        array $keyPaths,
        string $projectDir = '/tmp',
        ?EncryptorRegistry $registry = null
    ): RotateKeysCommand {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $registry ??= new EncryptorRegistry(
            ['default' => $this->createMock(EncryptorInterface::class)],
            'default'
        );
        $subscriber = new DoctrineEncryptSubscriber($registry);

        return new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, $registry, $kernel, $keyPaths);
    }

    /** Registry and subscriber must be null: an empty EncryptorRegistry would make DoctrineEncryptSubscriber::__construct() call getDefault() and throw. */
    public function testExecuteReturnsFailureWhenNoConfigs(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/tmp');
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $subscriber = new DoctrineEncryptSubscriber(null);
        $command = new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, null, $kernel, []);
        $command->setApplication(new Application());
        $tester = new CommandTester($command);

        $tester->execute(['--no-interaction' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('No encryptor configs found', $tester->getDisplay());
    }

    public function testExecuteRunsFullRotationWithNoInteractionAndNoBackup(): void
    {
        $keyPath = sys_get_temp_dir() . '/nowo-rotate-test-' . uniqid() . '.key';
        if (file_exists($keyPath)) {
            @unlink($keyPath);
        }
        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($this->createConnectionMock());

        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());

        $decryptCmd = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $encryptCmd = new EncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $generateCmd = new GenerateSecretKeyCommand($em, new AttributeReader(), $subscriber, $kernel, $keyPaths);
        $rotateCmd = $this->createRotateCommand($keyPaths, sys_get_temp_dir(), $registry);

        $app = $this->createApplicationWithCommands([
            'doctrine:encrypt:rotate-keys' => $rotateCmd,
            'doctrine:decrypt:database' => $decryptCmd,
            'doctrine:encrypt:database' => $encryptCmd,
            'doctrine:encrypt:generate-secret-key' => $generateCmd,
        ]);
        $rotateCmd->setApplication($app);
        $tester = new CommandTester($app->find('doctrine:encrypt:rotate-keys'));
        $tester->execute(['--no-interaction' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Key rotation completed successfully', $tester->getDisplay());
        $this->assertStringContainsString('Decrypting database', $tester->getDisplay());
        $this->assertStringContainsString('Re-encrypting database', $tester->getDisplay());

        if (file_exists($keyPath)) {
            @unlink($keyPath);
        }
    }

    public function testExecuteAbortsAtFirstConfirmWhenNotNoInteraction(): void
    {
        $keyPaths = [
            'default' => ['path' => '/tmp/nowo.key', 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createRotateCommand($keyPaths);
        $app = $this->createApplicationWithCommands(['doctrine:encrypt:rotate-keys' => $command]);
        $command->setApplication($app);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Rotation aborted', $tester->getDisplay());
    }

    public function testExecuteWithBackupOptionShowsBackupStep(): void
    {
        $keyPaths = [
            'default' => ['path' => '/nonexistent/key.key', 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createRotateCommand($keyPaths);
        $app = $this->createApplicationWithCommands(['doctrine:encrypt:rotate-keys' => $command]);
        $command->setApplication($app);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute(['--backup' => true], ['interactive' => true]);

        $this->assertStringContainsString('Step 1: Back up', $tester->getDisplay());
        $this->assertStringContainsString('Step 2: Decrypt', $tester->getDisplay());
    }

    public function testResolveKeyPathsReplacesProjectDir(): void
    {
        $keyPaths = [
            'default' => ['path' => '%kernel.project_dir%/var/keys/default.key', 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createRotateCommand($keyPaths, '/app');
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $command->setApplication(new Application());
        $tester->execute([], ['interactive' => true]);
        $this->assertStringContainsString('Key rotation has 4 steps', $tester->getDisplay());
    }

    public function testExecutePausedAtStep2ShowsPausedMessage(): void
    {
        $keyPaths = [
            'default' => ['path' => '/tmp/nowo.key', 'encryptor_class' => 'Halite'],
        ];
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($this->createConnectionMock());
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());
        $rotateCmd = new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, $registry, $kernel, $keyPaths);
        $app = $this->createApplicationWithCommands([
            'doctrine:encrypt:rotate-keys' => $rotateCmd,
            'doctrine:decrypt:database' => new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry),
            'doctrine:encrypt:database' => new EncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry),
            'doctrine:encrypt:generate-secret-key' => new GenerateSecretKeyCommand($em, new AttributeReader(), $subscriber, $kernel, $keyPaths),
        ]);
        $rotateCmd->setApplication($app);
        $tester = new CommandTester($app->find('doctrine:encrypt:rotate-keys'));
        $tester->setInputs(['yes', 'no']);

        $tester->execute([], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Rotation paused', $tester->getDisplay());
        $this->assertStringContainsString('decrypted', $tester->getDisplay());
    }

    public function testExecuteWithBackupAndSqliteBacksUpDatabaseAndKeys(): void
    {
        $projectDir = sys_get_temp_dir() . '/nowo-rotate-backup-' . uniqid();
        @mkdir($projectDir, 0o755, true);
        @mkdir($projectDir . '/var', 0o755, true);
        $dbPath = $projectDir . '/var/db.sqlite';
        file_put_contents($dbPath, 'sqlite data');
        $keyPath = $projectDir . '/var/default.key';
        file_put_contents($keyPath, 'key-content');

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $conn = $this->createConnectionMock();
        $conn->method('getParams')->willReturn(['driver' => 'pdo_sqlite', 'path' => 'file:' . $dbPath]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($conn);

        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $decryptCmd = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $encryptCmd = new EncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $generateCmd = new GenerateSecretKeyCommand($em, new AttributeReader(), $subscriber, $kernel, $keyPaths);
        $rotateCmd = new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, $registry, $kernel, $keyPaths);

        $app = $this->createApplicationWithCommands([
            'doctrine:encrypt:rotate-keys' => $rotateCmd,
            'doctrine:decrypt:database' => $decryptCmd,
            'doctrine:encrypt:database' => $encryptCmd,
            'doctrine:encrypt:generate-secret-key' => $generateCmd,
        ]);
        $rotateCmd->setApplication($app);
        $tester = new CommandTester($app->find('doctrine:encrypt:rotate-keys'));
        $tester->execute(['--backup' => true, '--no-interaction' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Backup completed', $tester->getDisplay());
        $this->assertStringContainsString('Database (SQLite) backed up', $tester->getDisplay());
        $this->assertStringContainsString('Key files backed up', $tester->getDisplay());

        $backupDirs = glob($projectDir . '/var/encrypt_rotation_backup_*');
        $this->assertNotEmpty($backupDirs, 'Backup directory should exist');
        $backupDir = $backupDirs[0];
        $this->assertFileExists($backupDir . '/database.sqlite.gz');
        $this->assertFileExists($backupDir . '/keys/default_default.key');

        @unlink($backupDir . '/database.sqlite.gz');
        @unlink($backupDir . '/keys/default_default.key');
        @rmdir($backupDir . '/keys');
        @rmdir($backupDir);
        @unlink($dbPath);
        @unlink($keyPath);
        @rmdir($projectDir . '/var');
        @rmdir($projectDir);
    }

    public function testExecuteWithBackupWhenNotSqliteAndNoBackupCmdShowsComment(): void
    {
        $projectDir = sys_get_temp_dir() . '/nowo-rotate-nosqlite-' . uniqid();
        @mkdir($projectDir, 0o755, true);
        @mkdir($projectDir . '/var', 0o755, true);
        $keyPath = $projectDir . '/var/default.key';
        file_put_contents($keyPath, 'key');

        $keyPaths = ['default' => ['path' => $keyPath, 'encryptor_class' => 'Halite']];
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $conn = $this->createConnectionMock();
        $conn->method('getParams')->willReturn(['driver' => 'pdo_mysql']);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($conn);
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);
        $rotateCmd = new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, $registry, $kernel, $keyPaths);
        $decryptCmd = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $encryptCmd = new EncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $generateCmd = new GenerateSecretKeyCommand($em, new AttributeReader(), $subscriber, $kernel, $keyPaths);
        $app = $this->createApplicationWithCommands([
            'doctrine:encrypt:rotate-keys' => $rotateCmd,
            'doctrine:decrypt:database' => $decryptCmd,
            'doctrine:encrypt:database' => $encryptCmd,
            'doctrine:encrypt:generate-secret-key' => $generateCmd,
        ]);
        $rotateCmd->setApplication($app);
        $tester = new CommandTester($app->find('doctrine:encrypt:rotate-keys'));
        $tester->execute(['--backup' => true, '--no-interaction' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Database not backed up automatically', $tester->getDisplay());
        $this->assertStringContainsString('not SQLite', $tester->getDisplay());
        @unlink($keyPath);
        @rmdir($projectDir . '/var');
        @rmdir($projectDir);
    }

    public function testExecuteWithBackupAndBackupDbCmdFailureShowsComment(): void
    {
        $projectDir = sys_get_temp_dir() . '/nowo-rotate-dbcmd-' . uniqid();
        @mkdir($projectDir, 0o755, true);
        @mkdir($projectDir . '/var', 0o755, true);
        $keyPath = $projectDir . '/var/default.key';
        file_put_contents($keyPath, 'key');

        $keyPaths = ['default' => ['path' => $keyPath, 'encryptor_class' => 'Halite']];
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $conn = $this->createConnectionMock();
        $conn->method('getParams')->willReturn(['driver' => 'pdo_mysql']);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($conn);
        $registry = new EncryptorRegistry(['default' => $this->createMock(EncryptorInterface::class)], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $failingBackupCmd = new class () extends Command {
            public function execute(InputInterface $input, OutputInterface $output): int
            {
                return self::FAILURE;
            }
        };
        $failingBackupCmd->setName('app:fake-backup');

        $rotateCmd = new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, $registry, $kernel, $keyPaths);
        $app = $this->createApplicationWithCommands([
            'doctrine:encrypt:rotate-keys' => $rotateCmd,
            'app:fake-backup' => $failingBackupCmd,
            'doctrine:decrypt:database' => new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry),
            'doctrine:encrypt:database' => new EncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry),
            'doctrine:encrypt:generate-secret-key' => new GenerateSecretKeyCommand($em, new AttributeReader(), $subscriber, $kernel, $keyPaths),
        ]);
        $rotateCmd->setApplication($app);

        $tester = new CommandTester($app->find('doctrine:encrypt:rotate-keys'));
        $tester->execute(['--backup' => true, '--backup-db-cmd' => 'app:fake-backup', '--no-interaction' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Running database backup command', $tester->getDisplay());
        $this->assertStringContainsString('exited with code 1', $tester->getDisplay());

        @unlink($keyPath);
        @rmdir($projectDir . '/var');
        @rmdir($projectDir);
    }

    public function testExecuteWithEnvConfigShowsEnvVarMessage(): void
    {
        $keyPath = sys_get_temp_dir() . '/nowo-rotate-env-' . uniqid() . '.key';
        if (file_exists($keyPath)) {
            @unlink($keyPath);
        }
        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
            'env_config' => ['path' => null, 'encryptor_class' => 'Halite'],
        ];
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);
        $em->method('getConnection')->willReturn($this->createConnectionMock());
        $registry = new EncryptorRegistry([
            'default' => $this->createMock(EncryptorInterface::class),
            'env_config' => $this->createMock(EncryptorInterface::class),
        ], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());
        $decryptCmd = new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $encryptCmd = new EncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $registry);
        $generateCmd = new GenerateSecretKeyCommand($em, new AttributeReader(), $subscriber, $kernel, $keyPaths);
        $rotateCmd = new RotateKeysCommand($em, new AttributeReader(), $subscriber, null, $registry, $kernel, $keyPaths);
        $app = $this->createApplicationWithCommands([
            'doctrine:encrypt:rotate-keys' => $rotateCmd,
            'doctrine:decrypt:database' => $decryptCmd,
            'doctrine:encrypt:database' => $encryptCmd,
            'doctrine:encrypt:generate-secret-key' => $generateCmd,
        ]);
        $rotateCmd->setApplication($app);
        $tester = new CommandTester($app->find('doctrine:encrypt:rotate-keys'));
        $tester->execute(['--no-interaction' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Config(s) using env var', $tester->getDisplay());
        $this->assertStringContainsString('env_config', $tester->getDisplay());
        if (file_exists($keyPath)) {
            @unlink($keyPath);
        }
    }

    /**
     * Register commands via CommandLoader (Application::add() was removed in Symfony Console 8.0).
     *
     * @param array<string, Command> $commandMap name => command instance
     */
    private function createApplicationWithCommands(array $commandMap): Application
    {
        $app = new Application();
        $loader = new FactoryCommandLoader(
            array_map(static fn (Command $cmd): \Closure => fn (): Command => $cmd, $commandMap)
        );
        $app->setCommandLoader($loader);
        return $app;
    }

    private function createConnectionMock(): \Doctrine\DBAL\Connection
    {
        $result = $this->createMock(\Doctrine\DBAL\Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);
        $platform = $this->createMock(\Doctrine\DBAL\Platforms\AbstractPlatform::class);
        $platform->method('quoteIdentifier')->willReturnCallback(static fn (string $s): string => '"' . $s . '"');
        $conn = $this->createMock(\Doctrine\DBAL\Connection::class);
        $conn->method('getDatabasePlatform')->willReturn($platform);
        $conn->method('executeQuery')->willReturn($result);
        return $conn;
    }
}
