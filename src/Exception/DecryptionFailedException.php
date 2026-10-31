<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use RuntimeException;
use Throwable;

use function sprintf;

/**
 * A value carries the encryption marker but cannot be decrypted (wrong or rotated key, corrupted or foreign ciphertext).
 *
 * Thrown by the Doctrine listener when `on_decrypt_failure: throw` is configured, and by
 * {@see EncryptUtil::decryptStrict()}. The message never contains the value.
 */
final class DecryptionFailedException extends RuntimeException implements ExceptionInterface
{
    /**
     * Failure while decrypting an entity property in the Doctrine listener.
     */
    public static function forProperty(string $entityClass, string $property, string $config, ?Throwable $previous = null): self
    {
        return new self(sprintf(
            'Could not decrypt %s::$%s (config "%s"): wrong or rotated key, or corrupted ciphertext.%s',
            $entityClass,
            $property,
            $config,
            $previous instanceof Throwable ? sprintf(' Cause: %s: %s', $previous::class, $previous->getMessage()) : '',
        ), 0, $previous);
    }

    /**
     * Failure while decrypting a standalone value (EncryptUtil::decryptStrict()).
     */
    public static function forValue(?string $config, Throwable $previous): self
    {
        return new self(sprintf(
            'Could not decrypt the value (config "%s"): wrong or rotated key, or corrupted ciphertext. Cause: %s: %s',
            $config ?? 'default',
            $previous::class,
            $previous->getMessage(),
        ), 0, $previous);
    }
}
