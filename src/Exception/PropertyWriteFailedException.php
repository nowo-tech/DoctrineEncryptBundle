<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

/**
 * The Doctrine listener could not write an encrypted property (decrypted value on load, ciphertext on flush): the
 * entity's setter or typed property refused it (for example a TypeError, or a host setter that throws).
 *
 * Since 2.15.2 the listener throws this instead of the original exception, whose trace carries the value as a setter /
 * PropertyAccessor / ReflectionProperty argument. On purpose it has no previous exception and names the cause by class
 * only (a host setter's message may quote the value): neither the message nor the trace contains the value.
 */
final class PropertyWriteFailedException extends RuntimeException implements ExceptionInterface
{
    public static function forProperty(string $entityClass, string $property, string $config, Throwable $cause): self
    {
        return new self(sprintf(
            'Could not write %s::$%s (config "%s"): the setter or the typed property refused the value (cause: %s; message and trace omitted because they may contain the value).',
            $entityClass,
            $property,
            $config,
            $cause::class,
        ));
    }
}
