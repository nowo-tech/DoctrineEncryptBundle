<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Infrastructure;

use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain\Iban;

/**
 * Host type: an Iban value object stored encrypted (registered as "encrypted_iban.financial_data").
 */
final class EncryptedIbanType extends EncryptedStringType
{
    protected function toPlaintext(mixed $value): ?string
    {
        return $value instanceof Iban ? $value->value : parent::toPlaintext($value);
    }

    protected function fromPlaintext(string $plaintext): mixed
    {
        return new Iban($plaintext);
    }
}
