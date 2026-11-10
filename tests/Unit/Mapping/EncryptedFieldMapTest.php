<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping;

use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfile;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\PlainProfileChild;
use PHPUnit\Framework\TestCase;

final class EncryptedFieldMapTest extends TestCase
{
    public function testEmptyMap(): void
    {
        $map = new EncryptedFieldMap();

        self::assertTrue($map->isEmpty());
        self::assertSame([], $map->getProperties(PlainProfile::class));
        self::assertSame([], $map->getEmbedded(PlainProfile::class));
        self::assertSame([], $map->all());
    }

    public function testNullProfileMeansDefaultAndLeadingBackslashIsIgnored(): void
    {
        $map = new EncryptedFieldMap(['\\' . PlainProfile::class => ['properties' => ['taxId' => null, 'nickname' => 'personal_data'], 'embedded' => ['address']]]);

        self::assertFalse($map->isEmpty());
        self::assertSame(['taxId' => 'default', 'nickname' => 'personal_data'], $map->getProperties(PlainProfile::class));
        self::assertSame(['taxId' => 'default', 'nickname' => 'personal_data'], $map->getProperties('\\' . PlainProfile::class));
        self::assertSame(['address'], $map->getEmbedded(PlainProfile::class));
        self::assertSame([PlainProfile::class => ['properties' => ['taxId' => 'default', 'nickname' => 'personal_data'], 'embedded' => ['address']]], $map->all());
    }

    public function testEntriesOfParentsApplyToSubclassesAndTheSubclassWins(): void
    {
        $map = new EncryptedFieldMap([
            PlainProfile::class      => ['properties' => ['taxId' => 'a', 'nickname' => 'a'], 'embedded' => ['address']],
            PlainProfileChild::class => ['properties' => ['nickname' => 'b'], 'embedded' => ['address', 'other']],
        ]);

        self::assertSame(['nickname' => 'b', 'taxId' => 'a'], $map->getProperties(PlainProfileChild::class));
        self::assertSame(['address', 'other'], $map->getEmbedded(PlainProfileChild::class));
        self::assertSame(['taxId' => 'a', 'nickname' => 'a'], $map->getProperties(PlainProfile::class));
    }

    public function testUnknownClassOnlyUsesItsOwnEntry(): void
    {
        $map = new EncryptedFieldMap(['App\\Missing' => ['properties' => ['x' => null]]]);

        self::assertSame(['x' => 'default'], $map->getProperties('App\\Missing'));
        self::assertSame([], $map->getEmbedded('App\\Missing'));
    }
}
