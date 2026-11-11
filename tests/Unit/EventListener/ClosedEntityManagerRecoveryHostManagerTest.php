<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DoctrineEncryptBundle\EventListener\ClosedEntityManagerRecoveryListener;
use Nowo\DoctrineEncryptBundle\Tests\Support\SqliteManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Rule 6 (W0): the recovery resets only the entity managers the bundle encrypts for. A closed manager on a connection
 * excluded through `connections` (or outside `closed_manager_recovery.entity_managers`) belongs to the host and is
 * never reset by the bundle. Real ORM: two in-memory SQLite entity managers behind a real ManagerRegistry.
 */
final class ClosedEntityManagerRecoveryHostManagerTest extends TestCase
{
    public function testClosedManagerOutsideTheListenerConnectionsIsNotReset(): void
    {
        $registry = new SqliteManagerRegistry(['default' => [], 'encrypted' => []]);
        $host     = $this->closedManager($registry, 'default');
        $bundle   = $this->closedManager($registry, 'encrypted');

        (new ClosedEntityManagerRecoveryListener($registry, ['encrypted']))->onKernelRequest($this->mainRequest());

        $this->assertSame($host, $registry->getManager('default'), 'A host manager must not be reset by the bundle.');
        $this->assertNotSame($bundle, $registry->getManager('encrypted'));
        $this->assertTrue($this->manager($registry, 'encrypted')->isOpen());
    }

    public function testExplicitEntityManagersWinOverConnections(): void
    {
        $registry = new SqliteManagerRegistry(['default' => [], 'encrypted' => []]);
        $host     = $this->closedManager($registry, 'default');
        $this->closedManager($registry, 'encrypted');

        (new ClosedEntityManagerRecoveryListener($registry, [], ['encrypted']))->onKernelRequest($this->mainRequest());

        $this->assertSame($host, $registry->getManager('default'));
        $this->assertTrue($this->manager($registry, 'encrypted')->isOpen());
    }

    public function testDefaultOnlyHostKeepsTheRecovery(): void
    {
        $registry = new SqliteManagerRegistry(['default' => []]);
        $manager  = $this->closedManager($registry, 'default');

        (new ClosedEntityManagerRecoveryListener($registry))->onKernelRequest($this->mainRequest());

        $this->assertNotSame($manager, $registry->getManager('default'));
        $this->assertTrue($this->manager($registry, 'default')->isOpen());
    }

    public function testManagerOnAnUnknownConnectionIsNotReset(): void
    {
        $registry = new SqliteManagerRegistry(['default' => []]);
        $manager  = $this->createMock(EntityManagerInterface::class);
        $manager->method('isOpen')->willReturn(false);
        $manager->method('getConnection')->willReturn($this->createMock(Connection::class));
        $mock = $this->createMock(ManagerRegistry::class);
        $mock->method('getManagers')->willReturn(['stray' => $manager]);
        $mock->method('getConnections')->willReturn($registry->getConnections());
        $mock->expects($this->never())->method('resetManager');

        (new ClosedEntityManagerRecoveryListener($mock, ['default']))->onKernelRequest($this->mainRequest());
    }

    private function closedManager(SqliteManagerRegistry $registry, string $name): EntityManagerInterface
    {
        $manager = $this->manager($registry, $name);
        $manager->close();

        return $manager;
    }

    private function manager(SqliteManagerRegistry $registry, string $name): EntityManagerInterface
    {
        $manager = $registry->getManager($name);
        $this->assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function mainRequest(): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST);
    }
}
