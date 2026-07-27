<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

class ExtendedUser extends User
{
    public function __construct(string $name, ?string $address, #[Encrypted]
        public ?string $extra)
    {
        parent::__construct($name, $address);
    }
}
