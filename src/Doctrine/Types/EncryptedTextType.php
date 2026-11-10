<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * `encrypted_text` (CLOB/TEXT): like `encrypted_string` for long values.
 */
class EncryptedTextType extends EncryptedStringType
{
    public const NAME = 'encrypted_text';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }
}
