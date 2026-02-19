<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Example entity using multiple encryptor configs (personal_data and financial_data).
 * See config/packages/nowo_doctrine_encrypt.yaml and docs/CONFIGURATION.md.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sensitive_record')]
class SensitiveRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Encrypted('personal_data')]
    private ?string $personalNote = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Encrypted('financial_data')]
    private ?string $financialNote = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPersonalNote(): ?string
    {
        return $this->personalNote;
    }

    public function setPersonalNote(?string $personalNote): static
    {
        $this->personalNote = $personalNote;
        return $this;
    }

    public function getFinancialNote(): ?string
    {
        return $this->financialNote;
    }

    public function setFinancialNote(?string $financialNote): static
    {
        $this->financialNote = $financialNote;
        return $this;
    }
}
