<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

class WithUser
{
    #[Encrypted]
    public string $name;

    public ?string $foo;

    #[ORM\Embedded(class: User::class)]
    public User $user;

    public function __construct(string $name, string $foo, User $user)
    {
        $this->name = $name;
        $this->foo = $foo;
        $this->user = $user;
    }
}
