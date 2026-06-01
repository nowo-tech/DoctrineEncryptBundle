<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use PHPUnit\Framework\TestCase;

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
        $dir  = sys_get_temp_dir() . '/mysql-aes-' . uniqid('', true);
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
}
