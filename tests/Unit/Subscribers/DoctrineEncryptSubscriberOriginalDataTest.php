<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\UnitOfWork;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\FakeUnitOfWork;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

use function spl_object_id;

/**
 * The unit of work's original data follows the values the listener swaps, so a decrypted entity is not dirty.
 */
final class DoctrineEncryptSubscriberOriginalDataTest extends TestCase
{
    private DoctrineEncryptSubscriber $subscriber;

    /** @var (callable(): void)|null */
    private $onDecrypt;

    protected function setUp(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'encrypted-' . $plain);
        $encryptor->method('decrypt')->willReturnCallback(function (string $cipher): string {
            if ($this->onDecrypt !== null) {
                $callback        = $this->onDecrypt;
                $this->onDecrypt = null;
                $callback();
            }

            return (string) preg_replace('/^encrypted-/', '', $cipher);
        });
        $this->onDecrypt  = null;
        $this->subscriber = new DoctrineEncryptSubscriber($encryptor);
    }

    public function testPostLoadSetsThePlaintextAsOriginalData(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $uow  = $this->unitOfWork([spl_object_id($user) => ['name' => 'encrypted-David<ENC>', 'address' => 'encrypted-Switzerland<ENC>', 'id' => 7]]);

        $this->subscriber->postLoad(new PostLoadEventArgs($user, $this->entityManager($uow, $this->metadata(['name', 'address', 'id']))));

        $this->assertSame('David', $user->name);
        $this->assertSame(['name' => 'David', 'address' => 'Switzerland', 'id' => 7], $uow->original[spl_object_id($user)]);
    }

    public function testPostUpdateSetsThePlaintextAsOriginalData(): void
    {
        $user = new User('encrypted-David<ENC>', null);
        $uow  = $this->unitOfWork([spl_object_id($user) => ['name' => 'encrypted-David<ENC>', 'address' => null]]);

        $this->subscriber->postUpdate(new PostUpdateEventArgs($user, $this->entityManager($uow, $this->metadata(['name', 'address']))));

        $this->assertSame(['name' => 'David', 'address' => null], $uow->original[spl_object_id($user)]);
    }

    public function testEmbeddedFieldIsMatchedThroughItsOriginalField(): void
    {
        $user                    = new User('encrypted-David<ENC>', null);
        $metadata                = $this->metadata([]);
        $mapping                 = new FieldMapping('string', 'profile.name', 'profile_name');
        $mapping->originalField  = 'name';
        $metadata->fieldMappings = ['profile.name' => $mapping];
        $uow                     = $this->unitOfWork([spl_object_id($user) => ['profile.name' => 'encrypted-David<ENC>']]);

        $this->subscriber->postLoad(new PostLoadEventArgs($user, $this->entityManager($uow, $metadata, ['profile.name' => 'name'])));

        $this->assertSame(['profile.name' => 'David'], $uow->original[spl_object_id($user)]);
    }

    public function testOriginalDataThatIsNotTheDecryptedCiphertextIsLeftAlone(): void
    {
        $user = new User('encrypted-David<ENC>', null);
        $uow  = $this->unitOfWork([spl_object_id($user) => ['name' => 'something else']]);

        $this->subscriber->postLoad(new PostLoadEventArgs($user, $this->entityManager($uow, $this->metadata(['name']))));

        $this->assertSame('David', $user->name);
        $this->assertSame(['name' => 'something else'], $uow->original[spl_object_id($user)]);
    }

    public function testEntityWithoutOriginalDataIsNotLookedUp(): void
    {
        $user = new User('encrypted-David<ENC>', null);
        $uow  = $this->unitOfWork([]);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->expects($this->never())->method('getClassMetadata');

        $this->subscriber->postLoad(new PostLoadEventArgs($user, $em));

        $this->assertSame('David', $user->name);
        $this->assertSame([], $uow->original);
    }

    public function testNothingSwappedMeansNothingToSync(): void
    {
        $user = new User('David', null);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('getUnitOfWork');

        $this->subscriber->postLoad(new PostLoadEventArgs($user, $em));

        $this->assertSame('David', $user->name);
    }

    public function testPreFlushSetsTheStoredCiphertextAsOriginalDataOfUnchangedValuesOnly(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $this->subscriber->processFields($user, false);
        $user->setAddress('Peru');
        $uow = $this->unitOfWork([spl_object_id($user) => ['name' => 'David', 'address' => 'Switzerland']], [User::class => ['id1' => $user]]);

        $this->subscriber->preFlush(new PreFlushEventArgs($this->entityManager($uow, $this->metadata(['name', 'address']))));

        $this->assertSame('encrypted-David<ENC>', $user->name);
        $this->assertSame('encrypted-Peru<ENC>', $user->getAddress());
        // The unchanged value is not dirty any more; the changed one has the old value's stored ciphertext as original.
        $this->assertSame(['name' => 'encrypted-David<ENC>', 'address' => 'encrypted-Switzerland<ENC>'], $uow->original[spl_object_id($user)]);
        $this->assertSame(0, $uow->recomputed);
    }

    public function testPreFlushKeepsTheStoredCiphertextOfAClearedValueAsOriginalData(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $this->subscriber->processFields($user, false);
        $user->setAddress(null);
        $uow = $this->unitOfWork([spl_object_id($user) => ['name' => 'David', 'address' => 'Switzerland']], [User::class => ['id1' => $user]]);

        $this->subscriber->preFlush(new PreFlushEventArgs($this->entityManager($uow, $this->metadata(['name', 'address']))));

        $this->assertNull($user->getAddress());
        $this->assertSame(['name' => 'encrypted-David<ENC>', 'address' => 'encrypted-Switzerland<ENC>'], $uow->original[spl_object_id($user)]);
    }

    public function testPreFlushLeavesEntitiesItDidNotDecryptToOnFlush(): void
    {
        // Same class as a decrypted entity, but never decrypted itself (a new entity): onFlush encrypts insertions.
        $user  = new User('David', null);
        $uow   = $this->unitOfWork([spl_object_id($user) => ['name' => 'David']], [User::class => ['id1' => $user]]);
        $other = new User('encrypted-Other<ENC>', null);
        $this->subscriber->processFields($other, false);

        $this->subscriber->preFlush(new PreFlushEventArgs($this->entityManager($uow, $this->metadata(['name']))));

        $this->assertSame('David', $user->name);
        $this->assertSame(0, $uow->recomputed);
    }

    public function testPreFlushRestoresADecryptedEntityWhateverItsClass(): void
    {
        // Decrypted through postLoad: matched by identity, not by the identity map's (root) class name.
        $user = new User('encrypted-David<ENC>', null);
        $uow  = $this->unitOfWork([spl_object_id($user) => ['name' => 'encrypted-David<ENC>']], [stdClass::class => ['id1' => $user]]);
        $em   = $this->entityManager($uow, $this->metadata(['name']));
        $this->subscriber->postLoad(new PostLoadEventArgs($user, $em));

        $this->subscriber->preFlush(new PreFlushEventArgs($em));

        $this->assertSame('encrypted-David<ENC>', $user->name);
        $this->assertSame(['name' => 'encrypted-David<ENC>'], $uow->original[spl_object_id($user)]);
    }

    public function testPostFlushDecryptsEntitiesEncryptedOnPersistThatWereNotInserted(): void
    {
        $uow = $this->unitOfWork([]);
        $em  = $this->entityManager($uow, $this->metadata(['name']));
        $this->subscriber->onFlush(new OnFlushEventArgs($em));
        $late = new User('late', null);
        $this->subscriber->prePersist(new PrePersistEventArgs($late, $em));
        $this->assertSame('encrypted-late<ENC>', $late->name);

        $this->subscriber->postFlush(new PostFlushEventArgs($em));

        $this->assertSame('late', $late->name, 'Not in the identity map, decrypted anyway');
        $this->assertSame([], iterator_to_array((function () {
            yield from (new ReflectionProperty(DoctrineEncryptSubscriber::class, 'encryptedOnPersist'))->getValue($this->subscriber);
        })()));
    }

    public function testPreFlushRecomputesAnEntityThatAlreadyHasAChangeSet(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $this->subscriber->processFields($user, false);
        $uow                   = $this->unitOfWork([spl_object_id($user) => ['name' => 'Temp', 'address' => 'Switzerland']], [User::class => ['id1' => $user]]);
        $uow->scheduledUpdates = true;
        // computeChangeSets() ran while name was "Temp": the pending entry holds that plaintext.
        $uow->changeSet = ['name' => ['David', 'Temp']];
        $before         = $this->subscriber->encryptCounter;

        $this->subscriber->preFlush(new PreFlushEventArgs($this->entityManager($uow, $this->metadata(['name', 'address']))));

        // name gets a fresh ciphertext (so the merge replaces the plaintext entry), address follows its ciphertext.
        $this->assertSame($before + 1, $this->subscriber->encryptCounter);
        $this->assertSame('encrypted-David<ENC>', $user->name);
        $this->assertSame(['name' => 'encrypted-David<ENC>', 'address' => 'encrypted-Switzerland<ENC>'], $uow->original[spl_object_id($user)]);
        $this->assertSame(1, $uow->recomputed);
    }

    public function testPreFlushDoesNotRecomputeAnEntityThatIsNotManaged(): void
    {
        $user = new User('encrypted-David<ENC>', null);
        $this->subscriber->processFields($user, false);
        $uow                   = $this->unitOfWork([spl_object_id($user) => ['name' => 'David']], [User::class => ['id1' => $user]]);
        $uow->scheduledUpdates = true;
        $uow->state            = UnitOfWork::STATE_REMOVED;

        $this->subscriber->preFlush(new PreFlushEventArgs($this->entityManager($uow, $this->metadata(['name']))));

        $this->assertSame(0, $uow->recomputed);
        $this->assertSame(['name' => 'encrypted-David<ENC>'], $uow->original[spl_object_id($user)]);
    }

    public function testPrePersistEncryptsOnlyBetweenOnFlushAndPostFlush(): void
    {
        $uow = $this->unitOfWork([]);
        $em  = $this->entityManager($uow, $this->metadata(['name']));

        $outside = new User('plain', null);
        $this->subscriber->prePersist(new PrePersistEventArgs($outside, $em));
        $this->assertSame('plain', $outside->name);

        $this->subscriber->onFlush(new OnFlushEventArgs($em));
        $inside = new User('late', null);
        $this->subscriber->prePersist(new PrePersistEventArgs($inside, $em));
        $this->assertSame('encrypted-late<ENC>', $inside->name);

        $this->subscriber->postFlush(new PostFlushEventArgs($em));
        $after = new User('after', null);
        $this->subscriber->prePersist(new PrePersistEventArgs($after, $em));
        $this->assertSame('after', $after->name);

        // A flush that failed after onFlush: the next flush's preFlush closes the window too.
        $this->subscriber->onFlush(new OnFlushEventArgs($em));
        $this->subscriber->preFlush(new PreFlushEventArgs($em));
        $next = new User('next', null);
        $this->subscriber->prePersist(new PrePersistEventArgs($next, $em));
        $this->assertSame('next', $next->name);
    }

    public function testFlushEventsSkipUninitializedObjects(): void
    {
        $decrypted = new User('encrypted-David<ENC>', null);
        $this->subscriber->processFields($decrypted, false);
        $lazy                 = new User('encrypted-Lazy<ENC>', null);
        $uow                  = $this->unitOfWork([], [User::class => ['id1' => $decrypted, 'id2' => $lazy]]);
        $uow->uninitialized[] = $lazy;
        $em                   = $this->entityManager($uow, $this->metadata(['name']));

        $this->subscriber->preFlush(new PreFlushEventArgs($em));
        $this->subscriber->postFlush(new PostFlushEventArgs($em));

        $this->assertSame('David', $decrypted->name);
        $this->assertSame('encrypted-Lazy<ENC>', $lazy->name, 'An uninitialized object is neither loaded nor touched');
    }

    public function testPostFlushSetsThePlaintextAsOriginalData(): void
    {
        $user = new User('encrypted-David<ENC>', null);
        $uow  = $this->unitOfWork([spl_object_id($user) => ['name' => 'encrypted-David<ENC>']], [User::class => ['id1' => $user]]);

        $this->subscriber->postFlush(new PostFlushEventArgs($this->entityManager($uow, $this->metadata(['name']))));

        $this->assertSame(['name' => 'David'], $uow->original[spl_object_id($user)]);
    }

    public function testNestedLoadDuringADecryptionKeepsBothCollections(): void
    {
        $outer = new User('encrypted-Outer<ENC>', null);
        $inner = new User('encrypted-Inner<ENC>', null);
        $uow   = $this->unitOfWork([
            spl_object_id($outer) => ['name' => 'encrypted-Outer<ENC>'],
            spl_object_id($inner) => ['name' => 'encrypted-Inner<ENC>'],
        ]);
        $em              = $this->entityManager($uow, $this->metadata(['name']));
        $this->onDecrypt = function () use ($inner, $em): void {
            $this->subscriber->postLoad(new PostLoadEventArgs($inner, $em));
        };

        $this->subscriber->postLoad(new PostLoadEventArgs($outer, $em));

        $this->assertSame(['name' => 'Outer'], $uow->original[spl_object_id($outer)]);
        $this->assertSame(['name' => 'Inner'], $uow->original[spl_object_id($inner)]);
        $this->assertNull((new ReflectionProperty(DoctrineEncryptSubscriber::class, 'swappedValues'))->getValue($this->subscriber));
    }

    public function testResetClearsTheCollector(): void
    {
        $property = new ReflectionProperty(DoctrineEncryptSubscriber::class, 'swappedValues');
        $property->setValue($this->subscriber, [['kind' => 'decrypted', 'property' => 'name', 'before' => 'a', 'after' => 'b', 'stored' => null, 'reencrypt' => null]]);

        $this->subscriber->reset();

        $this->assertNull($property->getValue($this->subscriber));
    }

    /**
     * @param array<int, array<string, mixed>> $original
     * @param array<class-string, array<string, object>> $identityMap
     */
    private function unitOfWork(array $original, array $identityMap = []): FakeUnitOfWork
    {
        $uow                   = (new ReflectionClass(FakeUnitOfWork::class))->newInstanceWithoutConstructor();
        $uow->original         = $original;
        $uow->identity         = $identityMap;
        $uow->uninitialized    = [];
        $uow->scheduledUpdates = false;

        return $uow;
    }

    /**
     * @param list<string> $fields
     *
     * @return ClassMetadata<User>&MockObject
     */
    private function metadata(array $fields): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $mappings = [];
        foreach ($fields as $field) {
            $mappings[$field] = new FieldMapping('string', $field, $field);
        }
        $metadata->fieldMappings = $mappings;

        return $metadata;
    }

    /**
     * @param ClassMetadata<User>&MockObject $metadata
     * @param array<string, string> $propertyOfField Property read for a field (default: the field name)
     */
    private function entityManager(FakeUnitOfWork $uow, ClassMetadata $metadata, array $propertyOfField = []): EntityManagerInterface
    {
        $metadata->method('getFieldValue')->willReturnCallback(static function (object $entity, string $field) use ($propertyOfField): mixed {
            $property = new ReflectionProperty($entity, $propertyOfField[$field] ?? $field);

            return $property->getValue($entity);
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);
        $em->method('getClassMetadata')->willReturn($metadata);

        return $em;
    }
}
