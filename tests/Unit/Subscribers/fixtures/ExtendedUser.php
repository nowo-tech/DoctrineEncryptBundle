<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

class ExtendedUser extends User
{
    #[Encrypted]
    public ?string $extra;

    public function __construct(string $name, ?string $address, ?string $extra)
    {
        parent::__construct($name, $address);
        $this->extra = $extra;
    }
}
