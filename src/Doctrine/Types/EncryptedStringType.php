<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use InvalidArgumentException;
use Stringable;

use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * `encrypted_string` (VARCHAR): a string encrypted with the profile's encryptor. Give the column room for the
 * ciphertext, which is much longer than the plaintext (see docs/USAGE.md).
 */
class EncryptedStringType extends AbstractEncryptedType
{
    public const NAME = 'encrypted_string';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    protected function toPlaintext(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf('DBAL type "%s" expects a string, a Stringable or null, got %s.', $this->getRegisteredName(), get_debug_type($value)));
    }

    protected function fromPlaintext(string $plaintext): mixed
    {
        return $plaintext;
    }
}
