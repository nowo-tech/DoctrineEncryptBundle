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
    private ?int $id = null;

    #[ORM\Column(type: 'string', nullable: true)]
    #[Encrypted]
    private ?string $secret = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $notSecret = null;

    #[ORM\OneToOne(targetEntity: CascadeTarget::class, cascade: ['persist'])]
    private ?CascadeTarget $cascaded = null;

    public function getId()
    {
        return $this->id;
    }

    public function getSecret()
    {
        return $this->secret;
    }

    public function setSecret($secret): void
    {
        $this->secret = $secret;
    }

    /**
     * @return mixed
     */
    public function getNotSecret()
    {
        return $this->notSecret;
    }

    /**
     * @param mixed $notSecret
     */
    public function setNotSecret($notSecret): void
    {
        $this->notSecret = $notSecret;
    }

    /**
     * @return mixed
     */
    public function getCascaded()
    {
        return $this->cascaded;
    }

    /**
     * @param mixed $cascaded
     */
    public function setCascaded($cascaded): void
    {
        $this->cascaded = $cascaded;
    }
}
