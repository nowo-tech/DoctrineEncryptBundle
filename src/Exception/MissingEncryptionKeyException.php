<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use RuntimeException;

use function sprintf;

/**
 * The key file of a file-based profile does not exist and the bundle is not allowed to generate one
 * (`generate_missing_key: false`, the default outside debug mode).
 */
final class MissingEncryptionKeyException extends RuntimeException implements ExceptionInterface
{
    public static function forFile(string $keyFile): self
    {
        return new self(sprintf(
            'The encryption key file "%s" does not exist. Restore it from your backup, or create it explicitly with "php bin/console doctrine:encrypt:generate-secret-key" (only for a new installation: data encrypted with a lost key cannot be recovered). To let the bundle create missing keys on first use, set nowo_doctrine_encrypt.generate_missing_key: true.',
            $keyFile,
        ));
    }

    public static function unreadable(string $keyFile): self
    {
        return new self(sprintf('The encryption key file "%s" exists but cannot be read (check its owner and permissions). The bundle never replaces an existing key file.', $keyFile));
    }
}
