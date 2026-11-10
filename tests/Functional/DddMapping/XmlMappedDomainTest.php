<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptStatusCommand;
use Nowo\DoctrineEncryptBundle\DependencyInjection\DoctrineEncryptExtension;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntime;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\EventListener\EncryptedMappingValidator;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain\Address;
use Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain\Customer;
use Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain\Iban;
use Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Infrastructure\EncryptedIbanType;
use Nowo\DoctrineEncryptBundle\Tests\Functional\SqlQueryCollector;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function extension_loaded;

use const PHP_VERSION_ID;

/**
 * A domain mapped in XML (no bundle or Doctrine import in the classes): encrypted DBAL types for entity fields,
 * an embeddable and a value object, plus encrypted_fields for the listener.
 */
final class XmlMappedDomainTest extends TestCase
{
    private const DOMAIN_NAMESPACE = 'Nowo\DoctrineEncryptBundle\Tests\Functional\DddMapping\fixtures\Domain';

    private EntityManager $entityManager;
    private EncryptorRegistry $registry;
    private DoctrineEncryptSubscriber $subscriber;
    private SqlQueryCollector $queries;
    private string $dbFile;
    private EncryptedFieldMap $fieldMap;

    public static function setUpBeforeClass(): void
    {
        foreach (DoctrineEncryptExtension::dbalTypeMap(['financial_data']) as $name => $class) {
            if (!Type::hasType($name)) {
                Type::addType($name, $class);
            }
        }
        if (!Type::hasType('encrypted_iban.financial_data')) {
            Type::addType('encrypted_iban.financial_data', EncryptedIbanType::class);
        }
    }

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('This test only runs when the sodium extension is enabled.');
        }

        $default   = new HaliteEncryptor(__DIR__ . '/../fixtures/halite.key');
        $financial = new HaliteEncryptor(__DIR__ . '/../fixtures/halite-financial.key');
        // Wired like the bundle does: one entry per profile plus the "default" alias of default_profile.
        $this->registry = new EncryptorRegistry(['personal_data' => $default, 'financial_data' => $financial, 'default' => $default], 'personal_data');
        EncryptedTypeRuntime::configure($this->registry, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW);

        $config = ORMSetup::createConfiguration(true);
        $config->setMetadataDriverImpl(new SimplifiedXmlDriver([__DIR__ . '/fixtures/config' => self::DOMAIN_NAMESPACE]));
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }

        $dbFile       = tempnam(sys_get_temp_dir(), 'nowo_encrypt_ddd');
        $this->dbFile = $dbFile !== false ? $dbFile : sys_get_temp_dir() . '/nowo_encrypt_ddd_' . uniqid('', true);

        $this->queries = new SqlQueryCollector();
        $dbalConfig    = new DbalConfiguration();
        $dbalConfig->setMiddlewares([new Middleware($this->queries)]);
        $connection          = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->dbFile], $dbalConfig);
        $this->entityManager = new EntityManager($connection, $config);
        $this->fieldMap      = new EncryptedFieldMap([
            Customer::class => ['properties' => ['email' => null], 'embedded' => ['address']],
            Address::class  => ['properties' => ['city' => 'financial_data']],
        ]);
        // The selection is checked against the XML metadata when it loads (schema creation below).
        $this->entityManager->getEventManager()->addEventListener([Events::loadClassMetadata], new EncryptedMappingValidator($this->fieldMap));

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->subscriber = new DoctrineEncryptSubscriber(
            $this->registry,
            null,
            DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW,
            $this->fieldMap,
            DoctrineEncryptSubscriber::PROPERTY_ACCESS_REFLECTION,
        );
        $this->entityManager->getEventManager()->addEventListener(
            [Events::postUpdate, Events::preUpdate, Events::postLoad, Events::onFlush, Events::preFlush, Events::postFlush],
            $this->subscriber,
        );
    }

    protected function tearDown(): void
    {
        EncryptedTypeRuntime::clear();
        if (isset($this->entityManager)) {
            $this->entityManager->getConnection()->close();
            @unlink($this->dbFile);
        }
    }

    public function testTypesAndConfiguredFieldsAreEncryptedAtRestAndPlainInTheDomain(): void
    {
        $customer = $this->newCustomer();
        $iban     = $customer->getIban();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        // The entity keeps its plaintext and the same value object instance after the flush.
        self::assertSame('600111222', $customer->getPhone());
        self::assertSame($iban, $customer->getIban());
        self::assertSame('ana@example.com', $customer->getEmail());
        self::assertSame('Gran Via 1', $customer->getAddress()->street());
        self::assertSame('Madrid', $customer->getAddress()->city());

        $row = $this->rawRow((int) $customer->getId());
        foreach (['email', 'phone', 'iban', 'notes', 'preferences', 'address_street', 'address_city'] as $column) {
            self::assertIsString($row[$column]);
            self::assertStringEndsWith(EncryptUtil::ENCRYPTION_MARKER, $row[$column], $column);
        }
        self::assertStringNotContainsString('600111222', (string) $row['phone']);
        self::assertStringNotContainsString('ES91', (string) $row['iban']);
        self::assertStringNotContainsString('dark', (string) $row['preferences']);

        $this->entityManager->clear();
        $loaded = $this->entityManager->find(Customer::class, $customer->getId());
        self::assertInstanceOf(Customer::class, $loaded);
        self::assertSame('600111222', $loaded->getPhone());
        self::assertEquals(new Iban('ES9121000418450200051332'), $loaded->getIban());
        self::assertSame('Long notes', $loaded->getNotes());
        self::assertSame(['theme' => 'dark', 'ratio' => 1.0], $loaded->getPreferences());
        self::assertSame('ana@example.com', $loaded->getEmail());
        self::assertSame('Gran Via 1', $loaded->getAddress()->street());
        self::assertSame('Madrid', $loaded->getAddress()->city());

        // Neither the types nor the listener (property_access: reflection) went through the domain setters.
        self::assertSame([], $loaded->setterCalls);
        self::assertSame(['ana@example.com', '600111222'], $customer->setterCalls);
    }

    public function testProfilesFollowTheTypeNameAndTheConfiguration(): void
    {
        $customer = $this->newCustomer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $row  = $this->rawRow((int) $customer->getId());
        $util = new EncryptUtil($this->registry);

        // Same "<ciphertext><ENC>" format and keys as #[Encrypted]: EncryptUtil (and the listener) read it.
        self::assertSame('ES9121000418450200051332', $util->decryptStrict((string) $row['iban'], 'financial_data'));
        self::assertSame('Madrid', $util->decryptStrict((string) $row['address_city'], 'financial_data'));
        self::assertSame('600111222', $util->decryptStrict((string) $row['phone']));
        self::assertSame('ana@example.com', $util->decryptStrict((string) $row['email']));
    }

    public function testListenerCiphertextLoadsThroughTheTypeAndLegacyPlaintextPassesThrough(): void
    {
        $customer = $this->newCustomer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        $util = new EncryptUtil($this->registry);
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE ddd_customer SET phone = ?, notes = ? WHERE id = ?',
            [$util->encrypt('699000000'), 'written before encryption', $customer->getId()],
        );

        $this->entityManager->clear();
        $loaded = $this->entityManager->find(Customer::class, $customer->getId());
        self::assertInstanceOf(Customer::class, $loaded);
        self::assertSame('699000000', $loaded->getPhone());
        self::assertSame('written before encryption', $loaded->getNotes());
    }

    public function testUnchangedEncryptedTypeFieldsAreNotRewritten(): void
    {
        $customer = $this->newCustomer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->entityManager->find(Customer::class, $customer->getId());
        self::assertInstanceOf(Customer::class, $loaded);
        $before = $this->rawRow((int) $customer->getId());

        $this->queries->queries = [];
        $this->entityManager->flush();
        self::assertSame([], array_filter($this->queries->queries, static fn (array $query): bool => stripos($query['sql'], 'UPDATE') === 0));
        self::assertSame($before, $this->rawRow((int) $customer->getId()));

        $loaded->setNotes('Changed');
        $this->entityManager->flush();
        $after = $this->rawRow((int) $customer->getId());
        self::assertNotSame($before['notes'], $after['notes']);
        self::assertSame($before['phone'], $after['phone']);
    }

    public function testDecryptFailureFailsClosedLikeTheListener(): void
    {
        $customer = $this->newCustomer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement('UPDATE ddd_customer SET phone = ? WHERE id = ?', ['tampered' . EncryptUtil::ENCRYPTION_MARKER, $customer->getId()]);
        $this->entityManager->clear();

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('DBAL type "encrypted_string"');
        $this->entityManager->find(Customer::class, $customer->getId());
    }

    public function testQueryingByAnEncryptedValueFindsNothing(): void
    {
        $customer = $this->newCustomer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Halite ciphertext is randomized: the parameter is encrypted again and never equals the stored value.
        self::assertSame([], $this->entityManager->getRepository(Customer::class)->findBy(['phone' => '600111222']));
    }

    public function testRawSqlCommandsHandleTypeAndConfiguredColumns(): void
    {
        $customer = $this->newCustomer();
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        $id = (int) $customer->getId();

        // An entry for a class Doctrine does not map (here the value object) is reported.
        $withUnmapped = new EncryptedFieldMap([...$this->fieldMap->all(), Iban::class => ['properties' => ['value' => null]]]);
        $status       = $this->runCommand(new DoctrineEncryptStatusCommand($this->entityManager, new AttributeReader(), $this->subscriber, null, $this->registry), [], $withUnmapped);
        self::assertStringContainsString('encrypted_fields: ' . Iban::class . ' is not mapped by this entity manager', $status);
        self::assertStringContainsString('phone (config: personal_data, DBAL type encrypted_string)', $status);
        self::assertStringContainsString('iban (config: financial_data, DBAL type encrypted_iban.financial_data)', $status);
        self::assertStringContainsString('address.street (config: personal_data, DBAL type encrypted_string)', $status);
        self::assertStringContainsString('email (config: personal_data)', $status);
        self::assertStringContainsString('address.city (config: financial_data, embedded)', $status);

        $this->runCommand(new DoctrineDecryptDatabaseCommand($this->entityManager, new AttributeReader(), $this->subscriber, null, $this->registry), ['--force' => true]);
        $plain = $this->rawRow($id);
        self::assertSame('600111222', $plain['phone']);
        self::assertSame('ES9121000418450200051332', $plain['iban']);
        self::assertSame('Gran Via 1', $plain['address_street']);
        self::assertSame('ana@example.com', $plain['email']);
        self::assertSame('Madrid', $plain['address_city']);

        $this->runCommand(new DoctrineEncryptDatabaseCommand($this->entityManager, new AttributeReader(), $this->subscriber, null, $this->registry), ['--force' => true]);
        $encrypted = $this->rawRow($id);
        foreach (['email', 'phone', 'iban', 'notes', 'preferences', 'address_street', 'address_city'] as $column) {
            self::assertStringEndsWith(EncryptUtil::ENCRYPTION_MARKER, (string) $encrypted[$column], $column);
        }

        $this->entityManager->clear();
        $loaded = $this->entityManager->find(Customer::class, $id);
        self::assertInstanceOf(Customer::class, $loaded);
        self::assertSame('600111222', $loaded->getPhone());
        self::assertEquals(new Iban('ES9121000418450200051332'), $loaded->getIban());
        self::assertSame('Gran Via 1', $loaded->getAddress()->street());
        self::assertSame('ana@example.com', $loaded->getEmail());
        self::assertSame('Madrid', $loaded->getAddress()->city());
    }

    private function newCustomer(): Customer
    {
        $customer = new Customer();
        $customer->setEmail('ana@example.com');
        $customer->setPhone('600111222');
        $customer->setIban(new Iban('ES9121000418450200051332'));
        $customer->setNotes('Long notes');
        $customer->setPreferences(['theme' => 'dark', 'ratio' => 1.0]);
        $customer->moveTo(new Address('Gran Via 1', 'Madrid'));

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    private function rawRow(int $id): array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM ddd_customer WHERE id = ?', [$id]);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(AbstractCommand $command, array $input, ?EncryptedFieldMap $fieldMap = null): string
    {
        $command->setApplication(new Application());
        $command->setEncryptedFieldMap($fieldMap ?? $this->fieldMap);
        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());

        return $tester->getDisplay();
    }
}
