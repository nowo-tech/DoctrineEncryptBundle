<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

#[ORM\Entity]
class Dog extends Animal
{
    public function __construct(?string $chip, #[ORM\Column(name: 'keeper', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $keeper)
    {
        parent::__construct($chip);
    }

    public function getKeeper(): ?string
    {
        return $this->keeper;
    }

    public function setKeeper(?string $keeper): void
    {
        $this->keeper = $keeper;
    }
}
