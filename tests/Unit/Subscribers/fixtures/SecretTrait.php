<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

/**
 * A trait cannot be an encrypted_fields key (the listener looks up the using class).
 */
// @phpstan-ignore trait.unused (only referenced by name in a configuration test)
trait SecretTrait
{
    private ?string $secret = null;
}
