<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
class CascadeTarget
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Encrypted]
    private ?string $secret = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $notSecret = null;

    public function getId(): int
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
}
