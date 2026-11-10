<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain;

/**
 * Embeddable mapped in XML: street uses the encrypted_string DBAL type, city is selected in encrypted_fields.
 */
final class Address
{
    public function __construct(
        private ?string $street = null,
        private ?string $city = null,
    ) {
    }

    public function street(): ?string
    {
        return $this->street;
    }

    public function city(): ?string
    {
        return $this->city;
    }
}
