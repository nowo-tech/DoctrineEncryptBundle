<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

class EntityWithConfigAlias
{
    #[Encrypted('default')]
    public string $defaultField = '';

    #[Encrypted('other_config')]
    public string $otherField = '';

    public function __construct(string $defaultField = '', string $otherField = '')
    {
        $this->defaultField = $defaultField;
        $this->otherField   = $otherField;
    }
}
