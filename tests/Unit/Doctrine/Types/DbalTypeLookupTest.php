<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Doctrine\Types;

use Doctrine\DBAL\Types\Type;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\DbalTypeLookup;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Tests\Support\DbalTypeFixtures;
use PHPUnit\Framework\TestCase;

final class DbalTypeLookupTest extends TestCase
{
    public function testFindsARegisteredTypeThroughTheConnection(): void
    {
        $connection = DbalTypeFixtures::connection(['encrypted_string' => EncryptedStringType::class]);

        self::assertInstanceOf(EncryptedStringType::class, DbalTypeLookup::find($connection, 'encrypted_string'));
    }

    public function testUnknownTypeIsNull(): void
    {
        $connection = DbalTypeFixtures::connection(['encrypted_string' => EncryptedStringType::class]);

        self::assertNull(DbalTypeLookup::find($connection, 'type_that_is_not_registered'));
    }

    public function testNoDeprecationIsRaisedOnEitherOutcome(): void
    {
        $connection = DbalTypeFixtures::connection(['encrypted_string' => EncryptedStringType::class]);

        $messages = DbalTypeFixtures::deprecationsRaisedBy(static function () use ($connection): void {
            DbalTypeLookup::find($connection, 'encrypted_string');
            DbalTypeLookup::find($connection, 'type_that_is_not_registered');
        });

        self::assertSame([], $messages);
    }

    public function testWithoutAConnectionTheStaticRegistryIsRead(): void
    {
        // Static registry: the only source before doctrine/dbal 4.6 (Doctrine deprecations are off by default here).
        if (!Type::hasType('encrypted_string')) {
            Type::addType('encrypted_string', EncryptedStringType::class);
        }

        self::assertInstanceOf(EncryptedStringType::class, DbalTypeLookup::find(null, 'encrypted_string'));
        self::assertNull(DbalTypeLookup::findInStaticRegistry('type_that_is_not_registered'));
    }

    public function testNameOfAndTheProfileDoNotRaiseDeprecations(): void
    {
        $type = DbalTypeLookup::find(DbalTypeFixtures::connection(['encrypted_string.financial_data' => EncryptedStringType::class]), 'encrypted_string.financial_data');
        self::assertInstanceOf(EncryptedStringType::class, $type);

        $messages = DbalTypeFixtures::deprecationsRaisedBy(static function () use ($type): void {
            self::assertSame('encrypted_string.financial_data', DbalTypeLookup::nameOf($type));
            self::assertSame('financial_data', $type->getProfile());
        });

        self::assertSame([], $messages);
    }
}
