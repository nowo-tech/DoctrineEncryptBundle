<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Assigned identifier (no generator): the identifier is part of the change set, so an insertion whose change set is
 * computed before the flush has original data, like a SEQUENCE identifier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ccs_badge')]
class Badge
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'integer')]
        private int $id,
        #[ORM\Column(name: 'secret', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $secret,
        #[ORM\Column(name: 'label', type: 'string', length: 64, nullable: true)]
        private ?string $label,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }
}
