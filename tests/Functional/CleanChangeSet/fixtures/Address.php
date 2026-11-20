<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Embeddable]
class Address
{
    public function __construct(
        #[ORM\Column(name: 'street', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $street,
        #[ORM\Column(name: 'city', type: 'string', length: 255, nullable: true)]
        private ?string $city,
        #[ORM\Embedded(class: Geo::class)]
        private Geo $geo
    ) {
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): void
    {
        $this->street = $street;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getGeo(): Geo
    {
        return $this->geo;
    }
}
