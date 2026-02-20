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

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * @param mixed $secret
     */
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

}
