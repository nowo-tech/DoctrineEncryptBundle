<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Single-table inheritance root with a private encrypted field (read by the listener through the parent class).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ccs_animal')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string', length: 16)]
#[ORM\DiscriminatorMap(['animal' => Animal::class, 'dog' => Dog::class])]
class Animal
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(name: 'chip', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $chip
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChip(): ?string
    {
        return $this->chip;
    }

    public function setChip(?string $chip): void
    {
        $this->chip = $chip;
    }
}
