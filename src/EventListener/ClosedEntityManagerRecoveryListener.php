<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resets entity managers left closed by a failed flush before the next main request is handled.
 *
 * The encrypt listener re-encrypts managed entities in preFlush and only decrypts them again in postFlush,
 * which Doctrine does not dispatch when the flush fails. The failed flush also closes the manager. When the
 * kernel is not reset between requests (e.g. FrankenPHP worker mode without services_resetter), that closed
 * manager and its identity map of still-encrypted entities would be reused by later requests. Open managers
 * are never touched (their identity map is not cleared).
 */
final class ClosedEntityManagerRecoveryListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?ManagerRegistry $managerRegistry = null,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->managerRegistry === null) {
            return;
        }

        foreach ($this->managerRegistry->getManagers() as $name => $manager) {
            if ($this->isClosed($manager)) {
                $this->managerRegistry->resetManager((string) $name);
            }
        }
    }

    private function isClosed(ObjectManager $manager): bool
    {
        return method_exists($manager, 'isOpen') && !$manager->isOpen();
    }
}
