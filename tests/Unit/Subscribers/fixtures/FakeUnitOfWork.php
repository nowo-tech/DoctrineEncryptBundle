<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Doctrine\ORM\UnitOfWork;

use function in_array;
use function spl_object_id;

/**
 * Unit of work double that keeps the original data like the real one (only the methods the listener calls).
 *
 * @internal
 */
final class FakeUnitOfWork extends UnitOfWork
{
    /** @var array<int, array<string, mixed>> */
    public array $original;

    /** @var array<class-string, array<string, object>> */
    public array $identity;

    /** @var list<object> */
    public array $uninitialized;

    public bool $scheduledUpdates;

    public function getOriginalEntityData(object $entity): array
    {
        return $this->original[spl_object_id($entity)] ?? [];
    }

    public function setOriginalEntityProperty(int $oid, string $property, mixed $value): void
    {
        $this->original[$oid][$property] = $value;
    }

    public function getIdentityMap(): array
    {
        return $this->identity;
    }

    public function isUninitializedObject(mixed $obj): bool
    {
        return in_array($obj, $this->uninitialized, true);
    }

    public function isScheduledForUpdate(object $entity): bool
    {
        return $this->scheduledUpdates;
    }
}
