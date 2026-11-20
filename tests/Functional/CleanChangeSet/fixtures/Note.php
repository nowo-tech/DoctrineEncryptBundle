<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures;

use Doctrine\ORM\Mapping as ORM;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * Lazy many-to-one: loading a note leaves its account as an uninitialized proxy. The note has an encrypted body of
 * its own, so a change of its account is made on a decrypted entity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ccs_note')]
class Note
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    // @phpstan-ignore property.unusedType (Doctrine sets id via reflection)
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Account::class, fetch: 'LAZY')]
        #[ORM\JoinColumn(name: 'account_id', nullable: false)]
        private Account $account,
        #[ORM\Column(name: 'body', type: 'text', nullable: true)]
        #[Encrypted]
        private ?string $body = 'note body',
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setAccount(Account $account): void
    {
        $this->account = $account;
    }
}
