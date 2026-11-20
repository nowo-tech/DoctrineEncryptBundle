<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Account;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Address;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Animal;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Dog;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Geo;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Note;
use Nowo\DoctrineEncryptBundle\Tests\Functional\SqlQueryCollector;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_slice;
use function count;
use function extension_loaded;
use function is_string;
use function stripos;
use function strlen;
use function substr;

use const PHP_VERSION_ID;

/**
 * Decrypted entities must not look changed to the unit of work.
 *
 * The listener decrypts in postLoad and re-encrypts in preFlush. Before the fix the unit of work's original data kept
 * the ciphertext while the property held the plaintext, so UnitOfWork::computeChangeSets() outside a flush reported
 * every decrypted field as changed, and host-pending guards (DoctrineTransactionRunner::forHostEntityManager()) refused
 * to run. The original data now holds the plaintext after every decryption.
 *
 * The same scenarios run on SQLite (functional suite) and on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16 (database suite).
 */
abstract class AbstractCleanChangeSetTestCase extends TestCase
{
    private const MARKER = DoctrineEncryptSubscriber::ENCRYPTION_MARKER;

    protected EntityManager $em;

    protected DoctrineEncryptSubscriber $subscriber;

    protected EncryptorInterface $encryptor;

    protected SqlQueryCollector $queries;

    /**
     * Connection parameters of the database the scenarios run on (the test is skipped when it returns null).
     *
     * @return array<string, mixed>|null
     */
    abstract protected function connectionParams(): ?array;

    protected function setUp(): void
    {
        if (!extension_loaded('sodium')) {
            self::markTestSkipped('This test only runs when the sodium extension is enabled.');
        }

        $params = $this->connectionParams();
        if ($params === null) {
            self::markTestSkipped(static::class . ': database URL not set.');
        }

        $this->queries = new SqlQueryCollector();
        $dbalConfig    = new DbalConfiguration();
        $dbalConfig->setMiddlewares([new Middleware($this->queries)]);
        $connection = DriverManager::getConnection($params, $dbalConfig);

        $config = ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/fixtures'], true);
        if (PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $classes    = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($classes);
        $schemaTool->createSchema($classes);

        $this->encryptor  = new HaliteEncryptor(__DIR__ . '/../fixtures/halite.key');
        $this->subscriber = new DoctrineEncryptSubscriber($this->encryptor);
        $this->em->getEventManager()->addEventListener(
            [Events::postUpdate, Events::preUpdate, Events::postLoad, Events::onFlush, Events::preFlush, Events::postFlush],
            $this->subscriber,
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $connection = $this->em->getConnection();
            (new SchemaTool($this->em))->dropSchema($this->em->getMetadataFactory()->getAllMetadata());
            $connection->close();
        }
    }

    public function testUntouchedLoadedEntityHasAnEmptyChangeSetAndIssuesNoUpdate(): void
    {
        $id = $this->seedAccount();

        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        self::assertSame('s3cr3t', $account->getSecret());
        self::assertSame('Main St 1', $account->getAddress()->getStreet());
        self::assertSame('40.4,-3.7', $account->getAddress()->getGeo()->getPoint());

        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        self::assertSame([], $uow->getScheduledEntityUpdates());

        $original = $uow->getOriginalEntityData($account);
        self::assertSame('s3cr3t', $original['secret']);
        self::assertSame('Main St 1', $original['address.street']);
        self::assertSame('40.4,-3.7', $original['address.geo.point']);

        $before = $this->rawAccount($id);
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
        self::assertSame($before, $this->rawAccount($id));
        self::assertSame('s3cr3t', $account->getSecret());
    }

    public function testChangingOneEncryptedFieldChangesExactlyThatField(): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setSecret('new secret');
        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame(['secret' => ['s3cr3t', 'new secret']], $uow->getEntityChangeSet($account));

        self::assertSame(1, $this->updatesDuring(fn () => $this->em->flush()));

        $after = $this->rawAccount($id);
        self::assertNotSame($before['secret'], $after['secret']);
        self::assertSame('new secret', $this->decryptRaw($after['secret']));
        self::assertSame($before['address_street'], $after['address_street'], 'Untouched encrypted columns keep their ciphertext');
        self::assertSame($before['address_geo_point'], $after['address_geo_point']);

        // After the flush the entity holds the plaintext again, with the plaintext as original data.
        self::assertSame('new secret', $account->getSecret());
        self::assertSame('new secret', $uow->getOriginalEntityData($account)['secret']);
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));

        $this->em->clear();
        $reloaded = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $reloaded);
        self::assertSame('new secret', $reloaded->getSecret());
    }

    public function testChangingANestedEmbeddedEncryptedFieldChangesExactlyThatField(): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->getAddress()->getGeo()->setPoint('41.3,2.1');
        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame(['address.geo.point' => ['40.4,-3.7', '41.3,2.1']], $uow->getEntityChangeSet($account));

        self::assertSame(1, $this->updatesDuring(fn () => $this->em->flush()));
        $after = $this->rawAccount($id);
        self::assertSame('41.3,2.1', $this->decryptRaw($after['address_geo_point']));
        self::assertSame($before['secret'], $after['secret']);
        self::assertSame($before['address_street'], $after['address_street']);

        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        $this->em->clear();
        $reloaded = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $reloaded);
        self::assertSame('41.3,2.1', $reloaded->getAddress()->getGeo()->getPoint());
        self::assertSame('Main St 1', $reloaded->getAddress()->getStreet());
    }

    public function testValueChangedBackToItsOriginalPlaintextIssuesNoUpdate(): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setSecret('temporary');
        $account->setSecret('s3cr3t');
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));

        self::assertSame($before, $this->rawAccount($id));
        self::assertSame('s3cr3t', $account->getSecret());
    }

    /**
     * UnitOfWork::computeChangeSets() outside the flush while the value differed leaves a change set holding
     * plaintext, which the unit of work keeps when the flush finds no difference. The flush must never write that
     * plaintext: at worst it writes the same ciphertext again.
     *
     * @return iterable<string, array{list<string>}>
     */
    public static function valuesComputedBeforeTheFlush(): iterable
    {
        yield 'changed, computed, changed back' => [['temporary']];
        yield 'changed, computed, changed back, computed' => [['temporary', 's3cr3t']];
        yield 'changed twice, computed each time, changed back' => [['one', 'two']];
    }

    /**
     * @param list<string> $computedValues
     */
    #[DataProvider('valuesComputedBeforeTheFlush')]
    public function testChangeSetComputedOutsideTheFlushNeverWritesPlaintext(array $computedValues): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        foreach ($computedValues as $value) {
            $account->setSecret($value);
            $this->em->getUnitOfWork()->computeChangeSets();
        }
        $account->setSecret('s3cr3t');
        $this->em->flush();

        self::assertSame($before, $this->rawAccount($id), 'The stored ciphertext is unchanged');
        self::assertSame('s3cr3t', $account->getSecret());
        $this->em->getUnitOfWork()->computeChangeSets();
        self::assertSame([], $this->em->getUnitOfWork()->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
    }

    public function testRefreshAfterAChangeSetComputedOutsideTheFlushNeverWritesPlaintext(): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setSecret('temporary');
        $this->em->getUnitOfWork()->computeChangeSets();
        $this->em->refresh($account);
        self::assertSame('s3cr3t', $account->getSecret());
        $this->em->flush();

        self::assertSame($before['secret'], $this->rawAccount($id)['secret']);
        self::assertSame('s3cr3t', $account->getSecret());
    }

    public function testRandomisedCiphertextNeverHidesAChange(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        foreach (['one', 'two', 's3cr3t', 'three'] as $value) {
            $before = $this->rawAccount($id)['secret'];
            $account->setSecret($value);
            self::assertSame(1, $this->updatesDuring(fn () => $this->em->flush()), $value);
            $after = $this->rawAccount($id)['secret'];
            self::assertNotSame($before, $after);
            self::assertSame($value, $this->decryptRaw($after));
            self::assertSame($value, $account->getSecret());
        }
    }

    public function testInsertedEntityIsCleanAfterTheFlush(): void
    {
        $account = new Account('fresh', 'plain', new Address('Side St 2', 'Bilbao', new Geo('43.2,-2.9')));
        $this->em->persist($account);
        $this->em->flush();

        self::assertSame('fresh', $account->getSecret());
        self::assertSame('Side St 2', $account->getAddress()->getStreet());
        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));

        $raw = $this->rawAccount((int) $account->getId());
        self::assertSame('fresh', $this->decryptRaw($raw['secret']));
        self::assertSame('43.2,-2.9', $this->decryptRaw($raw['address_geo_point']));
    }

    public function testEncryptedFieldsAcrossInheritance(): void
    {
        $dog = new Dog('chip-123', 'Ana');
        $this->em->persist($dog);
        $this->em->flush();
        $id = (int) $dog->getId();
        $this->em->clear();

        $loaded = $this->em->find(Animal::class, $id);
        self::assertInstanceOf(Dog::class, $loaded);
        self::assertSame('chip-123', $loaded->getChip());
        self::assertSame('Ana', $loaded->getKeeper());

        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($loaded));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));

        $loaded->setChip('chip-456');
        $uow->computeChangeSets();
        self::assertSame(['chip' => ['chip-123', 'chip-456']], $uow->getEntityChangeSet($loaded));
        self::assertSame(1, $this->updatesDuring(fn () => $this->em->flush()));

        $this->em->clear();
        $reloaded = $this->em->find(Dog::class, $id);
        self::assertInstanceOf(Dog::class, $reloaded);
        self::assertSame('chip-456', $reloaded->getChip());
        self::assertSame('Ana', $reloaded->getKeeper());
    }

    public function testProxyInitialisedLazilyIsClean(): void
    {
        $id        = $this->seedAccount();
        $reference = $this->em->getReference(Account::class, $id);
        self::assertInstanceOf(Account::class, $reference);
        $note = new Note($reference);
        $this->em->persist($note);
        $this->em->flush();
        $noteId = (int) $note->getId();
        $this->em->clear();

        $note = $this->em->find(Note::class, $noteId);
        self::assertInstanceOf(Note::class, $note);
        $uow     = $this->em->getUnitOfWork();
        $account = $note->getAccount();
        self::assertTrue($uow->isUninitializedObject($account));

        // A flush must not initialize the proxy just to look at it.
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
        self::assertTrue($uow->isUninitializedObject($account));

        self::assertSame('s3cr3t', $account->getSecret());
        self::assertFalse($uow->isUninitializedObject($account));
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
    }

    public function testRefreshLeavesTheEntityClean(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setSecret('not flushed');
        $this->em->refresh($account);

        self::assertSame('s3cr3t', $account->getSecret());
        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
    }

    public function testUndecryptableValueKeptAsIsStaysClean(): void
    {
        $id      = $this->seedAccount();
        $foreign = new HaliteEncryptor(__DIR__ . '/../fixtures/halite-foreign.key');
        $raw     = $foreign->encrypt('other key') . self::MARKER;
        $this->em->getConnection()->update('ccs_account', ['secret' => $raw], ['id' => $id]);

        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        $uow = $this->em->getUnitOfWork();
        $uow->computeChangeSets();
        self::assertSame([], $uow->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
        self::assertSame($raw, $this->rawAccount($id)['secret']);
    }

    /**
     * transaction-doctrine's host guard (UnitOfWork::computeChangeSets() before the run) must accept a manager that
     * only loaded encrypted entities, and still refuse one with a real pending change.
     */
    public function testHostPendingGuardIgnoresDecryptedEntities(): void
    {
        $id  = $this->seedAccount();
        $dog = new Dog('chip-1', 'Leo');
        $this->em->persist($dog);
        $this->em->flush();
        $this->em->clear();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        self::assertInstanceOf(Dog::class, $this->em->find(Animal::class, $dog->getId()));

        $runner = DoctrineTransactionRunner::forHostEntityManager($this->em);
        self::assertSame('ran', $runner->run(static fn (): string => 'ran'));

        $account->setSecret('pending host change');
        $this->expectException(LogicException::class);
        $runner->run(static fn (): string => 'must not run');
    }

    private function seedAccount(): int
    {
        $account = new Account('s3cr3t', 'visible', new Address('Main St 1', 'Madrid', new Geo('40.4,-3.7')));
        $this->em->persist($account);
        $this->em->flush();
        $id = (int) $account->getId();
        $this->em->clear();

        return $id;
    }

    /**
     * @return array{secret: string, address_street: string, address_geo_point: string}
     */
    private function rawAccount(int $id): array
    {
        $row = $this->em->getConnection()->fetchAssociative('SELECT secret, address_street, address_geo_point FROM ccs_account WHERE id = ?', [$id]);
        self::assertIsArray($row);
        $result = [];
        foreach (['secret', 'address_street', 'address_geo_point'] as $column) {
            self::assertTrue(is_string($row[$column]), $column);
            self::assertStringEndsWith(self::MARKER, $row[$column], $column . ' is stored encrypted');
            $result[$column] = $row[$column];
        }

        return $result;
    }

    private function decryptRaw(string $raw): string
    {
        return $this->encryptor->decrypt(substr($raw, 0, -strlen(self::MARKER)));
    }

    /**
     * Number of UPDATE statements sent while $action runs.
     */
    private function updatesDuring(callable $action): int
    {
        $start = count($this->queries->queries);
        $action();
        $sent = array_slice($this->queries->queries, $start);

        return count(array_filter($sent, static fn (array $query): bool => stripos($query['sql'], 'UPDATE ') === 0));
    }
}
