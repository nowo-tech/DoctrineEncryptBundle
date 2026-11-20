<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * Every encrypted field of this entity is inside an embeddable (the listener caches them under the Address object).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ccs_contact')]
class Contact
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'name', type: 'string', length: 64, nullable: true)]
        private ?string $name,
        #[ORM\Embedded(class: Address::class)]
        private Address $address,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }
}
