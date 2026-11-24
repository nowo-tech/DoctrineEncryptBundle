<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Encryptors;

use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedJsonType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtilInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use SensitiveParameter;
use SensitiveParameterValue;
use Throwable;

use function sprintf;

/**
 * Audit ZAW-I4: plaintext and key material passed to the encryptors never appear in exception stack traces.
 *
 * PHP 8.2+ replaces a #[\SensitiveParameter] argument with a {@see SensitiveParameterValue} in every trace frame, even
 * when `zend.exception_ignore_args` is off (development php.ini), so loggers and error pages cannot print it.
 */
final class SensitiveParameterRedactionTest extends TestCase
{
    private string|false $previousIgnoreArgs = false;

    protected function setUp(): void
    {
        // Traces only carry arguments when exception_ignore_args is off: force it so the redaction is really tested.
        $this->previousIgnoreArgs = ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        if ($this->previousIgnoreArgs !== false) {
            ini_set('zend.exception_ignore_args', $this->previousIgnoreArgs);
        }
    }

    public function testHaliteEncryptRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $encryptor = new HaliteEncryptor('', null, false);

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt($plaintext));

        $this->assertFrameRedacts($e, HaliteEncryptor::class, 'encrypt', 0, $plaintext);
    }

    public function testHaliteKeyContentIsRedactedInTrace(): void
    {
        // Not a valid hex Halite key: importing it throws inside loadKeyFromString().
        $keyContent = $this->marker();
        $encryptor  = new HaliteEncryptor('', $keyContent, false);

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt('x'));

        $this->assertFrameRedacts($e, HaliteEncryptor::class, 'loadKeyFromString', 0, $keyContent);
    }

    public function testDefuseEncryptRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $encryptor = new DefuseEncryptor('', null, false);

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt($plaintext));

        $this->assertFrameRedacts($e, DefuseEncryptor::class, 'encrypt', 0, $plaintext);
    }

    public function testMysqlAesEncryptRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $encryptor = new MysqlAesEncryptor(sys_get_temp_dir() . '/missing-' . uniqid('', true) . '.key');

        $e = $this->catchThrowable(static fn (): string => $encryptor->encrypt($plaintext));

        $this->assertFrameRedacts($e, MysqlAesEncryptor::class, 'encrypt', 0, $plaintext);
    }

    public function testEncryptUtilRedactsPlaintextInTrace(): void
    {
        $plaintext = $this->marker();
        $failing   = $this->createMock(EncryptorInterface::class);
        // Thrown from inside the call (an exception built up front would carry the test's trace, not EncryptUtil's).
        $failing->method('encrypt')->willReturnCallback(static fn (): never => throw new RuntimeException('encryptor failure'));
        $registry = $this->createMock(EncryptorRegistryInterface::class);
        $registry->method('getDefault')->willReturn($failing);
        $util = new EncryptUtil($registry);

        $e = $this->catchThrowable(static fn (): ?string => $util->encrypt($plaintext));

        $this->assertFrameRedacts($e, EncryptUtil::class, 'encrypt', 0, $plaintext);
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function sensitiveParameters(): iterable
    {
        yield 'Halite key content' => [HaliteEncryptor::class, '__construct', 'keyContent'];
        yield 'Halite plaintext' => [HaliteEncryptor::class, 'encrypt', 'data'];
        yield 'Halite key import' => [HaliteEncryptor::class, 'loadKeyFromString', 'content'];
        yield 'Defuse key content' => [DefuseEncryptor::class, '__construct', 'keyContent'];
        yield 'Defuse plaintext' => [DefuseEncryptor::class, 'encrypt', 'data'];
        yield 'MysqlAes key content' => [MysqlAesEncryptor::class, '__construct', 'keyContent'];
        yield 'MysqlAes plaintext' => [MysqlAesEncryptor::class, 'encrypt', 'data'];
        yield 'MysqlAes passphrase' => [MysqlAesEncryptor::class, 'deriveKey', 'passphrase'];
        yield 'EncryptorInterface plaintext' => [EncryptorInterface::class, 'encrypt', 'data'];
        yield 'EncryptUtil plaintext' => [EncryptUtil::class, 'encrypt', 'value'];
        yield 'EncryptUtilInterface plaintext' => [EncryptUtilInterface::class, 'encrypt', 'value'];
        yield 'DBAL type value' => [AbstractEncryptedType::class, 'convertToDatabaseValue', 'value'];
        yield 'DBAL string type plaintext' => [EncryptedStringType::class, 'toPlaintext', 'value'];
        yield 'DBAL json type plaintext' => [EncryptedJsonType::class, 'toPlaintext', 'value'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('sensitiveParameters')]
    public function testParameterIsMarkedSensitive(string $class, string $method, string $parameter): void
    {
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $param) {
            if ($param->getName() === $parameter) {
                $this->assertCount(1, $param->getAttributes(SensitiveParameter::class), sprintf('%s::%s($%s) must be #[\SensitiveParameter]', $class, $method, $parameter));

                return;
            }
        }

        self::fail(sprintf('Parameter $%s not found on %s::%s()', $parameter, $class, $method));
    }

    private function marker(): string
    {
        return 'ZAW-I4-' . bin2hex(random_bytes(8));
    }

    private function catchThrowable(callable $call): Throwable
    {
        try {
            $call();
        } catch (Throwable $e) {
            return $e;
        }

        self::fail('An exception was expected.');
    }

    private function assertFrameRedacts(Throwable $e, string $class, string $function, int $index, string $secret): void
    {
        $found = false;
        for ($t = $e; $t instanceof Throwable; $t = $t->getPrevious()) {
            foreach ($t->getTrace() as $frame) {
                if (($frame['class'] ?? null) === $class && $frame['function'] === $function) {
                    $found = true;
                    $this->assertArrayHasKey('args', $frame, 'Trace arguments must be collected for this test');
                    $arg = $frame['args'][$index] ?? null;
                    $this->assertInstanceOf(SensitiveParameterValue::class, $arg);
                    $this->assertSame($secret, $arg->getValue(), 'The original value stays reachable for deliberate use only');
                }
            }
            $this->assertStringNotContainsString($secret, $t->getTraceAsString());
            // Bundle frames only: the test's own closures legitimately capture the value.
            $bundleFrames = array_filter($t->getTrace(), static fn (array $f): bool => str_starts_with($f['class'] ?? '', 'Nowo\\DoctrineEncryptBundle\\') && !str_starts_with($f['class'] ?? '', 'Nowo\\DoctrineEncryptBundle\\Tests\\'));
            $this->assertStringNotContainsString($secret, print_r($bundleFrames, true));
        }

        $this->assertTrue($found, sprintf('No %s::%s() frame in the trace', $class, $function));
        $this->assertStringNotContainsString($secret, (string) $e);
    }
}
