<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Command\GenerateSecretKeyCommand;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

use function extension_loaded;

class GenerateSecretKeyCommandTest extends TestCase
{
    /**
     * @param array<string, array{path: string|null, encryptor_class: string}> $keyPaths
     */
    private function createCommand(array $keyPaths, string $projectDir = '/tmp'): GenerateSecretKeyCommand
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $em              = $this->createMock(EntityManagerInterface::class);
        $attributeReader = new AttributeReader();
        $subscriber      = $this->createMock(DoctrineEncryptSubscriber::class);

        return new GenerateSecretKeyCommand($em, $attributeReader, $subscriber, $kernel, $keyPaths);
    }

    public function testExecuteWithoutArgumentCreatesMissingKeysForAllConfigs(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPath = sys_get_temp_dir() . '/nowo-test-halite-' . uniqid() . '.key';
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createCommand($keyPaths, sys_get_temp_dir());
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString($keyPath, $tester->getDisplay());
        $this->assertFileExists($keyPath);
        $this->assertStringContainsString('Created', $tester->getDisplay());

        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    public function testExecuteWithoutArgumentSkipsConfigWhenKeyAlreadyExists(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPath = sys_get_temp_dir() . '/nowo-test-halite-existing-' . uniqid() . '.key';
        touch($keyPath);

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createCommand($keyPaths, sys_get_temp_dir());
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('already exists', $tester->getDisplay());

        unlink($keyPath);
    }

    public function testExecuteWithConfigArgumentCreatesKeyAndOutputsPath(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPath = sys_get_temp_dir() . '/nowo-test-halite-single-' . uniqid() . '.key';
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createCommand($keyPaths, sys_get_temp_dir());
        $tester  = new CommandTester($command);

        $tester->execute(['config' => 'default']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString($keyPath, $tester->getDisplay());
        $this->assertFileExists($keyPath);

        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    public function testExecuteWithConfigArgumentAsksConfirmationWhenKeyExists(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPath = sys_get_temp_dir() . '/nowo-test-halite-confirm-' . uniqid() . '.key';
        touch($keyPath);

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];
        $command     = $this->createCommand($keyPaths, sys_get_temp_dir());
        $application = new Application();
        $command->setApplication($application);
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $tester->execute(['config' => 'default'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Overwrite', $tester->getDisplay());
        $this->assertStringContainsString('Aborted', $tester->getDisplay());

        unlink($keyPath);
    }

    public function testExecuteWithConfigArgumentOverwritesKeyWhenUserConfirmsYes(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPath = sys_get_temp_dir() . '/nowo-test-halite-overwrite-' . uniqid() . '.key';
        file_put_contents($keyPath, 'old-content');

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Halite'],
        ];
        $command     = $this->createCommand($keyPaths, sys_get_temp_dir());
        $application = new Application();
        $command->setApplication($application);
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $tester->execute(['config' => 'default'], ['interactive' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Overwrite', $tester->getDisplay());
        $this->assertStringContainsString('saved to', $tester->getDisplay());
        $content = (string) file_get_contents($keyPath);
        $this->assertNotSame('old-content', $content);
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/i', trim($content), 'Key file must be hex');
        unlink($keyPath);
    }

    public function testExecuteWithUnknownConfigReturnsFailure(): void
    {
        $keyPaths = [
            'default' => ['path' => '/tmp/default.key', 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute(['config' => 'nonexistent']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown config', $tester->getDisplay());
        $this->assertStringContainsString('default', $tester->getDisplay());
    }

    public function testExecuteWithConfigArgumentWhenEncryptorNotSupportedReturnsFailure(): void
    {
        $keyPaths = [
            'custom' => ['path' => '/tmp/custom.key', 'encryptor_class' => 'CustomEncryptor'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute(['config' => 'custom']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Key generation is only supported for Halite, Defuse and MysqlAes', $tester->getDisplay());
        $this->assertStringContainsString('CustomEncryptor', $tester->getDisplay());
    }

    public function testExecuteWithoutArgumentCreatesDefuseKeyWhenMissing(): void
    {
        $keyPath = sys_get_temp_dir() . '/nowo-test-defuse-' . uniqid() . '.key';
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }

        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Defuse'],
        ];
        $command = $this->createCommand($keyPaths, sys_get_temp_dir());
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($keyPath);
        $this->assertStringContainsString('Created', $tester->getDisplay());

        unlink($keyPath);
    }

    public function testExecuteWithoutArgumentOutputsNotSupportedForUnsupportedEncryptor(): void
    {
        $keyPaths = [
            'custom' => ['path' => '/tmp/custom.key', 'encryptor_class' => 'CustomEncryptor'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('not supported', $tester->getDisplay());
        $this->assertStringContainsString('Halite, Defuse, MysqlAes', $tester->getDisplay());
    }

    public function testExecuteWithoutArgumentWhenConfigWithoutPathOutputsOnlyKey(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPaths = [
            'default' => ['path' => null, 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Config "default":', $tester->getDisplay());
        $this->assertStringContainsString('not set yet', $tester->getDisplay());
        $this->assertStringContainsString('.env', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/[a-f0-9]{100,}/', $tester->getDisplay(), 'Output must contain the generated key (hex)');
    }

    public function testExecuteWithConfigArgumentWhenPathNullOutputsOnlyKey(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPaths = [
            'default' => ['path' => null, 'encryptor_class' => 'Halite'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute(['config' => 'default']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Config "default":', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/[a-f0-9]{100,}/', $tester->getDisplay());
    }

    public function testExecuteWithoutArgumentWhenConfigWithoutPathUnsupportedEncryptor(): void
    {
        $keyPaths = [
            'custom' => ['path' => null, 'encryptor_class' => 'CustomEncryptor'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('key generation not supported', $tester->getDisplay());
        $this->assertStringContainsString('CustomEncryptor', $tester->getDisplay());
    }

    public function testExecuteWithoutArgumentDefuseConfigWithoutPathOutputsKey(): void
    {
        $keyPaths = [
            'default' => ['path' => null, 'encryptor_class' => 'Defuse'],
        ];
        $command = $this->createCommand($keyPaths);
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Config "default":', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/[a-f0-9]{400,}/', $tester->getDisplay(), 'Defuse key is 510 hex chars');
    }

    public function testCommandDefinitionHasConfigArgument(): void
    {
        $command = $this->createCommand(['default' => ['path' => '/tmp/k.key', 'encryptor_class' => 'Halite']]);
        $def     = $command->getDefinition();
        $this->assertTrue($def->hasArgument('config'));
        $this->assertNull($def->getArgument('config')->getDefault(), 'config argument is optional (no default required)');
    }

    /** Covers createKey() path when parent directory does not exist (mkdir branch for Defuse). */
    public function testExecuteCreatesKeyInNonExistentDirectory(): void
    {
        $baseDir = sys_get_temp_dir() . '/nowo-encrypt-nested-' . uniqid();
        $this->assertDirectoryDoesNotExist($baseDir);
        $keyPath  = $baseDir . '/subdir/defuse.key';
        $keyPaths = [
            'default' => ['path' => $keyPath, 'encryptor_class' => 'Defuse'],
        ];
        $command = $this->createCommand($keyPaths, sys_get_temp_dir());
        $tester  = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertFileExists($keyPath);
        $this->assertStringContainsString('Created', $tester->getDisplay());
        $content = (string) file_get_contents($keyPath);
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/i', trim($content));
        unlink($keyPath);
        rmdir($baseDir . '/subdir');
        rmdir($baseDir);
    }
}
