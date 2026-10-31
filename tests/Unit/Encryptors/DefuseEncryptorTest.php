<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Exception\MissingEncryptionKeyException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function function_exists;

class DefuseEncryptorTest extends TestCase
{
    private const DATA = 'foobar';

    public function testEncrypt(): void
    {
        $dir = __DIR__ . '/fixtures';
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        $keyfile = $dir . '/defuse-test-' . uniqid('', true) . '.key';
        $key     = bin2hex(random_bytes(64));
        file_put_contents($keyfile, $key);
        $this->assertNotFalse(file_get_contents($keyfile), 'Key file must be readable after write');
        try {
            $defuse    = new DefuseEncryptor($keyfile);
            $encrypted = $defuse->encrypt(self::DATA);
            $this->assertNotSame(self::DATA, $encrypted);
            $decrypted = $defuse->decrypt($encrypted);
            $this->assertSame(self::DATA, $decrypted);
            $newkey = file_get_contents($keyfile);
            $this->assertNotFalse($newkey, 'Key file must still be readable after encrypt/decrypt');
            $this->assertSame($key, $newkey, 'The key must not be modified');
        } finally {
            if (file_exists($keyfile)) {
                unlink($keyfile);
            }
        }
    }

    public function testGenerateKey(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-' . md5((string) time());
        if (file_exists($keyfile)) {
            unlink($keyfile);
        }
        $defuse = new DefuseEncryptor($keyfile);
        $defuse->encrypt(self::DATA);

        $this->assertFileExists($keyfile);
        $this->assertNotEmpty(file_get_contents($keyfile), 'A key should have been created and saved to the file');

        unlink($keyfile);
    }

    public function testGeneratedKeyFileIsOwnerOnly(): void
    {
        $dir     = sys_get_temp_dir() . '/defuse-perm-' . uniqid('', true);
        $keyfile = $dir . '/nested/defuse.key';
        try {
            (new DefuseEncryptor($keyfile))->encrypt(self::DATA);

            clearstatcache();
            $this->assertFileExists($keyfile);
            $this->assertSame(0o600, fileperms($keyfile) & 0o777, 'Auto-generated key must not be group/world readable');
            $this->assertSame(0o770, fileperms($dir . '/nested') & 0o777, 'Created key directory must not be world-accessible');
        } finally {
            @unlink($keyfile);
            @rmdir($dir . '/nested');
            @rmdir($dir);
        }
    }

    public function testEncryptDecryptEmptyString(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-test-' . uniqid('', true) . '.key';
        $defuse  = new DefuseEncryptor($keyfile);
        $defuse->encrypt(''); // ensure key exists

        $encrypted = $defuse->encrypt('');
        $this->assertNotSame('', $encrypted);
        $decrypted = $defuse->decrypt($encrypted);
        $this->assertSame('', $decrypted);
        @unlink($keyfile);
    }

    public function testDecryptInvalidCiphertextThrows(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-test-' . uniqid('', true) . '.key';
        $defuse  = new DefuseEncryptor($keyfile);
        $defuse->encrypt(self::DATA); // ensure key exists

        $this->expectException(WrongKeyOrModifiedCiphertextException::class);
        $defuse->decrypt('not-valid-ciphertext');
        @unlink($keyfile);
    }

    public function testEncryptingSamePlaintextTwiceProducesDifferentCiphertext(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-test-' . uniqid('', true) . '.key';
        $defuse  = new DefuseEncryptor($keyfile);
        $defuse->encrypt(self::DATA); // ensure key exists

        $c1 = $defuse->encrypt(self::DATA);
        $c2 = $defuse->encrypt(self::DATA);

        $this->assertNotSame($c1, $c2);
        $this->assertSame(self::DATA, $defuse->decrypt($c1));
        $this->assertSame(self::DATA, $defuse->decrypt($c2));
        @unlink($keyfile);
    }

    public function testEncryptDecryptWithKeyContentFromString(): void
    {
        $keyContent = bin2hex(random_bytes(64));
        $defuse     = new DefuseEncryptor('/nonexistent/path.key', $keyContent);
        $encrypted  = $defuse->encrypt(self::DATA);
        $this->assertNotSame(self::DATA, $encrypted);
        $this->assertSame(self::DATA, $defuse->decrypt($encrypted));
        $this->assertFileDoesNotExist('/nonexistent/path.key');
    }

    public function testGetKeyThrowsWhenKeyFileEmptyAndNoKeyContent(): void
    {
        $defuse = new DefuseEncryptor('');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The encryption key environment variable is not set');
        $this->expectExceptionMessage('doctrine:encrypt:generate-secret-key');
        $defuse->encrypt(self::DATA);
    }

    public function testConstructorWithEmptyKeyContentUsesKeyFile(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-empty-content-' . uniqid('', true) . '.key';
        $key     = bin2hex(random_bytes(64));
        file_put_contents($keyfile, $key);
        try {
            $defuse    = new DefuseEncryptor($keyfile, '');
            $encrypted = $defuse->encrypt(self::DATA);
            $this->assertSame(self::DATA, $defuse->decrypt($encrypted));
        } finally {
            @unlink($keyfile);
        }
    }

    public function testMissingKeyFileIsNotGeneratedWhenGenerationIsDisabled(): void
    {
        $keyfile   = sys_get_temp_dir() . '/defuse-missing-' . uniqid('', true) . '.key';
        $encryptor = new DefuseEncryptor($keyfile, null, false);

        try {
            $encryptor->encrypt(self::DATA);
            $this->fail('MissingEncryptionKeyException expected');
        } catch (MissingEncryptionKeyException $e) {
            $this->assertStringContainsString($keyfile, $e->getMessage());
        }
        $this->assertFileDoesNotExist($keyfile);
    }

    public function testExistingKeyFileIsUsedWhenGenerationIsDisabled(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-existing-' . uniqid('', true) . '.key';
        (new DefuseEncryptor($keyfile))->encrypt(self::DATA);
        $key = file_get_contents($keyfile);

        try {
            $encryptor = new DefuseEncryptor($keyfile, null, false);
            $this->assertSame(self::DATA, $encryptor->decrypt($encryptor->encrypt(self::DATA)));
            $this->assertSame($key, file_get_contents($keyfile));
        } finally {
            @unlink($keyfile);
        }
    }

    public function testUnreadableKeyFileIsNeverReplaced(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can read a 0000 file.');
        }
        $keyfile = sys_get_temp_dir() . '/defuse-unreadable-' . uniqid('', true) . '.key';
        (new DefuseEncryptor($keyfile))->encrypt(self::DATA);
        $key = file_get_contents($keyfile);
        chmod($keyfile, 0o000);

        try {
            (new DefuseEncryptor($keyfile))->encrypt(self::DATA);
            $this->fail('MissingEncryptionKeyException expected');
        } catch (MissingEncryptionKeyException $e) {
            $this->assertStringContainsString('cannot be read', $e->getMessage());
        } finally {
            chmod($keyfile, 0o600);
            $this->assertSame($key, file_get_contents($keyfile));
            @unlink($keyfile);
        }
    }
}
