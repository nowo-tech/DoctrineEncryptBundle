<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Configuration;

/**
 * Marker interface for PHP 8 attribute classes that the bundle treats as encryption-related.
 *
 * The bundle only uses PHP 8 attributes (e.g. #[Encrypted]). AttributeReader filters
 * ReflectionAttribute instances by this interface so only our attributes are processed;
 * other attributes on the same property (e.g. #[ORM\Column]) are ignored.
 *
 * @internal
 */
interface Annotation
{
}
