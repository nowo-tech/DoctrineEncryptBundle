<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/** A non-nullable #[Encrypted] property (cannot hold null when on_decrypt_failure is "null"). */
class StrictUser
{
    public function __construct(
        #[Encrypted]
        public string $name,
    ) {
    }
}
