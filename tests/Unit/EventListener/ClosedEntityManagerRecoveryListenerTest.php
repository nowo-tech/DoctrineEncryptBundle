<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nowo\DoctrineEncryptBundle\EventListener\ClosedEntityManagerRecoveryListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ClosedEntityManagerRecoveryListenerTest extends TestCase
{
    public function testSubscribesToMainRequestWithHighPriority(): void
    {
        $this->assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 4096]],
            ClosedEntityManagerRecoveryListener::getSubscribedEvents(),
        );
    }

    public function testClosedManagerFromPreviousRequestIsResetOnNextMainRequest(): void
    {
        $closed = $this->createMock(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);
        $open = $this->createMock(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);
        $open->expects($this->never())->method('clear');
        $other = $this->createMock(ObjectManager::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $closed, 'audit' => $open, 'odm' => $other]);
        $registry->expects($this->once())->method('resetManager')->with('default');

        (new ClosedEntityManagerRecoveryListener($registry))->onKernelRequest($this->createEvent(HttpKernelInterface::MAIN_REQUEST));
    }

    public function testSubRequestsDoNotResetManagers(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagers');
        $registry->expects($this->never())->method('resetManager');

        (new ClosedEntityManagerRecoveryListener($registry))->onKernelRequest($this->createEvent(HttpKernelInterface::SUB_REQUEST));
    }

    public function testWithoutRegistryDoesNothing(): void
    {
        (new ClosedEntityManagerRecoveryListener())->onKernelRequest($this->createEvent(HttpKernelInterface::MAIN_REQUEST));

        $this->addToAssertionCount(1);
    }

    private function createEvent(int $requestType): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), new Request(), $requestType);
    }
}
