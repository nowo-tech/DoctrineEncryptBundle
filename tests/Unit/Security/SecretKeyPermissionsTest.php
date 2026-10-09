<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Security;

use Nowo\DoctrineEncryptBundle\Security\SecretKeyPermissions;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretKeyPermissionsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nowo-encrypt-perms-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    public function testEnsureDirectoryCreatesWithRestrictedMode(): void
    {
        $dir = $this->root . '/var/secrets';
        SecretKeyPermissions::ensureDirectory($dir);

        clearstatcache();
        self::assertDirectoryExists($dir);
        self::assertSame(0o770, fileperms($dir) & 0o777);
    }

    public function testEnsureDirectoryHonoursCustomModeAndLeavesExistingDirectoriesAlone(): void
    {
        $dir = $this->root . '/keys';
        SecretKeyPermissions::ensureDirectory($dir, 0o700);
        clearstatcache();
        self::assertSame(0o700, fileperms($dir) & 0o777);

        chmod($dir, 0o755);
        SecretKeyPermissions::ensureDirectory($dir, 0o700);
        clearstatcache();
        self::assertSame(0o755, fileperms($dir) & 0o777);

        SecretKeyPermissions::ensureDirectory('');
    }

    public function testEnsureDirectoryThrowsWhenItCannotBeCreated(): void
    {
        mkdir($this->root, 0o700, true);
        $file = $this->root . '/not-a-dir';
        file_put_contents($file, 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create encryption key directory');
        SecretKeyPermissions::ensureDirectory($file . '/sub');
    }

    public function testHardenFile(): void
    {
        mkdir($this->root, 0o700, true);
        $key = $this->root . '/.Halite.default.key';
        file_put_contents($key, 'k');
        chmod($key, 0o644);

        self::assertTrue(SecretKeyPermissions::hardenFile($key));
        clearstatcache();
        self::assertSame(0o600, fileperms($key) & 0o777);

        // Already correct: no-op, still true.
        self::assertTrue(SecretKeyPermissions::hardenFile($key));

        self::assertTrue(SecretKeyPermissions::hardenFile($key, 0o640));
        clearstatcache();
        self::assertSame(0o640, fileperms($key) & 0o777);

        self::assertFalse(SecretKeyPermissions::hardenFile($this->root . '/missing.key'));
        self::assertFalse(SecretKeyPermissions::hardenFile($this->root));
        self::assertFalse(SecretKeyPermissions::hardenFile(''));
    }
}
