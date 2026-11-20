<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet;

use Closure;

/**
 * A host application's Doctrine listener: the event manager calls the method named after the event.
 */
final readonly class HostListener
{
    public function __construct(private Closure $callback)
    {
    }

    public function onFlush(object $args): void
    {
        ($this->callback)($args);
    }

    public function preUpdate(object $args): void
    {
        ($this->callback)($args);
    }

    public function postFlush(object $args): void
    {
        ($this->callback)($args);
    }
}
