<?php

namespace Nowo\DoctrineEncryptBundle\Configuration;

use Attribute;

/**
 * The `Encrypted` class is a PHP attribute that can be applied to properties and is used as a
 * placeholder for encryption functionality.
 *
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted implements Annotation
{
    // Placeholder
}
