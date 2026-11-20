<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
#[ORM\Table(name: 'ccs_account')]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'secret', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $secret,
        #[ORM\Column(name: 'not_secret', type: 'string', length: 255, nullable: true)]
        private ?string $notSecret,
        #[ORM\Embedded(class: Address::class)]
        private Address $address
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): void
    {
        $this->secret = $secret;
    }

    public function getNotSecret(): ?string
    {
        return $this->notSecret;
    }

    public function setNotSecret(?string $notSecret): void
    {
        $this->notSecret = $notSecret;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }
}
