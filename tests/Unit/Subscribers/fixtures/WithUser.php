<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

class WithUser
{
    public function __construct(
        #[Encrypted]
        public string $name,
        public ?string $foo,
        #[ORM\Embedded(class: User::class)]
        public User $user
    ) {
    }
}
