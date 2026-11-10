<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Nested embeddable (Person::$address->geo).
 */
#[ORM\Embeddable]
class Geo
{
    public function __construct(
        #[ORM\Column(type: 'string', length: 1024, nullable: true)]
        #[Encrypted]
        private ?string $coordinates
    ) {
    }

    public function coordinates(): ?string
    {
        return $this->coordinates;
    }
}
