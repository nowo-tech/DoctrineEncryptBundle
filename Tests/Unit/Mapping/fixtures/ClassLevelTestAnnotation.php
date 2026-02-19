<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures;

use Nowo\DoctrineEncryptBundle\Configuration\Annotation;

/** @internal */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ClassLevelTestAnnotation implements Annotation
{
}
