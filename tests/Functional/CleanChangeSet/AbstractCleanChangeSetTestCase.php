<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
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
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Badge;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Dog;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Geo;
use Nowo\DoctrineEncryptBundle\Tests\Functional\CleanChangeSet\fixtures\Note;
use Nowo\DoctrineEncryptBundle\Tests\Functional\SqlQueryCollector;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnit;
use Nowo\TransactionDoctrine\WriteUnit\WriteUnitPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_filter;
use function array_keys;
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
            [Events::postUpdate, Events::preUpdate, Events::postLoad, Events::onFlush, Events::preFlush, Events::postFlush, Events::prePersist],
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

        // The pending change set held a plaintext for secret: it is replaced by a fresh ciphertext of the same value.
        $after = $this->rawAccount($id);
        self::assertSame('s3cr3t', $this->decryptRaw($after['secret']));
        self::assertSame($before['address_street'], $after['address_street']);
        self::assertSame($before['address_geo_point'], $after['address_geo_point']);
        self::assertSame('s3cr3t', $account->getSecret());
        $this->em->getUnitOfWork()->computeChangeSets();
        self::assertSame([], $this->em->getUnitOfWork()->getEntityChangeSet($account));
        self::assertSame(0, $this->updatesDuring(fn () => $this->em->flush()));
    }

    public function testRefreshAfterAChangeSetComputedOutsideTheFlushNeverWritesPlaintext(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setSecret('temporary');
        $this->em->getUnitOfWork()->computeChangeSets();
        $this->em->refresh($account);
        self::assertSame('s3cr3t', $account->getSecret());
        $this->em->flush();

        self::assertSame('s3cr3t', $this->decryptRaw($this->rawAccount($id)['secret']));
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
        $chip   = $this->em->getConnection()->fetchOne('SELECT chip FROM ccs_animal WHERE id = ?', [$id]);
        $keeper = $this->em->getConnection()->fetchOne('SELECT keeper FROM ccs_animal WHERE id = ?', [$id]);
        self::assertTrue(is_string($chip) && is_string($keeper));
        self::assertSame('chip-456', $this->decryptRaw($chip), 'The parent class column is stored encrypted');
        self::assertSame('Ana', $this->decryptRaw($keeper), 'The child class column is stored encrypted');

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

    // Audit WE regressions: a change set computed outside the flush must keep every change, and the flush listeners
    // never see an old plaintext.

    /**
     * WE-1 (P28): a non-encrypted change computed outside the flush is written, with the encrypted values unchanged.
     */
    public function testNonEncryptedChangeComputedOutsideTheFlushIsWritten(): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setNotSecret('changed');
        $this->em->getUnitOfWork()->computeChangeSets();
        self::assertSame(1, $this->updatesDuring(fn () => $this->em->flush()));

        self::assertSame('changed', $this->em->getConnection()->fetchOne('SELECT not_secret FROM ccs_account WHERE id = ?', [$id]));
        self::assertSame($before, $this->rawAccount($id), 'Untouched encrypted columns keep their ciphertext');
        self::assertSame('s3cr3t', $account->getSecret());
        $this->em->getUnitOfWork()->computeChangeSets();
        self::assertSame([], $this->em->getUnitOfWork()->getEntityChangeSet($account));
    }

    /**
     * WE-1 (P25): an encrypted value cleared, computed outside the flush, is stored as null; set again, it is encrypted.
     */
    public function testEncryptedValueClearedAndComputedOutsideTheFlushIsStoredAsNull(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $account->setSecret(null);
        $this->em->getUnitOfWork()->computeChangeSets();
        $this->em->flush();
        self::assertNull($this->em->getConnection()->fetchOne('SELECT secret FROM ccs_account WHERE id = ?', [$id]));

        $account->setSecret('back');
        $this->em->flush();
        self::assertSame('back', $this->decryptRaw($this->rawAccount($id)['secret']));
    }

    /**
     * WE-1 (P29): a to-one association change computed outside the flush is written.
     */
    public function testToOneChangeComputedOutsideTheFlushIsWritten(): void
    {
        $first  = $this->seedAccount();
        $second = $this->seedAccount();
        $note   = new Note($this->accountReference($first));
        $this->em->persist($note);
        $this->em->flush();
        $noteId = (int) $note->getId();
        $this->em->clear();

        $note = $this->em->find(Note::class, $noteId);
        self::assertInstanceOf(Note::class, $note);
        $note->getAccount()->getSecret(); // the encrypted account is loaded and decrypted
        $other = $this->em->find(Account::class, $second);
        self::assertInstanceOf(Account::class, $other);
        $note->setAccount($other);
        $this->em->getUnitOfWork()->computeChangeSets();
        $this->em->flush();

        $row = $this->em->getConnection()->fetchAssociative('SELECT account_id, body FROM ccs_note WHERE id = ?', [$noteId]);
        self::assertIsArray($row);
        self::assertSame($second, (int) $row['account_id']);
        self::assertTrue(is_string($row['body']));
        self::assertSame('note body', $this->decryptRaw($row['body']));
        self::assertSame('note body', $note->getBody());
    }

    /**
     * WE-1 (P34): the host flushes its own change after transaction-doctrine's host guard refused to run.
     */
    public function testHostFlushAfterAHostGuardRefusalKeepsTheChange(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        $runner = DoctrineTransactionRunner::forHostEntityManager($this->em);

        $account->setNotSecret('pending');
        $refusal = null;
        try {
            $runner->run(static fn (): string => 'must not run');
        } catch (Throwable $throwable) {
            $refusal = $throwable;
        }
        self::assertInstanceOf(LogicException::class, $refusal, 'The guard refuses a pending change');
        $this->em->flush();

        self::assertSame('pending', $this->em->getConnection()->fetchOne('SELECT not_secret FROM ccs_account WHERE id = ?', [$id]));
        self::assertSame('s3cr3t', $this->decryptRaw($this->rawAccount($id)['secret']));
        self::assertSame('ran', $runner->run(static fn (): string => 'ran'));
    }

    /**
     * WE-1 (P35): transaction-doctrine's WriteUnit runs once with a host pending change; the work's flush keeps it.
     */
    public function testWriteUnitWithAHostPendingChangeKeepsTheChange(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        $account->setNotSecret('host pending');

        $unit = new WriteUnit(new SingleManagerRegistry($this->em), new WriteUnitPolicy(3, 0, 0, 0));
        $unit->run(function (): void {
            $this->em->flush();
        });

        self::assertSame('host pending', $this->em->getConnection()->fetchOne('SELECT not_secret FROM ccs_account WHERE id = ?', [$id]));
        self::assertSame('s3cr3t', $this->decryptRaw($this->rawAccount($id)['secret']));
    }

    /**
     * WE-1 (P37): an insertion computed before the flush (assigned identifier, like SEQUENCE) of a class with a
     * decrypted instance in memory keeps every column.
     */
    public function testInsertionComputedBeforeTheFlushKeepsEveryColumn(): void
    {
        $this->em->persist(new Badge(1, 'first', 'label 1'));
        $this->em->flush();
        $this->em->clear();
        self::assertInstanceOf(Badge::class, $this->em->find(Badge::class, 1));

        $badge = new Badge(2, 'second', 'label 2');
        $this->em->persist($badge);
        $this->em->getUnitOfWork()->computeChangeSets();
        $this->em->flush();
        $this->em->persist(new Badge(3, 'third', 'label 3'));
        $this->em->flush();

        foreach ([2 => ['second', 'label 2'], 3 => ['third', 'label 3']] as $badgeId => [$secret, $label]) {
            $row = $this->em->getConnection()->fetchAssociative('SELECT secret, label FROM ccs_badge WHERE id = ?', [$badgeId]);
            self::assertIsArray($row);
            self::assertSame($label, $row['label']);
            self::assertTrue(is_string($row['secret']));
            self::assertSame($secret, $this->decryptRaw($row['secret']));
        }
        self::assertSame('second', $badge->getSecret());
    }

    /**
     * WE-3 (P07): an entity persisted by a host onFlush listener that runs after the bundle's is stored encrypted.
     */
    public function testEntityPersistedByALaterOnFlushListenerIsStoredEncrypted(): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        $account->setNotSecret('dirty');

        $created  = null;
        $listener = new HostListener(static function (OnFlushEventArgs $args) use (&$created): void {
            if ($created instanceof Account) {
                return;
            }
            $created = new Account('audit', 'x', new Address('Audit St', 'X', new Geo('0,0')));
            $manager = $args->getObjectManager();
            $manager->persist($created);
            $manager->getUnitOfWork()->computeChangeSet($manager->getClassMetadata(Account::class), $created);
        });
        $this->em->getEventManager()->addEventListener([Events::onFlush], $listener);
        $this->em->flush();
        $this->em->getEventManager()->removeEventListener([Events::onFlush], $listener);

        self::assertInstanceOf(Account::class, $created);
        $raw = $this->rawAccount((int) $created->getId());
        self::assertSame('audit', $this->decryptRaw($raw['secret']));
        self::assertSame('Audit St', $this->decryptRaw($raw['address_street']));
        self::assertSame('audit', $created->getSecret(), 'Decrypted again after the flush');

        // Outside a flush, persist() leaves the values alone.
        $later = new Account('later', 'x', new Address('L', 'X', new Geo('1,1')));
        $this->em->persist($later);
        self::assertSame('later', $later->getSecret());
    }

    /**
     * WE-2 (P38): onFlush and preUpdate listeners see the old ciphertext, never the old plaintext, for a changed or
     * cleared encrypted value.
     */
    public function testFlushListenersNeverSeeTheOldPlaintext(): void
    {
        $id      = $this->seedAccount();
        $before  = $this->rawAccount($id);
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);
        $account->setSecret('new value');
        $account->getAddress()->setStreet(null);

        $seen    = [];
        $onFlush = new HostListener(static function (OnFlushEventArgs $args) use (&$seen): void {
            $uow = $args->getObjectManager()->getUnitOfWork();
            foreach ($uow->getScheduledEntityUpdates() as $entity) {
                $seen['onFlush'] = $uow->getEntityChangeSet($entity);
            }
        });
        $preUpdate = new HostListener(static function (PreUpdateEventArgs $args) use (&$seen): void {
            $seen['preUpdate'] = $args->getEntityChangeSet();
        });
        $events = $this->em->getEventManager();
        $events->addEventListener([Events::onFlush], $onFlush);
        $events->addEventListener([Events::preUpdate], $preUpdate);
        $this->em->flush();
        $events->removeEventListener([Events::onFlush], $onFlush);
        $events->removeEventListener([Events::preUpdate], $preUpdate);

        foreach (['onFlush', 'preUpdate'] as $event) {
            self::assertArrayHasKey($event, $seen);
            self::assertSame(['secret', 'address.street'], array_keys($seen[$event]), $event);
            self::assertSame($before['secret'], $seen[$event]['secret'][0], $event . ': old value is the stored ciphertext');
            self::assertStringEndsWith(self::MARKER, (string) $seen[$event]['secret'][1], $event);
            self::assertSame($before['address_street'], $seen[$event]['address.street'][0], $event);
            self::assertNull($seen[$event]['address.street'][1], $event);
        }
        $row = $this->em->getConnection()->fetchAssociative('SELECT secret, address_street FROM ccs_account WHERE id = ?', [$id]);
        self::assertIsArray($row);
        self::assertTrue(is_string($row['secret']));
        self::assertSame('new value', $this->decryptRaw($row['secret']));
        self::assertNull($row['address_street']);
    }

    /**
     * A host postFlush listener that changes an entity and flushes again (the outer flush's change sets are still in
     * the unit of work then), running after or before the bundle's postFlush.
     *
     * @return iterable<string, array{bool}>
     */
    public static function postFlushListenerOrder(): iterable
    {
        yield 'after the bundle' => [false];
        yield 'before the bundle' => [true];
    }

    #[DataProvider('postFlushListenerOrder')]
    public function testNestedFlushInAPostFlushListenerIsWritten(bool $beforeBundle): void
    {
        $id      = $this->seedAccount();
        $account = $this->em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        $done     = false;
        $listener = new HostListener(function () use ($account, &$done): void {
            if ($done) {
                return;
            }
            $done = true;
            $account->setNotSecret('nested');
            $account->getAddress()->setStreet('nested street');
            $this->em->flush();
        });
        $events = $this->em->getEventManager();
        if ($beforeBundle) {
            $events->removeEventListener([Events::postFlush], $this->subscriber);
            $events->addEventListener([Events::postFlush], $listener);
            $events->addEventListener([Events::postFlush], $this->subscriber);
        } else {
            $events->addEventListener([Events::postFlush], $listener);
        }
        $account->setSecret('outer');
        $this->em->flush();
        $events->removeEventListener([Events::postFlush], $listener);

        $raw = $this->rawAccount($id);
        self::assertSame('outer', $this->decryptRaw($raw['secret']));
        self::assertSame('nested street', $this->decryptRaw($raw['address_street']));
        self::assertSame('nested', $this->em->getConnection()->fetchOne('SELECT not_secret FROM ccs_account WHERE id = ?', [$id]));
        self::assertSame('outer', $account->getSecret());
        self::assertSame('nested street', $account->getAddress()->getStreet());
    }

    private function accountReference(int $id): Account
    {
        $reference = $this->em->getReference(Account::class, $id);
        self::assertInstanceOf(Account::class, $reference);

        return $reference;
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
