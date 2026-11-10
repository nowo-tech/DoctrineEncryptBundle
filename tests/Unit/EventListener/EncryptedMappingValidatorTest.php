<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\EventListener;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use LogicException;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\EventListener\EncryptedMappingValidator;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Mapping\ListenerFieldResolver;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainAddress;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfile;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;

final class EncryptedMappingValidatorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!Type::hasType('encrypted_string')) {
            Type::addType('encrypted_string', EncryptedStringType::class);
        }
    }

    public function testValidSelectionPasses(): void
    {
        $metadata = $this->profileMetadata('string');
        $this->validate(new EncryptedFieldMap([PlainProfile::class => ['properties' => ['taxId' => null], 'embedded' => ['address']]]), $metadata);

        // Default configuration: nothing selected, nothing to check.
        $this->validate(new EncryptedFieldMap(), $metadata);
        $this->addToAssertionCount(1);
    }

    public function testConfiguredPropertyMustBeAMappedField(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('PlainProfile::$nickname is not a mapped field');
        $this->validate(new EncryptedFieldMap([PlainProfile::class => ['properties' => ['nickname' => null]]]), $this->profileMetadata('string'));
    }

    public function testConfiguredEmbeddedMustBeAnEmbeddedMapping(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('listed under "embedded" but is not an embedded mapping');
        $this->validate(new EncryptedFieldMap([PlainProfile::class => ['embedded' => ['taxId']]]), $this->profileMetadata('string'));
    }

    public function testConfigAndEncryptedTypeOnTheSameFieldIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('field(s) "taxId" are selected for the listener');
        $this->validate(new EncryptedFieldMap([PlainProfile::class => ['properties' => ['taxId' => null]]]), $this->profileMetadata('encrypted_string'));
    }

    public function testAttributeAndEncryptedTypeOnTheSameFieldIsRejected(): void
    {
        $metadata = new ClassMetadata(User::class);
        $metadata->mapField(['fieldName' => 'name', 'type' => 'encrypted_string']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"name"');
        $this->validate(new EncryptedFieldMap(), $metadata);
    }

    public function testEmbeddedListenerFieldWithEncryptedTypeIsRejected(): void
    {
        $metadata = $this->profileMetadata('string', 'encrypted_string');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"address.street"');
        $this->validate(new EncryptedFieldMap([PlainProfile::class => ['embedded' => ['address']], PlainAddress::class => ['properties' => ['street' => null]]]), $metadata);
    }

    public function testResolverFollowsOnlySelectedEmbeddables(): void
    {
        $metadata = $this->profileMetadata('string');
        $map      = new EncryptedFieldMap([PlainAddress::class => ['properties' => ['street' => 'financial_data']]]);

        self::assertSame([], (new ListenerFieldResolver($map))->embeddedFields($metadata));

        $map = new EncryptedFieldMap([PlainProfile::class => ['embedded' => ['address']], PlainAddress::class => ['properties' => ['street' => 'financial_data']]]);
        self::assertSame([['field' => 'address.street', 'config' => 'financial_data']], (new ListenerFieldResolver($map))->embeddedFields($metadata));
        self::assertSame([], (new ListenerFieldResolver())->properties('App\\Missing'));
    }

    /**
     * PlainProfile with taxId (given type) and an inlined PlainAddress embeddable (street with the given type).
     *
     * @return ClassMetadata<PlainProfile>
     */
    private function profileMetadata(string $taxIdType, string $streetType = 'string'): ClassMetadata
    {
        $metadata = new ClassMetadata(PlainProfile::class);
        $metadata->mapField(['fieldName' => 'taxId', 'type' => $taxIdType]);
        $metadata->mapEmbedded(['fieldName' => 'address', 'class' => PlainAddress::class, 'columnPrefix' => 'address_']);
        $metadata->mapField(['fieldName' => 'address.street', 'type' => $streetType, 'columnName' => 'address_street', 'declaredField' => 'address', 'originalField' => 'street', 'originalClass' => PlainAddress::class]);

        return $metadata;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function validate(EncryptedFieldMap $map, ClassMetadata $metadata): void
    {
        (new EncryptedMappingValidator($map))->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->createMock(EntityManagerInterface::class)));
    }
}
