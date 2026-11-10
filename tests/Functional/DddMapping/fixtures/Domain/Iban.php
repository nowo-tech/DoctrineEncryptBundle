<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain;

use InvalidArgumentException;

/**
 * Value object that rejects anything that is not an IBAN (ciphertext included).
 */
final readonly class Iban
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $value) !== 1) {
            throw new InvalidArgumentException('Not an IBAN.');
        }
    }
}
