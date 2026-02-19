<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptStatusCommand;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DoctrineEncryptStatusCommandTest extends TestCase
{
    public function testExecuteOutputsEncryptedPropertiesCount(): void
    {
        $metadata = new \stdClass();
        $metadata->name = User::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $attributeReader = new AttributeReader();
        $subscriber = $this->createMock(DoctrineEncryptSubscriber::class);

        $command = new DoctrineEncryptStatusCommand($em, $attributeReader, $subscriber);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString(User::class, $display);
        $this->assertStringContainsString('2', $display);
        $this->assertStringContainsString('encrypted', $display);
    }

    public function testExecuteOutputsNoEncryptedWhenEntityHasNone(): void
    {
        $metadata = new \stdClass();
        $metadata->name = \stdClass::class;
        $metadata->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadata]);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $attributeReader = new AttributeReader();
        $subscriber = $this->createMock(DoctrineEncryptSubscriber::class);

        $command = new DoctrineEncryptStatusCommand($em, $attributeReader, $subscriber);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('no properties which are encrypted', $tester->getDisplay());
    }
}
