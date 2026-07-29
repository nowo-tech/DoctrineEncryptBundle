<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use ParagonIE\Halite\Alerts\InvalidMessage;
use ParagonIE\Halite\KeyFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SodiumException;
use Throwable;

use function extension_loaded;

class HaliteEncryptorTest extends TestCase
{
    private const DATA = 'foobar';

    public function testEncryptExtension(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $dir = __DIR__ . '/fixtures';
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        $keyfile = $dir . '/halite-test-' . uniqid('', true) . '.key';
        $keyObj  = KeyFactory::generateEncryptionKey();
        KeyFactory::save($keyObj, $keyfile);
        $key = file_get_contents($keyfile);
        $this->assertNotFalse($key, 'Key file must be readable after write');
        try {
            $halite    = new HaliteEncryptor($keyfile);
            $encrypted = $halite->encrypt(self::DATA);
            $this->assertNotSame(self::DATA, $encrypted);
            $decrypted = $halite->decrypt($encrypted);
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
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-' . md5((string) time());
        if (file_exists($keyfile)) {
            unlink($keyfile);
        }
        $halite = new HaliteEncryptor($keyfile);
        $halite->encrypt(self::DATA);

        $this->assertFileExists($keyfile);
        $this->assertNotEmpty(file_get_contents($keyfile), 'A key should have been created and saved to the file');

        unlink($keyfile);
    }

    public function testEncryptWithoutExtensionThrowsException(): void
    {
        if (extension_loaded('sodium')) {
            $this->markTestSkipped('This only runs when the sodium extension is disabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-test-' . uniqid('', true) . '.key';
        $halite  = new HaliteEncryptor($keyfile);

        $this->expectException(SodiumException::class);
        $halite->encrypt(self::DATA);
    }

    public function testEncryptDecryptEmptyString(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-test-' . uniqid('', true) . '.key';
        $halite  = new HaliteEncryptor($keyfile);
        $halite->encrypt(''); // ensure key exists

        $encrypted = $halite->encrypt('');
        $this->assertNotSame('', $encrypted);
        $decrypted = $halite->decrypt($encrypted);
        $this->assertSame('', $decrypted);
        @unlink($keyfile);
    }

    public function testDecryptInvalidCiphertextThrows(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-test-' . uniqid('', true) . '.key';
        $halite  = new HaliteEncryptor($keyfile);
        $halite->encrypt(self::DATA); // ensure key exists

        $this->expectException(InvalidMessage::class);
        $halite->decrypt('not-valid-ciphertext');
    }

    public function testEncryptingSamePlaintextTwiceProducesDifferentCiphertext(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-test-' . uniqid('', true) . '.key';
        $halite  = new HaliteEncryptor($keyfile);
        $halite->encrypt(self::DATA); // ensure key exists

        $c1 = $halite->encrypt(self::DATA);
        $c2 = $halite->encrypt(self::DATA);

        $this->assertNotSame($c1, $c2);
        $this->assertSame(self::DATA, $halite->decrypt($c1));
        $this->assertSame(self::DATA, $halite->decrypt($c2));
        @unlink($keyfile);
    }

    public function testEncryptDecryptWithKeyContentFromString(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-nowhere.key';
        $keyObj  = KeyFactory::generateEncryptionKey();
        $tmp     = tempnam(sys_get_temp_dir(), 'halite_');
        KeyFactory::save($keyObj, $tmp);
        $keyContent = trim((string) file_get_contents($tmp));
        @unlink($tmp);

        $halite    = new HaliteEncryptor($keyfile, $keyContent);
        $encrypted = $halite->encrypt(self::DATA);
        $this->assertNotSame(self::DATA, $encrypted);
        $this->assertSame(self::DATA, $halite->decrypt($encrypted));
        $this->assertFileDoesNotExist($keyfile);
    }

    public function testConstructorWithEmptyKeyContentUsesKeyFile(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-empty-content-' . uniqid('', true) . '.key';
        $keyObj  = KeyFactory::generateEncryptionKey();
        KeyFactory::save($keyObj, $keyfile);
        try {
            $halite    = new HaliteEncryptor($keyfile, '');
            $encrypted = $halite->encrypt(self::DATA);
            $this->assertSame(self::DATA, $halite->decrypt($encrypted));
        } finally {
            @unlink($keyfile);
        }
    }

    public function testNormalizeKeyFileTrimsTrailingNewline(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-newline-' . uniqid('', true) . '.key';
        $keyObj  = KeyFactory::generateEncryptionKey();
        $tmp     = tempnam(sys_get_temp_dir(), 'halite_');
        KeyFactory::save($keyObj, $tmp);
        $keyContent = file_get_contents($tmp);
        file_put_contents($keyfile, $keyContent . "\n");
        @unlink($tmp);
        try {
            $halite = new HaliteEncryptor($keyfile);
            $halite->encrypt(self::DATA);
            $this->assertSame(trim((string) $keyContent), (string) file_get_contents($keyfile));
        } finally {
            @unlink($keyfile);
        }
    }

    public function testInvalidKeyFileHexThrowsRuntimeException(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-invalid-' . uniqid('', true) . '.key';
        file_put_contents($keyfile, 'not-valid-hex-content-zz');
        try {
            $halite = new HaliteEncryptor($keyfile);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Invalid Halite key file');
            $this->expectExceptionMessage('Expected hexadecimal');
            $halite->encrypt(self::DATA);
        } finally {
            @unlink($keyfile);
        }
    }

    public function testGetKeyThrowsWhenKeyFileEmptyAndNoKeyContent(): void
    {
        $halite = new HaliteEncryptor('');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The encryption key environment variable is not set');
        $this->expectExceptionMessage('doctrine:encrypt:generate-secret-key');
        $halite->encrypt(self::DATA);
    }

    public function testEncryptDecryptWithKeyContentWithWhitespaceTrimsContent(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyObj = KeyFactory::generateEncryptionKey();
        $tmp    = tempnam(sys_get_temp_dir(), 'halite_');
        KeyFactory::save($keyObj, $tmp);
        $keyContent = "\n  " . trim((string) file_get_contents($tmp)) . "  \n";
        @unlink($tmp);

        $halite    = new HaliteEncryptor('/nonexistent.key', $keyContent);
        $encrypted = $halite->encrypt(self::DATA);
        $this->assertNotSame(self::DATA, $encrypted);
        $this->assertSame(self::DATA, $halite->decrypt($encrypted));
    }

    /** Covers normalizeKeyFile() early return when key path is not a file (e.g. is a directory). */
    public function testEncryptThrowsWhenKeyPathIsDirectory(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $dir = sys_get_temp_dir() . '/halite-dir-' . uniqid('', true);
        mkdir($dir, 0o755, true);
        try {
            $halite = new HaliteEncryptor($dir);
            $this->expectException(Throwable::class);
            @$halite->encrypt(self::DATA);
        } finally {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }
    }
}
