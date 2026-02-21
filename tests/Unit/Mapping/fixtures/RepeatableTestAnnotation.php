<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures;

use Attribute;
use Nowo\DoctrineEncryptBundle\Configuration\Annotation;

/** @internal */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class RepeatableTestAnnotation implements Annotation
{
    public function __construct(public string $value = '')
    {
    }
}
