<?php

declare(strict_types=1);

namespace Doctrine\Bundle\DoctrineBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class AsDoctrineListener
{
    public function __construct(
        public readonly string $event,
        public readonly int $priority = 0,
        public readonly string $connection = 'default',
    ) {
    }
}
