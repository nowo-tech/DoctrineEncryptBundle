<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use UnexpectedValueException;

/**
 * A value that must be ciphertext does not carry the encryption marker (or has an empty payload).
 *
 * Thrown by {@see EncryptUtil::decryptStrict()}, which never passes plaintext through.
 * The message never contains the value.
 */
final class ValueNotEncryptedException extends UnexpectedValueException implements ExceptionInterface
{
    public static function create(): self
    {
        return new self('The value is not encrypted: it does not end with the encryption marker or its ciphertext is empty.');
    }
}
