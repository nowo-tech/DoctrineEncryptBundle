<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use PHPUnit\Framework\TestCase;

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
        $key = bin2hex(random_bytes(64));
        file_put_contents($keyfile, $key);
        $this->assertNotFalse(file_get_contents($keyfile), 'Key file must be readable after write');
        try {
            $defuse = new DefuseEncryptor($keyfile);
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
        $keyfile = sys_get_temp_dir() . '/defuse-' . md5(time());
        if (file_exists($keyfile)) {
            unlink($keyfile);
        }
        $defuse = new DefuseEncryptor($keyfile);
        $defuse->encrypt(self::DATA);

        $this->assertFileExists($keyfile);
        $this->assertNotEmpty(file_get_contents($keyfile), 'A key should have been created and saved to the file');

        unlink($keyfile);
    }

    public function testEncryptDecryptEmptyString(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-test-' . uniqid('', true) . '.key';
        $defuse = new DefuseEncryptor($keyfile);
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
        $defuse = new DefuseEncryptor($keyfile);
        $defuse->encrypt(self::DATA); // ensure key exists

        $this->expectException(\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException::class);
        $defuse->decrypt('not-valid-ciphertext');
        @unlink($keyfile);
    }

    public function testEncryptingSamePlaintextTwiceProducesDifferentCiphertext(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-test-' . uniqid('', true) . '.key';
        $defuse = new DefuseEncryptor($keyfile);
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
        $defuse = new DefuseEncryptor('/nonexistent/path.key', $keyContent);
        $encrypted = $defuse->encrypt(self::DATA);
        $this->assertNotSame(self::DATA, $encrypted);
        $this->assertSame(self::DATA, $defuse->decrypt($encrypted));
        $this->assertFileDoesNotExist('/nonexistent/path.key');
    }

    public function testGetKeyThrowsWhenKeyFileEmptyAndNoKeyContent(): void
    {
        $defuse = new DefuseEncryptor('', null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The encryption key environment variable is not set');
        $this->expectExceptionMessage('doctrine:encrypt:generate-secret-key');
        $defuse->encrypt(self::DATA);
    }

    public function testConstructorWithEmptyKeyContentUsesKeyFile(): void
    {
        $keyfile = sys_get_temp_dir() . '/defuse-empty-content-' . uniqid('', true) . '.key';
        $key = bin2hex(random_bytes(64));
        file_put_contents($keyfile, $key);
        try {
            $defuse = new DefuseEncryptor($keyfile, '');
            $encrypted = $defuse->encrypt(self::DATA);
            $this->assertSame(self::DATA, $defuse->decrypt($encrypted));
        } finally {
            @unlink($keyfile);
        }
    }
}
