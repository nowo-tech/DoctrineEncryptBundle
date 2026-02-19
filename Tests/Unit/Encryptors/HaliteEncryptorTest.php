<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use ParagonIE\Halite\KeyFactory;
use PHPUnit\Framework\TestCase;

class HaliteEncryptorTest extends TestCase
{
    private const DATA = 'foobar';

    public function testEncryptExtension(): void
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $dir = __DIR__ . '/fixtures';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $keyfile = $dir . '/halite-test-' . uniqid('', true) . '.key';
        $keyObj = KeyFactory::generateEncryptionKey();
        KeyFactory::save($keyObj, $keyfile);
        $key = file_get_contents($keyfile);
        $this->assertNotFalse($key, 'Key file must be readable after write');
        try {
            $halite = new HaliteEncryptor($keyfile);
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
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-' . md5(time());
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
        $halite = new HaliteEncryptor($keyfile);

        $this->expectException(\SodiumException::class);
        $halite->encrypt(self::DATA);
    }

    public function testEncryptDecryptEmptyString(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-test-' . uniqid('', true) . '.key';
        $halite = new HaliteEncryptor($keyfile);
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
        $halite = new HaliteEncryptor($keyfile);
        $halite->encrypt(self::DATA); // ensure key exists

        $this->expectException(\ParagonIE\Halite\Alerts\InvalidMessage::class);
        $halite->decrypt('not-valid-ciphertext');
    }

    public function testEncryptingSamePlaintextTwiceProducesDifferentCiphertext(): void
    {
        if (!extension_loaded('sodium')) {
            $this->markTestSkipped('This test only runs when the sodium extension is enabled.');
        }
        $keyfile = sys_get_temp_dir() . '/halite-test-' . uniqid('', true) . '.key';
        $halite = new HaliteEncryptor($keyfile);
        $halite->encrypt(self::DATA); // ensure key exists

        $c1 = $halite->encrypt(self::DATA);
        $c2 = $halite->encrypt(self::DATA);

        $this->assertNotSame($c1, $c2);
        $this->assertSame(self::DATA, $halite->decrypt($c1));
        $this->assertSame(self::DATA, $halite->decrypt($c2));
        @unlink($keyfile);
    }
}
