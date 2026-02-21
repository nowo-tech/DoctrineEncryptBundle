<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\ChildWithEncryptedOnly;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\EntityWithConfigAlias;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\ExtendedUser;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\WithOptionalUser;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\WithUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DoctrineEncryptSubscriberTest extends TestCase
{
    /**
     * @var DoctrineEncryptSubscriber
     */
    private $subscriber;

    /**
     * @var EncryptorInterface|MockObject
     */
    private $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->encryptor
            ->expects($this->any())
            ->method('encrypt')
            ->willReturnCallback(static function (string $arg) {
                return 'encrypted-' . $arg;
            })
        ;
        $this->encryptor
            ->expects($this->any())
            ->method('decrypt')
            ->willReturnCallback(static function (string $arg) {
                return preg_replace('/^encrypted-/', '', $arg);
            })
        ;

        $this->subscriber = new DoctrineEncryptSubscriber($this->encryptor);
    }

    public function testSetRestorEncryptor(): void
    {
        $replaceEncryptor = $this->createMock(EncryptorInterface::class);

        $this->assertSame($this->encryptor, $this->subscriber->getEncryptor());
        $this->subscriber->setEncryptor($replaceEncryptor);
        $this->assertSame($replaceEncryptor, $this->subscriber->getEncryptor());
        $this->subscriber->restoreEncryptor();
        $this->assertSame($this->encryptor, $this->subscriber->getEncryptor());
    }

    public function testConstructorWithNullRegistryReturnsNullEncryptor(): void
    {
        $subscriber = new DoctrineEncryptSubscriber(null);
        $this->assertNull($subscriber->getEncryptor());
    }

    /** Covers processFields when encryptorOverride is set (uses override instead of registry->get). */
    public function testProcessFieldsEncryptUsesOverrideEncryptor(): void
    {
        $overrideEncryptor = $this->createMock(EncryptorInterface::class);
        $overrideEncryptor->expects($this->exactly(2))
            ->method('encrypt')
            ->willReturnCallback(static fn (string $s) => 'OVR-' . $s);

        $this->subscriber->setEncryptor($overrideEncryptor);

        $user = new User('David', 'Switzerland');
        $this->subscriber->processFields($user, true);

        $this->assertSame('OVR-David<ENC>', $user->name);
        $this->assertSame('OVR-Switzerland<ENC>', $user->getAddress());
    }

    public function testProcessFieldsEncrypt(): void
    {
        $user = new User('David', 'Switzerland');

        $this->subscriber->processFields($user, true);

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertStringStartsWith('encrypted-', $user->getAddress());
    }

    public function testProcessFieldsEncryptExtend(): void
    {
        $user = new ExtendedUser('David', 'Switzerland', 'extra');

        $this->subscriber->processFields($user, true);

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertStringStartsWith('encrypted-', $user->getAddress());
        $this->assertStringStartsWith('encrypted-', $user->extra);
    }

    public function testProcessFieldsEncryptEmbedded(): void
    {
        $withUser = new WithUser('Thing', 'foo', new User('David', 'Switzerland'));

        $this->subscriber->processFields($withUser, true);

        $this->assertStringStartsWith('encrypted-', $withUser->name);
        $this->assertSame('foo', $withUser->foo);
        $this->assertStringStartsWith('encrypted-', $withUser->user->name);
        $this->assertStringStartsWith('encrypted-', $withUser->user->getAddress());
    }

    public function testProcessFieldsEncryptNull(): void
    {
        $user = new User('David', null);

        $this->subscriber->processFields($user, true);

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertNull($user->getAddress());
    }

    public function testProcessFieldsNoEncryptor(): void
    {
        $user       = new User('David', 'Switzerland');
        $subscriber = new DoctrineEncryptSubscriber($this->createMock(EncryptorInterface::class));
        $subscriber->setEncryptor(null);
        $subscriber->processFields($user, true);

        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());
    }

    public function testProcessFieldsDecrypt(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $this->subscriber->processFields($user, false);

        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());
    }

    public function testProcessFieldsDecryptExtended(): void
    {
        $user = new ExtendedUser('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>', 'encrypted-extra<ENC>');

        $this->subscriber->processFields($user, false);

        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());
        $this->assertSame('extra', $user->extra);
    }

    public function testProcessFieldsDecryptEmbedded(): void
    {
        $withUser = new WithUser('encrypted-Thing<ENC>', 'foo', new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>'));

        $this->subscriber->processFields($withUser, false);

        $this->assertSame('Thing', $withUser->name);
        $this->assertSame('foo', $withUser->foo);
        $this->assertSame('David', $withUser->user->name);
        $this->assertSame('Switzerland', $withUser->user->getAddress());
    }

    public function testProcessFieldsDecryptNull(): void
    {
        $user = new User('encrypted-David<ENC>', null);

        $this->subscriber->processFields($user, false);

        $this->assertSame('David', $user->name);
        $this->assertNull($user->getAddress());
    }

    public function testProcessFieldsDecryptNonEncrypted(): void
    {
        // no trailing <ENC> but somethint that our mock decrypt would change if called
        $user = new User('encrypted-David', 'encrypted-Switzerland');

        $this->subscriber->processFields($user, false);

        $this->assertSame('encrypted-David', $user->name);
        $this->assertSame('encrypted-Switzerland', $user->getAddress());
    }

    /** Covers the cache path in processFields: after decrypt, encrypt uses cached value and does not call encryptor again. */
    public function testProcessFieldsEncryptUsesCachedDecryptionWhenValueWasDecrypted(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $this->subscriber->processFields($user, false);
        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());

        $this->encryptor->expects($this->never())->method('encrypt');
        $this->subscriber->processFields($user, true);

        $this->assertSame('encrypted-David<ENC>', $user->name);
        $this->assertSame('encrypted-Switzerland<ENC>', $user->getAddress());
    }

    /**
     * Test that fields are encrypted before flushing.
     */
    public function testOnFlush(): void
    {
        $user = new User('David', 'Switzerland');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->any())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$user])
        ;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($uow)
        ;
        $classMetaData = $this->createMock(ClassMetadata::class);
        $em->expects($this->once())->method('getClassMetadata')->willReturn($classMetaData);
        $uow->expects($this->once())->method('recomputeSingleEntityChangeSet');

        $onFlush = new OnFlushEventArgs($em);

        $this->subscriber->onFlush($onFlush);

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertStringStartsWith('encrypted-', $user->getAddress());
    }

    /**
     * Test that fields are decrypted again after flushing.
     */
    public function testPostFlush(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->any())
            ->method('getIdentityMap')
            ->willReturn([[$user]])
        ;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($uow)
        ;
        $postFlush = new PostFlushEventArgs($em);

        $this->subscriber->postFlush($postFlush);

        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());
    }

    public function testPreUpdateEncryptsEntity(): void
    {
        $user = new User('David', 'Switzerland');

        $em        = $this->createMock(EntityManagerInterface::class);
        $changeSet = [];
        $args      = new PreUpdateEventArgs($user, $em, $changeSet);

        $this->subscriber->preUpdate($args);

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertStringStartsWith('encrypted-', $user->getAddress());
    }

    public function testPostUpdateDecryptsEntity(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $em   = $this->createMock(EntityManagerInterface::class);
        $args = new PostUpdateEventArgs($user, $em);

        $this->subscriber->postUpdate($args);

        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());
    }

    public function testPostLoadDecryptsEntity(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');

        $em   = $this->createMock(EntityManagerInterface::class);
        $args = new PostLoadEventArgs($user, $em);

        $this->subscriber->postLoad($args);

        $this->assertSame('David', $user->name);
        $this->assertSame('Switzerland', $user->getAddress());
    }

    public function testProcessFieldsDoesNotEncryptEmptyString(): void
    {
        $user = new User('', '');

        $this->subscriber->processFields($user, true);

        $this->assertSame('', $user->name);
        $this->assertSame('', $user->getAddress());
    }

    public function testProcessFieldsDoesNotDecryptEmptyOrNull(): void
    {
        $user = new User('', null);

        $this->subscriber->processFields($user, false);

        $this->assertSame('', $user->name);
        $this->assertNull($user->getAddress());
    }

    /** String '0' is not treated as empty; it is encrypted and decrypted like any other value. */
    public function testProcessFieldsWithStringZeroEncryptsAndDecrypts(): void
    {
        $user = new User('0', '0');

        $this->subscriber->processFields($user, true);

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER, $user->name);
        $this->assertStringStartsWith('encrypted-', $user->getAddress());
        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER, $user->getAddress());

        $this->subscriber->processFields($user, false);
        $this->assertSame('0', $user->name);
        $this->assertSame('0', $user->getAddress());
    }

    public function testEncryptCounterIncrementsOnEncrypt(): void
    {
        $user   = new User('David', 'Switzerland');
        $before = $this->subscriber->encryptCounter;

        $this->subscriber->processFields($user, true);

        $this->assertGreaterThan($before, $this->subscriber->encryptCounter);
    }

    public function testDecryptCounterIncrementsOnDecrypt(): void
    {
        $user   = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $before = $this->subscriber->decryptCounter;

        $this->subscriber->processFields($user, false);

        $this->assertGreaterThan($before, $this->subscriber->decryptCounter);
    }

    public function testPreFlushReEncryptsCachedDecryptions(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $this->subscriber->processFields($user, false);
        $this->assertSame('David', $user->name);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->expects($this->any())
            ->method('getIdentityMap')
            ->willReturn([User::class => [$user]]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($uow);

        $this->subscriber->preFlush(new PreFlushEventArgs($em));

        $this->assertStringStartsWith('encrypted-', $user->name);
        $this->assertStringEndsWith('<ENC>', $user->name);
    }

    public function testOnFlushRecomputesChangeSetWhenEncryptionOccurs(): void
    {
        $user = new User('David', 'Switzerland');
        $uow  = $this->createMock(UnitOfWork::class);
        $uow->expects($this->any())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$user]);
        $classMetaData = $this->createMock(ClassMetadata::class);
        $em            = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $em->expects($this->once())
            ->method('getClassMetadata')
            ->with(User::class)
            ->willReturn($classMetaData);
        $uow->expects($this->once())
            ->method('recomputeSingleEntityChangeSet')
            ->with($classMetaData, $user);

        $this->subscriber->onFlush(new OnFlushEventArgs($em));

        $this->assertStringStartsWith('encrypted-', $user->name);
    }

    public function testOnFlushDoesNotRecomputeChangeSetWhenNoEncryptionOccurs(): void
    {
        $user = new User('', '');
        $uow  = $this->createMock(UnitOfWork::class);
        $uow->expects($this->any())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$user]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->any())
            ->method('getUnitOfWork')
            ->willReturn($uow);
        $em->expects($this->never())
            ->method('getClassMetadata');
        $uow->expects($this->never())
            ->method('recomputeSingleEntityChangeSet');

        $this->subscriber->onFlush(new OnFlushEventArgs($em));

        $this->assertSame('', $user->name);
        $this->assertSame('', $user->getAddress());
    }

    public function testEncryptionMarkerConstant(): void
    {
        $this->assertSame('<ENC>', DoctrineEncryptSubscriber::ENCRYPTION_MARKER);
    }

    public function testProcessFieldsReturnsEntityWhenEncryptorIsSet(): void
    {
        $user = new User('David', 'Switzerland');

        $result = $this->subscriber->processFields($user, true);

        $this->assertSame($user, $result);
    }

    public function testProcessFieldsReturnsEntityWhenEncryptorIsNull(): void
    {
        $subscriber = new DoctrineEncryptSubscriber($this->createMock(EncryptorInterface::class));
        $subscriber->setEncryptor(null);
        $user = new User('David', 'Switzerland');

        $result = $subscriber->processFields($user, true);

        $this->assertSame($user, $result);
    }

    public function testConstructorWithEncryptorRegistryUsesDefaultEncryptor(): void
    {
        $defaultEncryptor = $this->createMock(EncryptorInterface::class);
        $defaultEncryptor->method('encrypt')->willReturn('from-default');
        $registry   = new EncryptorRegistry(['default' => $defaultEncryptor], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $this->assertSame($defaultEncryptor, $subscriber->getEncryptor());
    }

    public function testConstructorWithNullRegistryGetEncryptorReturnsNull(): void
    {
        $subscriber = new DoctrineEncryptSubscriber(null);

        $this->assertNull($subscriber->getEncryptor());
    }

    public function testProcessFieldsReturnsEntityWhenRegistryIsNull(): void
    {
        $subscriber = new DoctrineEncryptSubscriber(null);
        $user       = new User('David', 'Switzerland');

        $result = $subscriber->processFields($user, true);

        $this->assertSame($user, $result);
        $this->assertSame('David', $user->name);
    }

    public function testProcessFieldsWithRegistryUsesEncryptorPerConfigAlias(): void
    {
        $defaultEncryptor = $this->createMock(EncryptorInterface::class);
        $defaultEncryptor->method('encrypt')->willReturn('enc-default');
        $otherEncryptor = $this->createMock(EncryptorInterface::class);
        $otherEncryptor->method('encrypt')->willReturn('enc-other');
        $registry = new EncryptorRegistry([
            'default'      => $defaultEncryptor,
            'other_config' => $otherEncryptor,
        ], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $entity = new EntityWithConfigAlias('a', 'b');
        $subscriber->processFields($entity, true);

        $this->assertStringStartsWith('enc-default', $entity->defaultField);
        $this->assertStringStartsWith('enc-other', $entity->otherField);
    }

    public function testProcessFieldsEncryptRestoresFromCacheWhenValueWasDecrypted(): void
    {
        $user = new User('encrypted-David<ENC>', 'encrypted-Switzerland<ENC>');
        $this->subscriber->processFields($user, false);
        $this->assertSame('David', $user->name);

        $this->subscriber->processFields($user, true);

        $this->assertStringEndsWith('<ENC>', $user->name);
        $this->assertStringEndsWith('<ENC>', $user->getAddress());
    }

    public function testProcessFieldsHandleEmbeddedWhenEmbeddedIsNull(): void
    {
        $withUser = new WithOptionalUser('Thing', null);

        $this->subscriber->processFields($withUser, true);

        $this->assertStringStartsWith('encrypted-', $withUser->name);
        $this->assertNull($withUser->user);
    }

    /** When configFilter is set, only properties for that config are processed. */
    public function testProcessFieldsWithConfigFilterOnlyProcessesMatchingConfig(): void
    {
        $defaultEncryptor = $this->createMock(EncryptorInterface::class);
        $defaultEncryptor->method('encrypt')->willReturn('enc-default');
        $otherEncryptor = $this->createMock(EncryptorInterface::class);
        $otherEncryptor->method('encrypt')->willReturn('enc-other');
        $registry = new EncryptorRegistry([
            'default'      => $defaultEncryptor,
            'other_config' => $otherEncryptor,
        ], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $entity = new EntityWithConfigAlias('a', 'b');
        $subscriber->processFields($entity, true, 'other_config');

        $this->assertSame('a', $entity->defaultField);
        $this->assertStringStartsWith('enc-other', $entity->otherField);
        $this->assertStringEndsWith('<ENC>', $entity->otherField);
    }

    /** Covers getClassProperties when parent class has no properties (merge branch not taken). */
    public function testProcessFieldsEncryptEntityWhoseParentHasNoProperties(): void
    {
        $entity = new ChildWithEncryptedOnly('secret-data');

        $this->subscriber->processFields($entity, true);

        $this->assertStringStartsWith('encrypted-', $entity->secret);
        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER, $entity->secret);
    }

    /** Decrypt with configFilter only decrypts properties for that config. */
    public function testProcessFieldsDecryptWithConfigFilterOnlyProcessesMatchingConfig(): void
    {
        $defaultEncryptor = $this->createMock(EncryptorInterface::class);
        $defaultEncryptor->method('decrypt')->willReturn('dec-default');
        $otherEncryptor = $this->createMock(EncryptorInterface::class);
        $otherEncryptor->method('decrypt')->willReturn('dec-other');
        $registry = new EncryptorRegistry([
            'default'      => $defaultEncryptor,
            'other_config' => $otherEncryptor,
        ], 'default');
        $subscriber = new DoctrineEncryptSubscriber($registry);

        $entity = new EntityWithConfigAlias('encrypted-x<ENC>', 'encrypted-y<ENC>');
        $subscriber->processFields($entity, false, 'default');

        $this->assertSame('dec-default', $entity->defaultField);
        $this->assertSame('encrypted-y<ENC>', $entity->otherField);
    }
}
