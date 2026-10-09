<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Security;

use RuntimeException;

use function sprintf;

/**
 * Filesystem permission helpers for encryption key files and their directories.
 *
 * Key files must never be group/world readable: anyone able to read the key can decrypt every
 * encrypted column. Directories created by the bundle are not world-accessible either.
 *
 * Existing directories are never chmod-ed (the default key directory is the project root).
 */
final class SecretKeyPermissions
{
    /** Default mode for key directories created by the bundle (owner + group, e.g. www-data). */
    public const DEFAULT_DIRECTORY_MODE = 0o770;

    /** Default mode for key files (owner read/write only). */
    public const DEFAULT_FILE_MODE = 0o600;

    /**
     * Creates the directory (recursively) with the given mode when it does not exist.
     *
     * @throws RuntimeException when the directory cannot be created
     */
    public static function ensureDirectory(string $directory, int $mode = self::DEFAULT_DIRECTORY_MODE): void
    {
        if ($directory === '' || is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, $mode, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create encryption key directory: %s', $directory));
        }

        // mkdir() applies the process umask; enforce the requested mode on the leaf directory.
        @chmod($directory, $mode);
    }

    /**
     * Restricts a key file to the given mode when it exists and its mode differs.
     *
     * @return bool true when the file now has (or already had) the requested mode
     */
    public static function hardenFile(string $path, int $mode = self::DEFAULT_FILE_MODE): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $perms = @fileperms($path);
        if ($perms !== false && ($perms & 0o777) === ($mode & 0o777)) {
            return true;
        }

        return @chmod($path, $mode);
    }
}
