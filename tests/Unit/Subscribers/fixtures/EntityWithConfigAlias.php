<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

class EntityWithConfigAlias
{
    public function __construct(
        #[Encrypted('default')]
        public string $defaultField = '',
        #[Encrypted('other_config')]
        public string $otherField = ''
    ) {
    }
}
