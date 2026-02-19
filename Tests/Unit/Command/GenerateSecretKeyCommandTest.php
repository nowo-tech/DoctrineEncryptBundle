<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Nowo\DoctrineEncryptBundle\Command\GenerateSecretKeyCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateSecretKeyCommandTest extends TestCase
{
    public function testExecuteWithHaliteEncryptorSavesKeyAndOutputsPath(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyPath = sys_get_temp_dir() . '/nowo-test-halite-' . uniqid() . '.key';
        if (file_exists($keyPath)) {
            unlink($keyPath);
        }

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $attributeReader = new AttributeReader();
        $subscriber = new DoctrineEncryptSubscriber(new HaliteEncryptor($keyPath));

        $command = new GenerateSecretKeyCommand($em, $attributeReader, $subscriber, $keyPath);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString($keyPath, $tester->getDisplay());
        $this->assertFileExists($keyPath);

        if (file_exists($keyPath)) {
            unlink($keyPath);
        }
    }

    public function testExecuteWithNullEncryptorOutputsCommentAndReturnsSuccess(): void
    {
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $attributeReader = new AttributeReader();
        $subscriber = new DoctrineEncryptSubscriber(null);

        $command = new GenerateSecretKeyCommand($em, $attributeReader, $subscriber, '/some/path.key');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Halite', $tester->getDisplay());
        $this->assertStringContainsString('only supported', $tester->getDisplay());
    }

    public function testExecuteWithDefuseEncryptorOutputsCommentAndReturnsSuccess(): void
    {
        $keyFile = __DIR__ . '/../Encryptors/fixtures/defuse.key';
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $attributeReader = new AttributeReader();
        $subscriber = new DoctrineEncryptSubscriber(new DefuseEncryptor($keyFile));

        $command = new GenerateSecretKeyCommand($em, $attributeReader, $subscriber, '/some/path.key');
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Halite', $tester->getDisplay());
    }
}
