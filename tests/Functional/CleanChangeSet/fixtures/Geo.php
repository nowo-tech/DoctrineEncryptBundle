<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Embeddable]
class Geo
{
    public function __construct(
        #[ORM\Column(name: 'point', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $point
    ) {
    }

    public function getPoint(): ?string
    {
        return $this->point;
    }

    public function setPoint(?string $point): void
    {
        $this->point = $point;
    }
}
