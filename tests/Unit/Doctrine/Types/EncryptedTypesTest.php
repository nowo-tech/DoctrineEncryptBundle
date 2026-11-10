<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Doctrine\Types;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use LogicException;
use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedJsonType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTextType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntime;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntimeConfigurator;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use stdClass;
use Stringable;

use function is_scalar;
use function strlen;

final class EncryptedTypesTest extends TestCase
{
    private SQLitePlatform $platform;
    private EncryptorRegistry $registry;

    public static function setUpBeforeClass(): void
    {
        foreach (DoctrineEncryptExtension::dbalTypeMap(['financial_data']) as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
    }

    protected function setUp(): void
    {
        $this->platform = new SQLitePlatform();
        $this->registry = new EncryptorRegistry([
            'personal_data'  => $this->encryptor('p'),
            'financial_data' => $this->encryptor('f'),
            'default'        => $this->encryptor('p'),
        ], 'personal_data');
        EncryptedTypeRuntime::configure($this->registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW);
    }

    protected function tearDown(): void
    {
        EncryptedTypeRuntime::clear();
    }

    public function testStringRoundTripUsesTheListenerFormat(): void
    {
        $type   = Type::getType('encrypted_string');
        $stored = $type->convertToDatabaseValue('secret', $this->platform);

        self::assertSame('p:' . base64_encode('secret') . EncryptUtil::ENCRYPTION_MARKER, $stored);
        self::assertSame('secret', (new EncryptUtil($this->registry))->decryptStrict($stored));
        self::assertSame('secret', $type->convertToPHPValue($stored, $this->platform));
        self::assertSame('secret', $type->convertToPHPValue((new EncryptUtil($this->registry))->encrypt('secret'), $this->platform));
    }

    public function testProfileComesFromTheTypeName(): void
    {
        $default   = Type::getType('encrypted_string');
        $financial = Type::getType('encrypted_string.financial_data');
        self::assertInstanceOf(AbstractEncryptedType::class, $default);
        self::assertInstanceOf(AbstractEncryptedType::class, $financial);

        self::assertSame('default', $default->getProfile());
        self::assertSame('financial_data', $financial->getProfile());
        self::assertSame('encrypted_string.financial_data', $financial->getName());
        self::assertStringStartsWith('f:', (string) $financial->convertToDatabaseValue('iban', $this->platform));
    }

    public function testNullAndEmptyAreStoredAsIs(): void
    {
        $type = Type::getType('encrypted_string');

        self::assertNull($type->convertToDatabaseValue(null, $this->platform));
        self::assertSame('', $type->convertToDatabaseValue('', $this->platform));
        self::assertNull($type->convertToPHPValue(null, $this->platform));
        self::assertSame('', $type->convertToPHPValue('', $this->platform));
    }

    public function testValueWithoutMarkerIsReturnedAsPlaintext(): void
    {
        self::assertSame('legacy', Type::getType('encrypted_string')->convertToPHPValue('legacy', $this->platform));
    }

    public function testPlaintextEndingWithTheMarkerIsEncryptedAndRestored(): void
    {
        $type   = Type::getType('encrypted_string');
        $stored = $type->convertToDatabaseValue('looks<ENC>', $this->platform);

        self::assertSame('looks<ENC>', $type->convertToPHPValue($stored, $this->platform));
    }

    public function testStringableIsAcceptedAndOtherValuesAreRejected(): void
    {
        $type       = Type::getType('encrypted_string');
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'vo';
            }
        };
        self::assertSame('vo', $type->convertToPHPValue($type->convertToDatabaseValue($stringable, $this->platform), $this->platform));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"encrypted_string" expects a string, a Stringable or null, got stdClass');
        $type->convertToDatabaseValue(new stdClass(), $this->platform);
    }

    public function testResourceFromTheDriverIsRead(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, (string) Type::getType('encrypted_text')->convertToDatabaseValue('long text', $this->platform));
        rewind($stream);

        self::assertSame('long text', Type::getType('encrypted_text')->convertToPHPValue($stream, $this->platform));
    }

    public function testNonStringScalarFromTheDriverIsCast(): void
    {
        self::assertSame('42', Type::getType('encrypted_string')->convertToPHPValue(42, $this->platform));
    }

    public function testJsonRoundTrip(): void
    {
        $type   = Type::getType('encrypted_json.financial_data');
        $stored = $type->convertToDatabaseValue(['a' => 1, 'ratio' => 1.0], $this->platform);

        self::assertIsString($stored);
        self::assertStringEndsWith(EncryptUtil::ENCRYPTION_MARKER, $stored);
        self::assertSame(['a' => 1, 'ratio' => 1.0], $type->convertToPHPValue($stored, $this->platform));
        self::assertNull($type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($type->convertToPHPValue('', $this->platform));
        self::assertSame(['plain' => true], $type->convertToPHPValue('{"plain":true}', $this->platform));
    }

    public function testSqlDeclarations(): void
    {
        self::assertSame($this->platform->getStringTypeDeclarationSQL(['length' => 500]), Type::getType('encrypted_string')->getSQLDeclaration(['length' => 500], $this->platform));
        self::assertSame($this->platform->getClobTypeDeclarationSQL([]), Type::getType('encrypted_text')->getSQLDeclaration([], $this->platform));
        self::assertSame($this->platform->getClobTypeDeclarationSQL([]), Type::getType('encrypted_json')->getSQLDeclaration([], $this->platform));

        $type = Type::getType('encrypted_text');
        self::assertInstanceOf(EncryptedTextType::class, $type);
        self::assertTrue($type->requiresSQLCommentHint($this->platform));
        self::assertSame('encrypted_text', $type->getRegisteredName());
        self::assertInstanceOf(EncryptedStringType::class, Type::getType(EncryptedStringType::NAME));
        self::assertInstanceOf(EncryptedJsonType::class, Type::getType(EncryptedJsonType::NAME));
    }

    public function testDecryptFailureThrows(): void
    {
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('Could not decrypt a value of DBAL type "encrypted_string.financial_data" (config "financial_data")');

        Type::getType('encrypted_string.financial_data')->convertToPHPValue('p:' . base64_encode('x') . '<ENC>', $this->platform);
    }

    public function testDecryptFailureNullLogsAndReturnsNull(): void
    {
        $logger = $this->logger();
        EncryptedTypeRuntime::configure($this->registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL, $logger);

        self::assertNull(Type::getType('encrypted_string')->convertToPHPValue('garbage<ENC>', $this->platform));
        self::assertCount(1, $logger->records);
        self::assertStringContainsString('The value is null.', $logger->records[0]);
        self::assertStringNotContainsString('garbage', $logger->records[0]);
    }

    public function testDecryptFailureKeepLogsAndReturnsTheRawPayload(): void
    {
        $logger = $this->logger();
        EncryptedTypeRuntime::configure($this->registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP, $logger);

        self::assertSame('garbage', Type::getType('encrypted_string')->convertToPHPValue('garbage<ENC>', $this->platform));
        self::assertStringContainsString('The raw value is kept.', $logger->records[0]);
    }

    public function testDecryptFailureKeepFailsClosedWhenThePhpValueCannotHoldTheCiphertext(): void
    {
        EncryptedTypeRuntime::configure($this->registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP);

        $this->expectException(DecryptionFailedException::class);
        Type::getType('encrypted_json')->convertToPHPValue('garbage<ENC>', $this->platform);
    }

    public function testRuntimeNotConfiguredRefusesToConvert(): void
    {
        EncryptedTypeRuntime::clear();
        self::assertFalse(EncryptedTypeRuntime::isConfigured());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DBAL types are not configured');
        Type::getType('encrypted_string')->convertToDatabaseValue('secret', $this->platform);
    }

    public function testReadWithoutConfigurationThrowsWhateverThePolicy(): void
    {
        $stored = Type::getType('encrypted_string')->convertToDatabaseValue('secret', $this->platform);
        EncryptedTypeRuntime::clear();

        $this->expectException(LogicException::class);
        Type::getType('encrypted_string')->convertToPHPValue($stored, $this->platform);
    }

    public function testReadWithAnUnknownProfileThrowsWhateverThePolicy(): void
    {
        if (!Type::hasType('encrypted_string.not_configured')) {
            Type::addType('encrypted_string.not_configured', EncryptedStringType::class);
        }
        EncryptedTypeRuntime::configure($this->registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown encryptor config "not_configured"');
        Type::getType('encrypted_string.not_configured')->convertToPHPValue('p:eA==<ENC>', $this->platform);
    }

    public function testRuntimeRejectsAnUnknownFailureMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EncryptedTypeRuntime::configure($this->registry, 'ignore');
    }

    public function testClearOnlyIfKeepsAnotherKernelsConfiguration(): void
    {
        $other = new EncryptorRegistry(['default' => $this->encryptor('o')]);
        EncryptedTypeRuntime::clear($other);
        self::assertTrue(EncryptedTypeRuntime::isConfigured());
        self::assertSame(DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW, EncryptedTypeRuntime::onDecryptFailure());

        EncryptedTypeRuntime::clear($this->registry);
        self::assertFalse(EncryptedTypeRuntime::isConfigured());
        self::assertSame(DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP, EncryptedTypeRuntime::onDecryptFailure());
    }

    public function testConfiguratorConfiguresAndClears(): void
    {
        EncryptedTypeRuntime::clear();
        $registry     = new EncryptorRegistry(['default' => $this->encryptor('c')]);
        $configurator = new EncryptedTypeRuntimeConfigurator($registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL);

        $configurator->configure();
        self::assertTrue(EncryptedTypeRuntime::isConfigured());
        self::assertSame(DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL, EncryptedTypeRuntime::onDecryptFailure());
        self::assertStringStartsWith('c:', EncryptedTypeRuntime::encryptor('default')->encrypt('x'));

        $configurator->clear();
        self::assertFalse(EncryptedTypeRuntime::isConfigured());
    }

    private function encryptor(string $prefix): EncryptorInterface
    {
        return new class($prefix) implements EncryptorInterface {
            public function __construct(private readonly string $prefix)
            {
            }

            public function encrypt(string $data): string
            {
                return $this->prefix . ':' . base64_encode($data);
            }

            public function decrypt(string $data): string
            {
                if (!str_starts_with($data, $this->prefix . ':')) {
                    throw new RuntimeException('Wrong key.');
                }

                return (string) base64_decode(substr($data, strlen($this->prefix) + 1), true);
            }
        };
    }

    /**
     * @return AbstractLogger&object{records: list<string>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $replace = [];
                foreach ($context as $key => $value) {
                    if (is_scalar($value)) {
                        $replace['{' . $key . '}'] = (string) $value;
                    }
                }
                $this->records[] = strtr((string) $message, $replace);
            }
        };
    }
}
