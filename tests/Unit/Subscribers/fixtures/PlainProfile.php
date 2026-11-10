<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use InvalidArgumentException;

/**
 * No bundle attribute: selected through encrypted_fields. The setter rejects anything that looks like ciphertext.
 */
class PlainProfile
{
    public int $setterCalls = 0;

    private ?PlainAddress $address = null;

    public function __construct(
        private ?string $taxId = null,
        private ?string $nickname = null,
    ) {
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): void
    {
        ++$this->setterCalls;
        if ($taxId !== null && str_contains($taxId, 'encrypted-')) {
            throw new InvalidArgumentException('Not a tax id.');
        }
        $this->taxId = $taxId;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getAddress(): ?PlainAddress
    {
        return $this->address;
    }

    public function setAddress(?PlainAddress $address): void
    {
        $this->address = $address;
    }
}
