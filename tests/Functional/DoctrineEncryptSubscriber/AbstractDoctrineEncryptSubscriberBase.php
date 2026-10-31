<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\DoctrineEncryptSubscriber;

use Doctrine\ORM\Events;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Functional\AbstractFunctionalTestCase;
use Nowo\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity\CascadeTarget;
use Nowo\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity\Owner;

abstract class AbstractDoctrineEncryptSubscriberBase extends AbstractFunctionalTestCase
{
    public function testEncryptionHappensOnOnlyAnnotatedFields(): void
    {
        $secret    = "It's a secret";
        $notSecret = "You're all welcome to know this.";
        $em        = $this->entityManager;
        $owner     = new Owner();
        $owner->setSecret($secret);
        $owner->setNotSecret($notSecret);
        $em->persist($owner);
        $em->flush();
        $em->clear();
        unset($owner);

        $connection = $em->getConnection();
        $owners     = $em->getRepository(Owner::class)->findAll();
        $this->assertCount(1, $owners);
        /** @var Owner $owner */
        $owner = $owners[0];
        $this->assertEquals($secret, $owner->getSecret());
        $this->assertEquals($notSecret, $owner->getNotSecret());
        $results = $connection->fetchAllAssociative('SELECT * from owner WHERE id = ?', [$owner->getId()]);
        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals($notSecret, $result['notSecret']);
        $this->assertNotEquals($secret, $result['secret']);
        $this->assertStringEndsWith('<ENC>', $result['secret']);
        $decrypted = $this->encryptor->decrypt(str_replace('<ENC>', '', $result['secret']));
        $this->assertEquals($secret, $decrypted);
    }

    public function testFlushOnAnotherEntityManagerKeepsTheSharedListenerStateOfThisOne(): void
    {
        $secret = 'shared listener secret';
        $other  = $this->createSecondEntityManager();

        $owner = new Owner();
        $owner->setSecret($secret);
        $owner->setNotSecret('before');
        $other->persist($owner);
        $other->flush();
        $id = $owner->getId();
        $other->clear();
        $stored = $other->getConnection()->fetchOne('SELECT secret FROM owner WHERE id = ?', [$id]);

        $loaded = $other->find(Owner::class, $id);
        $this->assertInstanceOf(Owner::class, $loaded);
        $this->assertSame($secret, $loaded->getSecret());

        // A flush on the first entity manager must not drop the second one's decryption state...
        $this->entityManager->flush();

        // ...so an unchanged secret is written back as the same ciphertext (no re-encryption, no UPDATE).
        $encryptionsBefore = $this->subscriber->encryptCounter;
        $loaded->setNotSecret('after');
        $other->flush();

        $this->assertSame($encryptionsBefore, $this->subscriber->encryptCounter);
        $row = $other->getConnection()->fetchAssociative('SELECT * FROM owner WHERE id = ?', [$id]);
        $this->assertIsArray($row);
        $this->assertSame('after', $row['notSecret']);
        $this->assertSame($stored, $row['secret']);
        $this->assertSame($secret, $loaded->getSecret());
    }

    public function testEncryptionCascades(): void
    {
        $secret    = "It's a secret";
        $notSecret = "You're all welcome to know this.";
        $em        = $this->entityManager;
        $owner     = new Owner();
        $em->persist($owner); // persist cascades
        $em->flush();

        $cascadeTarget = new CascadeTarget();
        $cascadeTarget->setSecret($secret);
        $cascadeTarget->setNotSecret($notSecret);
        $owner->setCascaded($cascadeTarget);
        $em->flush();
        $em->clear();
        unset($owner);
        unset($cascadeTarget);

        $connection     = $em->getConnection();
        $cascadeTargets = $em->getRepository(CascadeTarget::class)->findAll();
        $this->assertCount(1, $cascadeTargets);
        /** @var CascadeTarget $cascadeTarget */
        $cascadeTarget = $cascadeTargets[0];
        $this->assertEquals($secret, $cascadeTarget->getSecret());
        $this->assertEquals($notSecret, $cascadeTarget->getNotSecret());
        $results = $connection->fetchAllAssociative('SELECT * from cascadeTarget WHERE id = ?', [$cascadeTarget->getId()]);
        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertEquals($notSecret, $result['notSecret']);
        $this->assertNotEquals($secret, $result['secret']);
        $this->assertStringEndsWith('<ENC>', $result['secret']);
        $decrypted = $this->encryptor->decrypt(str_replace('<ENC>', '', $result['secret']));
        $this->assertEquals($secret, $decrypted);
    }

    public function testEncryptionDoesNotHappenWhenThereIsNoChange(): void
    {
        $secret    = "It's a secret";
        $notSecret = "You're all welcome to know this.";
        $em        = $this->entityManager;
        $owner1    = new Owner();
        $owner1->setSecret($secret);
        $owner1->setNotSecret($notSecret);
        $em->persist($owner1);
        $owner2 = new Owner();
        $owner2->setSecret($secret);
        $owner2->setNotSecret($notSecret);
        $em->persist($owner2);

        $em->flush();
        $em->clear();
        $owner1Id = $owner1->getId();
        unset($owner1);
        unset($owner2);

        // test that it was encrypted correctly
        $connection = $em->getConnection();
        $results    = $connection->fetchAllAssociative('SELECT * from owner WHERE id = ?', [$owner1Id]);
        $this->assertCount(1, $results);
        $result             = $results[0];
        $originalEncryption = $result['secret'];
        $this->assertStringEndsWith('<ENC>', $originalEncryption); // is encrypted

        $owners = $em->getRepository(Owner::class)->findAll();
        /** @var Owner $owner */
        foreach ($owners as $owner) {
            $this->assertEquals($secret, $owner->getSecret());
            $this->assertEquals($notSecret, $owner->getNotSecret());
        }
        $beforeQueryCount = $this->getCurrentQueryCount();
        $beforeFlush      = $this->subscriber->encryptCounter;
        $em->flush();
        $afterFlush      = $this->subscriber->encryptCounter;
        $afterQueryCount = $this->getCurrentQueryCount();
        // No encryption should have happened because we didn't change anything.
        $this->assertEquals($beforeFlush, $afterFlush);
        // No queries happened because we didn't change anything.
        $this->assertEquals($beforeQueryCount, $afterQueryCount, 'Unexpected queries on first flush');

        // flush again
        $beforeFlush = $this->subscriber->encryptCounter;
        $em->flush();
        $afterFlush       = $this->subscriber->encryptCounter;
        $afterQueryCount2 = $this->getCurrentQueryCount();
        $this->assertEquals($beforeFlush, $afterFlush);
        $this->assertEquals($afterQueryCount, $afterQueryCount2, 'Unexpected queries on second flush');

        $results = $connection->fetchAllAssociative('SELECT * from owner WHERE id = ?', [$owner1Id]);
        $this->assertCount(1, $results);
        $result                  = $results[0];
        $shouldBeTheSameAsBefore = $result['secret'];
        $this->assertStringEndsWith('<ENC>', $shouldBeTheSameAsBefore); // is encrypted
        $this->assertEquals($originalEncryption, $shouldBeTheSameAsBefore);

    }

    public function testEncryptionDoesHappenWhenASecretIsChanged(): void
    {
        $secret    = "It's a secret";
        $notSecret = "You're all welcome to know this.";
        $em        = $this->entityManager;
        $owner     = new Owner();
        $owner->setSecret($secret);
        $owner->setNotSecret($notSecret);
        $em->persist($owner);
        $em->flush();
        $em->clear();
        $ownerId = $owner->getId();
        unset($owner);

        // test that it was encrypted correctly
        $connection = $em->getConnection();
        $results    = $connection->fetchAllAssociative('SELECT * from owner WHERE id = ?', [$ownerId]);
        $this->assertCount(1, $results);
        $result             = $results[0];
        $originalEncryption = $result['secret'];
        $this->assertStringEndsWith('<ENC>', $originalEncryption); // is encrypted

        /** @var Owner $owner */
        $owner = $em->getRepository(Owner::class)->find($ownerId);
        $owner->setSecret('A NEW SECRET!!!');
        $beforeFlush = $this->subscriber->encryptCounter;
        $em->flush();
        $afterFlush = $this->subscriber->encryptCounter;
        // No encryption should have happened because we didn't change anything.
        $this->assertGreaterThan($beforeFlush, $afterFlush);

        $results = $connection->fetchAllAssociative('SELECT * from owner WHERE id = ?', [$ownerId]);
        $this->assertCount(1, $results);
        $result                      = $results[0];
        $shouldBeDifferentFromBefore = $result['secret'];
        $this->assertStringEndsWith('<ENC>', $shouldBeDifferentFromBefore); // is encrypted
        $this->assertNotEquals($originalEncryption, $shouldBeDifferentFromBefore);
    }

    public function testPlaintextEndingWithTheMarkerIsStoredEncrypted(): void
    {
        $secret = 'smtp://user:not-a-real-password@mail.test:25<ENC>';
        $em     = $this->entityManager;
        $owner  = new Owner();
        $owner->setSecret($secret);
        $em->persist($owner);
        $em->flush();
        $id = $owner->getId();
        $this->assertSame($secret, $owner->getSecret(), 'decrypted again after flush');
        $em->clear();

        $stored = $em->getConnection()->fetchOne('SELECT secret FROM owner WHERE id = ?', [$id]);
        $this->assertIsString($stored);
        $this->assertNotSame($secret, $stored, 'never stored in clear');
        $this->assertSame($secret, $this->encryptor->decrypt(substr($stored, 0, -5)));

        $loaded = $em->find(Owner::class, $id);
        $this->assertInstanceOf(Owner::class, $loaded);
        $this->assertSame($secret, $loaded->getSecret());

        // Changing it to another marker-ending plaintext encrypts it as well.
        $loaded->setSecret('second<ENC>');
        $em->flush();
        $em->clear();
        $stored = $em->getConnection()->fetchOne('SELECT secret FROM owner WHERE id = ?', [$id]);
        $this->assertIsString($stored);
        $this->assertSame('second<ENC>', $this->encryptor->decrypt(substr($stored, 0, -5)));
    }

    public function testMarkerEndingPlaintextSurvivesRepeatedUpdatesWithoutClear(): void
    {
        $secret = 'smtp://user:not-a-real-password@mail.test:25<ENC>';
        $em     = $this->entityManager;
        $owner  = new Owner();
        $owner->setSecret($secret);
        $owner->setNotSecret('v0');
        $em->persist($owner);
        $em->flush();
        $id     = $owner->getId();
        $stored = $em->getConnection()->fetchOne('SELECT secret FROM owner WHERE id = ?', [$id]);

        foreach (['v1', 'v2'] as $notSecret) {
            // Unrelated change: postUpdate and postFlush both decrypt in the same flush.
            $owner->setNotSecret($notSecret);
            $em->flush();

            $this->assertSame($secret, $owner->getSecret());
            $row = $em->getConnection()->fetchAssociative('SELECT * FROM owner WHERE id = ?', [$id]);
            $this->assertIsArray($row);
            $this->assertSame($notSecret, $row['notSecret']);
            $this->assertSame($stored, $row['secret'], 'ciphertext unchanged, never plaintext');
        }

        // Changing the secret itself, without clear(), also round-trips.
        $owner->setSecret('changed<ENC>');
        $em->flush();
        $owner->setNotSecret('v3');
        $em->flush();
        $this->assertSame('changed<ENC>', $owner->getSecret());
        $stored = $em->getConnection()->fetchOne('SELECT secret FROM owner WHERE id = ?', [$id]);
        $this->assertIsString($stored);
        $this->assertSame('changed<ENC>', $this->encryptor->decrypt(substr($stored, 0, -5)));
    }

    public function testUndecryptableValueIsWrittenBackUnchangedWhenOnDecryptFailureIsNull(): void
    {
        $em    = $this->entityManager;
        $owner = new Owner();
        $owner->setSecret('placeholder');
        $owner->setNotSecret('before');
        $em->persist($owner);
        $em->flush();
        $id = $owner->getId();
        $em->clear();
        // Ciphertext from another key (or corrupted): it must survive an unrelated change.
        $em->getConnection()->executeStatement('UPDATE owner SET secret = ? WHERE id = ?', ['Zm9yZWlnbi1rZXk=<ENC>', $id]);

        $events = [Events::postUpdate, Events::preUpdate, Events::postLoad, Events::onFlush, Events::preFlush, Events::postFlush];
        $em->getEventManager()->removeEventListener($events, $this->subscriber);
        $strict = new DoctrineEncryptSubscriber($this->encryptor, null, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL);
        $em->getEventManager()->addEventListener($events, $strict);

        $loaded = $em->find(Owner::class, $id);
        $this->assertInstanceOf(Owner::class, $loaded);
        $this->assertNull($loaded->getSecret(), 'callers never receive ciphertext');

        $loaded->setNotSecret('after');
        $em->flush();

        $row = $em->getConnection()->fetchAssociative('SELECT * FROM owner WHERE id = ?', [$id]);
        $this->assertIsArray($row);
        $this->assertSame('after', $row['notSecret']);
        $this->assertSame('Zm9yZWlnbi1rZXk=<ENC>', $row['secret']);
    }
}
