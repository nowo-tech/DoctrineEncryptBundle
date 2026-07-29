<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
class Owner
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Encrypted]
    private ?string $secret = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $notSecret = null;

    #[ORM\OneToOne(targetEntity: CascadeTarget::class, cascade: ['persist'])]
    private ?CascadeTarget $cascaded = null;

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

    public function getCascaded(): ?CascadeTarget
    {
        return $this->cascaded;
    }

    public function setCascaded(?CascadeTarget $cascaded): void
    {
        $this->cascaded = $cascaded;
    }
}
