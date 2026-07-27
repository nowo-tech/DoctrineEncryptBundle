<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Child of BaseWithNoProperties with a single Encrypted property.
 */
class ChildWithEncryptedOnly extends BaseWithNoProperties
{
    public function __construct(
        #[Encrypted]
        public string $secret
    ) {
    }
}
