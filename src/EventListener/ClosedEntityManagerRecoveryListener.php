<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\EventListener;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;
use function method_exists;

/**
 * Resets entity managers left closed by a failed flush before the next main request is handled.
 *
 * The encrypt listener re-encrypts managed entities in preFlush and only decrypts them again in postFlush,
 * which Doctrine does not dispatch when the flush fails. The failed flush also closes the manager. When the
 * kernel is not reset between requests (e.g. FrankenPHP worker mode without services_resetter), that closed
 * manager and its identity map of still-encrypted entities would be reused by later requests. Open managers
 * are never touched (their identity map is not cleared).
 *
 * Only the managers the bundle encrypts for are reset (2.11.1): `closed_manager_recovery.entity_managers` when set,
 * else the managers whose connection gets the encrypt listener (`connections`; every manager when it is empty).
 * Any other closed manager belongs to the host and is left as it is.
 */
final readonly class ClosedEntityManagerRecoveryListener implements EventSubscriberInterface
{
    /**
     * @param ManagerRegistry|null $managerRegistry Doctrine registry (null when DoctrineBundle is absent)
     * @param list<string> $connections Connections the encrypt listener is attached to (empty = every one)
     * @param list<string> $entityManagers Managers the bundle may reset (empty = derived from $connections)
     */
    public function __construct(
        private ?ManagerRegistry $managerRegistry = null,
        private array $connections = [],
        private array $entityManagers = [],
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

    /**
     * Resets the bundle's entity managers left closed by a previous request (FrankenPHP worker mode).
     *
     * @param RequestEvent $event Kernel request event (main requests only)
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->managerRegistry instanceof ManagerRegistry) {
            return;
        }

        foreach ($this->managerRegistry->getManagers() as $name => $manager) {
            if ($this->isClosed($manager) && $this->isEncryptedManager($this->managerRegistry, (string) $name, $manager)) {
                $this->managerRegistry->resetManager((string) $name);
            }
        }
    }

    private function isClosed(ObjectManager $manager): bool
    {
        return method_exists($manager, 'isOpen') && !$manager->isOpen();
    }

    private function isEncryptedManager(ManagerRegistry $registry, string $name, ObjectManager $manager): bool
    {
        if ($this->entityManagers !== []) {
            return in_array($name, $this->entityManagers, true);
        }
        if ($this->connections === []) {
            return true;
        }
        $connection = method_exists($manager, 'getConnection') ? $manager->getConnection() : null;
        foreach ($registry->getConnections() as $connectionName => $candidate) {
            if ($candidate === $connection) {
                return in_array((string) $connectionName, $this->connections, true);
            }
        }

        return false;
    }
}
