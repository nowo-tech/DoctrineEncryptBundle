<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain;

use DomainException;

use function str_contains;

/**
 * Domain entity mapped in XML, without any bundle or Doctrine import.
 *
 * Its setters validate their input and record every call, so a test can prove the bundle never passed ciphertext
 * through them.
 */
final class Customer
{
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    private ?string $email = null;

    private ?string $phone = null;

    private ?Iban $iban = null;

    private ?string $notes = null;

    /** @var array<string, mixed>|null */
    private ?array $preferences = null;

    private Address $address;

    /** @var list<string> values received by the setters */
    public array $setterCalls = [];

    public function __construct()
    {
        $this->address = new Address();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->setterCalls[] = $email;
        if (!str_contains($email, '@')) {
            throw new DomainException('Invalid e-mail.');
        }
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->setterCalls[] = (string) $phone;
        $this->phone         = $phone;
    }

    public function getIban(): ?Iban
    {
        return $this->iban;
    }

    public function setIban(?Iban $iban): void
    {
        $this->iban = $iban;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    /** @return array<string, mixed>|null */
    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    /** @param array<string, mixed>|null $preferences */
    public function setPreferences(?array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function moveTo(Address $address): void
    {
        $this->address = $address;
    }
}
