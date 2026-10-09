<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\EventListener;

use Nowo\DoctrineEncryptBundle\Security\SecretKeyPermissions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function dirname;

/**
 * Ensures key directories exist and key files are not readable by other users.
 *
 * - Console: checked on every command (key generation, rotation, migrations, workers booting).
 * - HTTP: at most once per {@see $httpCheckInterval} seconds per worker (FrankenPHP worker mode,
 *   RoadRunner, PHP-FPM process reuse), so a key generated lazily by the first encryption is still
 *   hardened without a stat()/chmod() on every request.
 *
 * Only file-based profiles are handled; profiles using secret_key_env_var have no path.
 * Existing directories are left untouched (the default key directory is the project root).
 */
final class SecretKeyPermissionsListener implements EventSubscriberInterface
{
    /** Console event name (string literal so symfony/console stays optional). */
    private const CONSOLE_COMMAND_EVENT = 'console.command';

    /**
     * Per-worker throttle timestamp. Deliberately kept across requests and not reset.
     */
    private int $lastHttpCheck = 0;

    /**
     * @param array<string, array{path: string|null, encryptor_class: string}> $keyPaths
     */
    public function __construct(
        private readonly array $keyPaths,
        private readonly int $directoryMode = SecretKeyPermissions::DEFAULT_DIRECTORY_MODE,
        private readonly int $fileMode = SecretKeyPermissions::DEFAULT_FILE_MODE,
        private readonly int $httpCheckInterval = 60,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST       => ['onKernelRequest', 1024],
            self::CONSOLE_COMMAND_EVENT => ['onConsoleCommand', 1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $now = time();
        if ($this->httpCheckInterval > 0 && $now - $this->lastHttpCheck < $this->httpCheckInterval) {
            return;
        }
        // @igor-ignore - Intentional per-worker throttle (at most one filesystem check per interval)
        $this->lastHttpCheck = $now;

        $this->ensure();
    }

    public function onConsoleCommand(): void
    {
        $this->ensure();
    }

    /**
     * Creates missing key directories and hardens existing key files.
     */
    public function ensure(): void
    {
        foreach ($this->keyPaths as $info) {
            $path = $info['path'] ?? null;
            if ($path === null || $path === '') {
                continue;
            }

            SecretKeyPermissions::ensureDirectory(dirname($path), $this->directoryMode);
            SecretKeyPermissions::hardenFile($path, $this->fileMode);
        }
    }
}
