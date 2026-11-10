<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'rotation_person')]
class Person
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'string', length: 1024, nullable: true)]
        #[Encrypted]
        private ?string $email,
        #[ORM\Embedded(class: Address::class)]
        private Address $address
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }
}
