<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use JsonException;
use SensitiveParameter;

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

    protected function toPlaintext(#[SensitiveParameter] mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $this->withoutValue($e);
        }
    }

    protected function fromPlaintext(#[SensitiveParameter] string $plaintext): mixed
    {
        if ($plaintext === '') {
            return null;
        }

        try {
            return json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw $this->withoutValue($e);
        }
    }

    /**
     * The trace of a JsonException holds the json_encode() / json_decode() frame, whose argument is the plaintext
     * (built-in functions cannot be marked #[\SensitiveParameter]). A fresh exception, without $previous, keeps the
     * message and code but starts its trace in this class, where the argument is redacted.
     */
    private function withoutValue(JsonException $e): JsonException
    {
        return new JsonException($e->getMessage(), $e->getCode());
    }
}
