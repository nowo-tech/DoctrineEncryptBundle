<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\BasicQueryTest;

use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Tests\Functional\AbstractFunctionalTestCase;
use Nowo\DoctrineEncryptBundle\Tests\Functional\fixtures\Entity\CascadeTarget;

abstract class AbstractBasicQueryTestCase extends AbstractFunctionalTestCase
{
    public function testPersistEntity(): void
    {
        $beforeCount = $this->getCurrentQueryCount();
        $user = new CascadeTarget();
        $user->setNotSecret('My public information');
        $user->setSecret('top secret information');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Start transaction; insert; commit
        $this->assertEquals('top secret information', $user->getSecret());
        $this->assertEquals(3, $this->getCurrentQueryCount() - $beforeCount);
    }

    public function testNoUpdateOnReadEncrypted(): void
    {
        $this->entityManager->beginTransaction();
        $baseCount = $this->getCurrentQueryCount();
        $this->assertGreaterThanOrEqual(1, $baseCount);

        $user = new CascadeTarget();
        $user->setNotSecret('My public information');
        $user->setSecret('top secret information');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $deltaAfterFirstFlush = $this->getCurrentQueryCount() - $baseCount;
        $this->assertGreaterThanOrEqual(2, $deltaAfterFirstFlush, 'At least begin + insert (or + commit)');

        $afterFirstFlush = $this->getCurrentQueryCount();

        // Test if no query is executed when doing nothing
        $this->entityManager->flush();
        $this->assertEquals($afterFirstFlush, $this->getCurrentQueryCount());

        // Test if no query is executed when reading unrelated field
        $user->getNotSecret();
        $this->entityManager->flush();
        $this->assertEquals($afterFirstFlush, $this->getCurrentQueryCount());

        // Test if no query is executed when reading related field and if field is valid
        $this->assertEquals('top secret information', $user->getSecret());
        $this->entityManager->flush();
        $this->assertEquals($afterFirstFlush, $this->getCurrentQueryCount());

        // Test if at least 1 query is executed when updating entity
        $user->setSecret('top secret information change');
        $this->entityManager->flush();
        $this->assertGreaterThanOrEqual(1, $this->getCurrentQueryCount() - $afterFirstFlush);
        $this->assertEquals('top secret information change', $user->getSecret());

        $this->entityManager->rollback();
        $rollbackDelta = $this->getCurrentQueryCount() - $afterFirstFlush;
        $this->assertGreaterThanOrEqual(1, $rollbackDelta, 'Rollback triggers at least one query');
    }

    public function testStoredDataIsEncrypted(): void
    {
        $user = new CascadeTarget();
        $user->setNotSecret('My public information');
        $user->setSecret('my secret');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $queryData = $this->getLatestInsertQuery();
        $this->assertNotNull($queryData);
        $params = $queryData['params'] ?? [];
        $params = array_values($params);
        $passwordData = $params !== [] && $params[0] === 'My public information' ? $params[1] : ($params[0] ?? $queryData['sql']);

        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER, (string) $passwordData);
        $this->assertStringDoesNotContain('my secret', (string) $passwordData);

        $user->setSecret('my secret has changed');
        $this->entityManager->flush();

        $queryData = $this->getLatestUpdateQuery();
        $this->assertNotNull($queryData);
        $params = array_values($queryData['params'] ?? []);
        $passwordData = $params[0] ?? $queryData['sql'];

        $this->assertStringEndsWith(DoctrineEncryptSubscriber::ENCRYPTION_MARKER, (string) $passwordData);
        $this->assertStringDoesNotContain('my secret', (string) $passwordData);
    }
}
