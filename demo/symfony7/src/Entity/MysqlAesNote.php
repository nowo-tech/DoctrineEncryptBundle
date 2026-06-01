<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MysqlAesNoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Demo entity for MysqlAes encryptor (ORM path) plus native AES_ENCRYPT column (repository path).
 */
#[ORM\Entity(repositoryClass: MysqlAesNoteRepository::class)]
#[ORM\Table(name: 'mysql_aes_note')]
class MysqlAesNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    /** Encrypted by Doctrine subscriber (MysqlAes + &lt;ENC&gt; marker). */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Encrypted('mysql_aes')]
    private ?string $secretOrm = null;

    /** Filled only via repository SQL (AES_ENCRYPT); not mapped for ORM writes. */
    #[ORM\Column(type: 'blob', nullable: true)]
    private $secretNative = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSecretOrm(): ?string
    {
        return $this->secretOrm;
    }

    public function setSecretOrm(?string $secretOrm): static
    {
        $this->secretOrm = $secretOrm;

        return $this;
    }

    public function getSecretNative(): ?string
    {
        if ($this->secretNative === null) {
            return null;
        }

        return is_resource($this->secretNative)
            ? stream_get_contents($this->secretNative) ?: null
            : (string) $this->secretNative;
    }
}
