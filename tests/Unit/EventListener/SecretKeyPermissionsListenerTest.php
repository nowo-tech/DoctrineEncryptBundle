<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\EventListener;

use Nowo\DoctrineEncryptBundle\EventListener\SecretKeyPermissionsListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecretKeyPermissionsListenerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/nowo-encrypt-listener-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
    }

    public function testSubscribedEvents(): void
    {
        $events = SecretKeyPermissionsListener::getSubscribedEvents();

        self::assertSame(['onKernelRequest', 1024], $events[KernelEvents::REQUEST]);
        self::assertSame(['onConsoleCommand', 1024], $events['console.command']);
    }

    public function testConsoleCreatesDirectoryAndHardensKeysOnEveryCommand(): void
    {
        $dir      = $this->root . '/var/secrets';
        $listener = $this->listener(['default' => $dir . '/.Halite.default.key', 'env' => null]);

        $listener->onConsoleCommand();
        clearstatcache();
        self::assertDirectoryExists($dir);
        self::assertSame(0o770, fileperms($dir) & 0o777);

        file_put_contents($dir . '/.Halite.default.key', 'k');
        chmod($dir . '/.Halite.default.key', 0o644);
        $listener->onConsoleCommand();
        clearstatcache();
        self::assertSame(0o600, fileperms($dir . '/.Halite.default.key') & 0o777);

        chmod($dir . '/.Halite.default.key', 0o644);
        $listener->onConsoleCommand();
        clearstatcache();
        self::assertSame(0o600, fileperms($dir . '/.Halite.default.key') & 0o777);
    }

    public function testHttpChecksAreThrottledPerWorker(): void
    {
        $dir = $this->root . '/secrets';
        mkdir($dir, 0o700, true);
        $key = $dir . '/.Defuse.default.key';
        file_put_contents($key, 'k');
        chmod($key, 0o644);

        $listener = $this->listener(['default' => $key]);
        $listener->onKernelRequest($this->requestEvent());
        clearstatcache();
        self::assertSame(0o600, fileperms($key) & 0o777);

        // Second request within the interval: no filesystem work.
        chmod($key, 0o644);
        $listener->onKernelRequest($this->requestEvent());
        clearstatcache();
        self::assertSame(0o644, fileperms($key) & 0o777);

        // Console always checks.
        $listener->onConsoleCommand();
        clearstatcache();
        self::assertSame(0o600, fileperms($key) & 0o777);
    }

    public function testZeroIntervalChecksEveryMainRequestWithCustomModes(): void
    {
        $dir = $this->root . '/custom';
        $key = $dir . '/.Halite.default.key';

        $listener = new SecretKeyPermissionsListener(['default' => ['path' => $key, 'encryptor_class' => 'Halite']], 0o700, 0o640, 0);
        $listener->onKernelRequest($this->requestEvent());
        clearstatcache();
        self::assertSame(0o700, fileperms($dir) & 0o777);

        file_put_contents($key, 'k');
        chmod($key, 0o666);
        $listener->onKernelRequest($this->requestEvent());
        clearstatcache();
        self::assertSame(0o640, fileperms($key) & 0o777);
    }

    public function testSubRequestIsIgnored(): void
    {
        $dir = $this->root . '/sub';
        $this->listener(['default' => $dir . '/.Halite.default.key'])
            ->onKernelRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));

        self::assertDirectoryDoesNotExist($dir);
    }

    /**
     * @param array<string, string|null> $paths
     */
    private function listener(array $paths): SecretKeyPermissionsListener
    {
        $keyPaths = [];
        foreach ($paths as $name => $path) {
            $keyPaths[$name] = ['path' => $path, 'encryptor_class' => 'Halite'];
        }

        return new SecretKeyPermissionsListener($keyPaths);
    }

    private function requestEvent(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }
}
