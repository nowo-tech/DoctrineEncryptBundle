<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/**
 * #[Encrypted] properties that PropertyAccess cannot reach: private without accessors, a setter with two required
 * arguments, and an uninitialized typed property.
 */
class EntityWithoutAccessors
{
    #[Encrypted]
    private ?string $token = null;

    #[Encrypted]
    private ?string $label = null;

    #[Encrypted]
    // @phpstan-ignore property.onlyRead, property.uninitializedReadonly (intentionally never initialized)
    private readonly string $neverSet;

    public int $labelSetterCalls = 0;

    public static function create(?string $token, ?string $label): self
    {
        $entity        = new self();
        $entity->token = $token;
        $entity->label = $label;

        return $entity;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** Two required arguments: not a PropertyAccess setter. */
    public function setLabel(?string $label, string $reason): void
    {
        ++$this->labelSetterCalls;
        $this->label = $label . $reason;
    }

    public function isNeverSetInitialized(): bool
    {
        return isset($this->neverSet);
    }
}
