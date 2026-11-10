<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DoctrineEncryptBundle\Command\AbstractCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineDecryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptDatabaseCommand;
use Nowo\DoctrineEncryptBundle\Command\DoctrineEncryptStatusCommand;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation\fixtures\Address;
use Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation\fixtures\Geo;
use Nowo\DoctrineEncryptBundle\Tests\Functional\EmbeddedRotation\fixtures\Person;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function extension_loaded;

use const PHP_VERSION_ID;

/**
 * KA-H1: a key rotation (decrypt:database with the old key, key change, encrypt:database with the new key) must cover
 * #[Encrypted] fields inside embeddables, nested ones included. Before 2.11 those columns were skipped and became
 * unreadable once the old key was gone.
 */
final class EmbeddedRotationTest extends TestCase
{
    private string $dir;
    private string $dbFile;

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('This test only runs when the sodium extension is enabled.');
        }

        $this->dir = sys_get_temp_dir() . '/nowo_encrypt_rotation_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0o700, true);
        $this->dbFile = $this->dir . '/db.sqlite';
    }

    protected function tearDown(): void
    {
        if (!isset($this->dir) || !is_dir($this->dir)) {
            return;
        }
        foreach (scandir($this->dir) ?: [] as $file) {
            if (is_file($this->dir . '/' . $file)) {
                unlink($this->dir . '/' . $file);
            }
        }
        rmdir($this->dir);
    }

    public function testRotationCoversEmbeddedAndNestedEmbeddedFields(): void
    {
        $old = $this->registry('old');
        $new = $this->registry('new');

        [$em]   = $this->entityManager($old, true);
        $person = new Person('ana@example.com', new Address('Madrid', 'ES', new Geo('40.41,-3.70')));
        $em->persist($person);
        $em->flush();
        $id = (int) $person->getId();
        $em->getConnection()->close();

        $before = $this->rawRow($id);
        foreach (['email', 'address_city', 'address_geo_coordinates'] as $column) {
            self::assertStringEndsWith(EncryptUtil::ENCRYPTION_MARKER, (string) $before[$column], $column);
        }
        self::assertSame('ES', $before['address_country']);

        // Status lists the embedded fields.
        [$em, $subscriber] = $this->entityManager($old);
        $status            = $this->runCommand(new DoctrineEncryptStatusCommand($em, new AttributeReader(), $subscriber, null, $old), []);
        self::assertStringContainsString('address.city (config: personal_data, embedded)', $status);
        self::assertStringContainsString('address.geo.coordinates (config: personal_data, embedded)', $status);

        // Step 1: decrypt with the old key.
        $this->runCommand(new DoctrineDecryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $old), ['--force' => true]);
        $plain = $this->rawRow($id);
        self::assertSame('ana@example.com', $plain['email']);
        self::assertSame('Madrid', $plain['address_city']);
        self::assertSame('40.41,-3.70', $plain['address_geo_coordinates']);
        $em->getConnection()->close();

        // Step 2: keys changed. Step 3: encrypt with the new key.
        [$em, $subscriber] = $this->entityManager($new);
        $this->runCommand(new DoctrineEncryptDatabaseCommand($em, new AttributeReader(), $subscriber, null, $new), ['--force' => true]);
        $em->getConnection()->close();

        $after   = $this->rawRow($id);
        $newUtil = new EncryptUtil($new);
        self::assertSame('Madrid', $newUtil->decryptStrict((string) $after['address_city']));
        self::assertSame('40.41,-3.70', $newUtil->decryptStrict((string) $after['address_geo_coordinates']));
        self::assertSame('ana@example.com', $newUtil->decryptStrict((string) $after['email']));

        // Read back through Doctrine with only the new key.
        [$em]   = $this->entityManager($new, false, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW);
        $loaded = $em->find(Person::class, $id);
        self::assertInstanceOf(Person::class, $loaded);
        self::assertSame('ana@example.com', $loaded->getEmail());
        self::assertSame('Madrid', $loaded->getAddress()->city());
        self::assertSame('40.41,-3.70', $loaded->getAddress()->geo()->coordinates());
        $em->getConnection()->close();
    }

    private function registry(string $name): EncryptorRegistry
    {
        $encryptor = new HaliteEncryptor($this->dir . '/.' . $name . '.key');

        return new EncryptorRegistry(['personal_data' => $encryptor, 'default' => $encryptor], 'personal_data');
    }

    /**
     * @return array{EntityManager, DoctrineEncryptSubscriber}
     */
    private function entityManager(EncryptorRegistry $registry, bool $createSchema = false, string $onDecryptFailure = DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP): array
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/fixtures'], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->dbFile]), $config);
        if ($createSchema) {
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        }
        $subscriber = new DoctrineEncryptSubscriber($registry, null, $onDecryptFailure);
        $em->getEventManager()->addEventListener(
            [Events::postUpdate, Events::preUpdate, Events::postLoad, Events::onFlush, Events::preFlush, Events::postFlush],
            $subscriber,
        );

        return [$em, $subscriber];
    }

    /**
     * @return array<string, mixed>
     */
    private function rawRow(int $id): array
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->dbFile]);
        $row        = $connection->fetchAssociative('SELECT * FROM rotation_person WHERE id = ?', [$id]);
        $connection->close();
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(AbstractCommand $command, array $input): string
    {
        $command->setApplication(new Application());
        $command->setEncryptedFieldMap(new EncryptedFieldMap());
        $tester = new CommandTester($command);
        $tester->execute($input, ['interactive' => false]);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());

        return $tester->getDisplay();
    }
}
