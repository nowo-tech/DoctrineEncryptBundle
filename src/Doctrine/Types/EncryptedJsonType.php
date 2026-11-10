<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function json_decode;
use function json_encode;

use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;

/**
 * `encrypted_json` (CLOB/TEXT, not a native JSON column: the stored value is ciphertext): a value JSON-encoded, then
 * encrypted. Decodes to arrays, like DBAL's `json` type.
 */
class EncryptedJsonType extends AbstractEncryptedType
{
    public const NAME = 'encrypted_json';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    protected function toPlaintext(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    protected function fromPlaintext(string $plaintext): mixed
    {
        return $plaintext === '' ? null : json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
    }
}
