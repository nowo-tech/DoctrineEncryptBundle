<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers;

use InvalidArgumentException;
use LogicException;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainAddress;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfile;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfileChild;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function strlen;

/**
 * encrypted_fields (config-based field selection) and property_access: reflection.
 */
final class DoctrineEncryptSubscriberConfiguredFieldsTest extends TestCase
{
    public function testConfiguredFieldsAreEncryptedAndDecryptedWithTheirProfile(): void
    {
        $subscriber = $this->subscriber(new EncryptedFieldMap([
            PlainProfile::class => ['properties' => ['taxId' => null, 'nickname' => 'financial_data'], 'embedded' => ['address']],
            PlainAddress::class => ['properties' => ['street' => null]],
        ]), DoctrineEncryptSubscriber::PROPERTY_ACCESS_REFLECTION);

        $profile = new PlainProfile('X123', 'nick');
        $profile->setAddress(new PlainAddress('Main St'));
        $profile->setterCalls = 0;

        $subscriber->processFields($profile);
        self::assertSame('encrypted-default-X123<ENC>', $this->raw($profile, 'taxId'));
        self::assertSame('encrypted-financial-nick<ENC>', $this->raw($profile, 'nickname'));
        self::assertSame('encrypted-default-Main St<ENC>', $this->raw($profile->getAddress(), 'street'));

        $subscriber->processFields($profile, false);
        self::assertSame('X123', $profile->getTaxId());
        self::assertSame('nick', $profile->getNickname());
        self::assertSame('Main St', $profile->getAddress()?->street());

        // property_access: reflection: the validating setter was never called with ciphertext (nor at all).
        self::assertSame(0, $profile->setterCalls);
    }

    public function testAutoPropertyAccessStillUsesTheSetter(): void
    {
        $subscriber = $this->subscriber(new EncryptedFieldMap([PlainProfile::class => ['properties' => ['taxId' => null]]]));
        $profile    = new PlainProfile('X123');

        // Default behaviour (unchanged): the one-argument setter receives the ciphertext and may reject it.
        $this->expectException(InvalidArgumentException::class);
        $subscriber->processFields($profile);
    }

    public function testParentEntriesApplyToSubclasses(): void
    {
        $subscriber = $this->subscriber(new EncryptedFieldMap([PlainProfile::class => ['properties' => ['nickname' => null]]]));
        $child      = new PlainProfileChild(null, 'nick');

        $subscriber->processFields($child);
        self::assertSame('encrypted-default-nick<ENC>', $this->raw($child, 'nickname', PlainProfile::class));
    }

    public function testAttributeFieldsComeFirstAndAreNotDuplicatedByTheConfiguration(): void
    {
        $subscriber = $this->subscriber(new EncryptedFieldMap([User::class => ['properties' => ['name' => 'financial_data'], 'embedded' => ['address']]]));
        $user       = new User('Ana', null);

        $subscriber->processFields($user);
        // #[Encrypted] wins (compile-time validation forbids both; at runtime the attribute is kept).
        self::assertSame('encrypted-default-Ana<ENC>', $user->name);
    }

    public function testUnknownConfiguredPropertyFailsLoudly(): void
    {
        $subscriber = $this->subscriber(new EncryptedFieldMap([PlainProfile::class => ['properties' => ['missing' => null]]]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has no property "missing"');
        $subscriber->processFields(new PlainProfile());
    }

    public function testUnknownConfiguredEmbeddedPropertyFailsLoudly(): void
    {
        $subscriber = $this->subscriber(new EncryptedFieldMap([PlainProfile::class => ['embedded' => ['missing']]]));

        $this->expectException(LogicException::class);
        $subscriber->processFields(new PlainProfile());
    }

    public function testInvalidPropertyAccessIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid property_access "magic"');
        new DoctrineEncryptSubscriber(null, null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP, null, 'magic');
    }

    public function testReflectionAccessOnAttributeEntityBypassesSetters(): void
    {
        $subscriber = $this->subscriber(null, DoctrineEncryptSubscriber::PROPERTY_ACCESS_REFLECTION);
        $user       = new User('Ana', 'Street');

        $subscriber->processFields($user);
        self::assertSame('encrypted-default-Street<ENC>', $user->getAddress());
        $subscriber->processFields($user, false);
        self::assertSame('Street', $user->getAddress());
    }

    private function subscriber(?EncryptedFieldMap $map, string $propertyAccess = DoctrineEncryptSubscriber::PROPERTY_ACCESS_AUTO): DoctrineEncryptSubscriber
    {
        $registry = new EncryptorRegistry([
            'default'        => $this->encryptor('default'),
            'financial_data' => $this->encryptor('financial'),
        ], 'default');

        return new DoctrineEncryptSubscriber($registry, null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW, $map, $propertyAccess);
    }

    private function encryptor(string $name): EncryptorInterface
    {
        return new class($name) implements EncryptorInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function encrypt(string $data): string
            {
                return 'encrypted-' . $this->name . '-' . $data;
            }

            public function decrypt(string $data): string
            {
                return substr($data, strlen('encrypted-' . $this->name . '-'));
            }
        };
    }

    /**
     * @param class-string|null $class
     */
    private function raw(?object $object, string $property, ?string $class = null): mixed
    {
        self::assertNotNull($object);

        return (new ReflectionProperty($class ?? $object::class, $property))->getValue($object);
    }
}
