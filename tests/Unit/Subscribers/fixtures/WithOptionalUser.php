<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/** @internal */
class WithOptionalUser
{
    public function __construct(
        #[Encrypted]
        public string $name,
        #[ORM\Embedded(class: User::class)]
        public ?User $user = null
    ) {
    }
}
