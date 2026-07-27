<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function strlen;

class MysqlAesEncryptorTest extends TestCase
{
    private const PASSPHRASE = 'demo-mysql-aes-key';

    public function testEncryptDecryptRoundTrip(): void
    {
        $encryptor = new MysqlAesEncryptor('', self::PASSPHRASE);
        $plain     = 'Sensitive note for MySQL AES';

        $cipher = $encryptor->encrypt($plain);
        $this->assertNotSame($plain, $cipher);
        $this->assertSame($plain, $encryptor->decrypt($cipher));
    }

    public function testDeriveKeyMatchesMysqlPadding(): void
    {
        $this->assertSame(16, strlen(MysqlAesEncryptor::deriveKey('short')));
        $this->assertSame('long-passphrase-', MysqlAesEncryptor::deriveKey('long-passphrase-xyz-extra'));
    }

    public function testReadsPassphraseFromFile(): void
    {
        $dir = sys_get_temp_dir() . '/mysql-aes-' . uniqid('', true);
        mkdir($dir);
        $file = $dir . '/key.txt';
        file_put_contents($file, self::PASSPHRASE . "\n");

        try {
            $encryptor = new MysqlAesEncryptor($file);
            $this->assertSame(self::PASSPHRASE, $encryptor->getPassphrase());
            $this->assertSame('hello', $encryptor->decrypt($encryptor->encrypt('hello')));
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    public function testMissingKeyFileThrows(): void
    {
        $encryptor = new MysqlAesEncryptor('/nonexistent/mysql-aes-key-' . uniqid('', true));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('key file not found');
        $encryptor->getPassphrase();
    }

    public function testKeyPathDirectoryThrows(): void
    {
        $dir = sys_get_temp_dir() . '/mysql-aes-dir-' . uniqid('', true);
        mkdir($dir);

        try {
            $encryptor = new MysqlAesEncryptor($dir);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('is a directory');
            $encryptor->getPassphrase();
        } finally {
            @rmdir($dir);
        }
    }

    public function testEmptyKeyContentFallsBackToFile(): void
    {
        $dir = sys_get_temp_dir() . '/mysql-aes-empty-' . uniqid('', true);
        mkdir($dir);
        $file = $dir . '/key.txt';
        file_put_contents($file, "from-file\n");

        try {
            $encryptor = new MysqlAesEncryptor($file, '');
            $this->assertSame('from-file', $encryptor->getPassphrase());
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }
}
