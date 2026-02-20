<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptStatusCommand;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\EntityWithConfigAlias;
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

    public function testExecuteOutputsSummaryWithEntityAndPropertyCounts(): void
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
        $this->assertMatchesRegularExpression('/\d+ entit\(y\/ies\) with encryption, \d+ encrypted properties in total/', $display);
    }

    public function testExecuteSkipsMappedSuperclassAndOutputsOthers(): void
    {
        $metadataSuper = new \stdClass();
        $metadataSuper->name = 'BaseEntity';
        $metadataSuper->isMappedSuperclass = true;

        $metadataEntity = new \stdClass();
        $metadataEntity->name = User::class;
        $metadataEntity->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataSuper, $metadataEntity]);

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
        $this->assertStringNotContainsString('BaseEntity', $display);
    }

    public function testExecuteWithNoEntitiesOutputsZeroSummary(): void
    {
        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([]);

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($metadataFactory);

        $attributeReader = new AttributeReader();
        $subscriber = $this->createMock(DoctrineEncryptSubscriber::class);

        $command = new DoctrineEncryptStatusCommand($em, $attributeReader, $subscriber);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('0 entit(y/ies) with encryption', $display);
        $this->assertStringContainsString('0 encrypted properties in total', $display);
    }

    public function testExecuteOutputsMultipleEntitiesWithEncryptedProperties(): void
    {
        $metadataUser = new \stdClass();
        $metadataUser->name = User::class;
        $metadataUser->isMappedSuperclass = false;

        $metadataEntityWithAlias = new \stdClass();
        $metadataEntityWithAlias->name = EntityWithConfigAlias::class;
        $metadataEntityWithAlias->isMappedSuperclass = false;

        $metadataFactory = $this->createMock(ClassMetadataFactory::class);
        $metadataFactory->method('getAllMetadata')->willReturn([$metadataUser, $metadataEntityWithAlias]);

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
        $this->assertStringContainsString(EntityWithConfigAlias::class, $display);
        $this->assertMatchesRegularExpression('/\d+ entit\(y\/ies\) with encryption, \d+ encrypted properties in total/', $display);
    }
}
