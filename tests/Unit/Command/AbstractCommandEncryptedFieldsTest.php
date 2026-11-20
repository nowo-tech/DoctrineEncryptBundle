<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Support\DbalTypeFixtures;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Command\fixtures\EncryptedFieldsExposingCommand;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfile;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfileChild;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * encrypted_fields and encrypted DBAL type columns in the command helpers.
 */
final class AbstractCommandEncryptedFieldsTest extends TestCase
{
    public function testConfiguredPropertiesAreAddedAfterAttributeOnesWithoutDuplicates(): void
    {
        $command = $this->command();
        $command->setEncryptedFieldMap(new EncryptedFieldMap([
            User::class         => ['properties' => ['name' => 'financial_data']],
            PlainProfile::class => ['properties' => ['taxId' => 'financial_data', 'missing' => null]],
        ]));

        $user = $this->metadata(User::class);
        self::assertSame(['name', 'address'], array_map(static fn (array $p): string => $p['property']->getName(), $command->withConfig($user)));
        self::assertSame(['default_profile', 'default_profile'], array_column($command->withConfig($user), 'config'));

        // A private property of the parent class is found; an unknown one is ignored here (the build validates it).
        $child = $command->withConfig($this->metadata(PlainProfileChild::class));
        self::assertCount(1, $child);
        self::assertSame('taxId', $child[0]['property']->getName());
        self::assertSame(PlainProfile::class, $child[0]['property']->getDeclaringClass()->getName());
        self::assertSame('financial_data', $child[0]['config']);
    }

    public function testEncryptedTypeFieldsAreListedWithTheirProfile(): void
    {
        $metadata = new ClassMetadata(PlainProfile::class);
        $metadata->mapField(['fieldName' => 'taxId', 'type' => 'encrypted_string']);
        $metadata->mapField(['fieldName' => 'nickname', 'type' => 'string']);
        $metadata->mapField(['fieldName' => 'setterCalls', 'type' => 'type_that_is_not_registered']);

        $command = $this->command();
        self::assertSame([], DbalTypeFixtures::deprecationsRaisedBy(static fn () => $command->typeFields($metadata)));
        self::assertSame([['field' => 'taxId', 'config' => 'default_profile', 'type' => 'encrypted_string']], $command->typeFields($metadata));
        self::assertSame([], $command->typeFields($this->metadata(PlainProfile::class)));
        self::assertFalse($command->embeddable($metadata));

        $metadata->isEmbeddedClass = true;
        self::assertTrue($command->embeddable($metadata));
    }

    private function metadata(string $class): stdClass
    {
        $metadata                     = new stdClass();
        $metadata->name               = $class;
        $metadata->isMappedSuperclass = false;

        return $metadata;
    }

    private function command(): EncryptedFieldsExposingCommand
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn(DbalTypeFixtures::connection(['encrypted_string' => EncryptedStringType::class]));

        return new EncryptedFieldsExposingCommand($entityManager, new AttributeReader(), $this->createMock(DoctrineEncryptSubscriber::class));
    }
}
