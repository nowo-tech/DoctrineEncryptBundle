<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

/**
 * Embeddable without attributes, selected through encrypted_fields.
 */
final class PlainAddress
{
    public function __construct(private ?string $street = null)
    {
    }

    public function street(): ?string
    {
        return $this->street;
    }
}
