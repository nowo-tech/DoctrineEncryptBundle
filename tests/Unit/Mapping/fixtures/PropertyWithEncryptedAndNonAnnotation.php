<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures;

use Attribute;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;

/** @internal */
class PropertyWithEncryptedAndNonAnnotation
{
    #[NonAnnotationAttribute]
    #[Encrypted]
    public string $value = '';
}

/** @internal */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NonAnnotationAttribute
{
}
