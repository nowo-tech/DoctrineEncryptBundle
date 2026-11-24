<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use RuntimeException;
use SensitiveParameter;
use Throwable;

use function sprintf;

/**
 * The Doctrine listener could not write an encrypted property (decrypted value on load, ciphertext on flush): the
 * entity's setter or typed property refused it (for example a TypeError, or a host setter that throws).
 *
 * Since 2.16.0 the listener throws this instead of the original exception, whose trace carries the value as a setter /
 * PropertyAccessor / ReflectionProperty argument. On purpose it has no previous exception and names the cause by class
 * only (a host setter's message may quote the value): neither the message nor the trace contains the value.
 */
final class PropertyWriteFailedException extends RuntimeException implements ExceptionInterface
{
    /**
     * @param Throwable $cause The setter's exception: #[\SensitiveParameter] because its own trace carries the value, and
     *                         this frame is part of the new exception's trace (audit ZBC-D-L1). Only its class is used.
     */
    public static function forProperty(string $entityClass, string $property, string $config, #[SensitiveParameter] Throwable $cause): self
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
