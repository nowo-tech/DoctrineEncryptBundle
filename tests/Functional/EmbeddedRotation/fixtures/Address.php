<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Embeddable]
class Address
{
    public function __construct(
        #[ORM\Column(type: 'string', length: 1024, nullable: true)]
        #[Encrypted]
        private ?string $city,
        #[ORM\Column(type: 'string', length: 255, nullable: true)]
        private ?string $country,
        #[ORM\Embedded(class: Geo::class)]
        private Geo $geo
    ) {
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    public function geo(): Geo
    {
        return $this->geo;
    }
}
