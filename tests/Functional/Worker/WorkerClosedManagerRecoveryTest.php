<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function gc_collect_cycles;
use function memory_get_usage;

/**
 * FrankenPHP worker mode with the kernel kept alive and never reset (FRANKENPHP_RESET_KERNEL=false, no services
 * resetter): one booted kernel serves every request through the http_kernel service, so kernel.reset never runs.
 * A closed manager the bundle encrypts for is reset at the next main request; the host's own closed manager (on a
 * connection without the encrypt listener) is never touched.
 */
final class WorkerClosedManagerRecoveryTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return WorkerTestKernel::class;
    }

    public function testBundleManagerRecoversOnTheNextRequestAndHostManagerIsUntouched(): void
    {
        self::bootKernel(['debug' => false]);
        $container = self::getContainer();
        $doctrine  = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $host      = $this->manager($doctrine, 'default');
        $encrypted = $this->manager($doctrine, 'encrypted');
        $http      = $container->get('http_kernel');
        $this->assertInstanceOf(HttpKernelInterface::class, $http);

        $host->close();
        for ($i = 0; $i < 20; ++$i) {
            // A failed flush of the previous request left the encrypted manager closed.
            $encrypted->close();
            $this->serve($http);

            $this->assertTrue($this->manager($doctrine, 'encrypted')->isOpen(), 'The bundle manager is reset at the next request.');
            $this->assertFalse($host->isOpen(), 'The bundle must never reset the host manager.');
            $this->assertSame($host, $doctrine->getManager('default'));
        }
    }

    /**
     * REQ-RUNTIME-002: ONE kept-alive kernel, no services resetter, 1,200 requests alternating two users. Every other
     * request starts with the bundle's manager closed by a failed flush of the previous request; each response carries
     * only its own user, the bundle manager is reset at each main request, the host manager is never touched, and
     * memory growth after warm-up stays under 4 MiB.
     */
    public function testAlternatingUsersOverManyRequestsKeepNoStateAndMemoryStaysFlat(): void
    {
        self::bootKernel(['debug' => false]);
        $container = self::getContainer();
        $doctrine  = $container->get('doctrine');
        $this->assertInstanceOf(ManagerRegistry::class, $doctrine);
        $host = $this->manager($doctrine, 'default');
        $http = $container->get('http_kernel');
        $this->assertInstanceOf(HttpKernelInterface::class, $http);

        $host->close();
        $baseline = 0;
        for ($i = 0; $i < 1200; ++$i) {
            $user = $i % 2 === 0 ? 'alice' : 'bob';
            if ($i % 2 === 1) {
                $this->manager($doctrine, 'encrypted')->close();
            }

            $request  = Request::create('/whoami', 'GET', ['user' => $user . '-' . $i]);
            $response = $http->handle($request);
            $this->assertSame('user:' . $user . '-' . $i, $response->getContent(), 'request ' . $i . ' carries only its own user');
            if ($http instanceof TerminableInterface) {
                $http->terminate($request, $response);
            }

            $this->assertTrue($this->manager($doctrine, 'encrypted')->isOpen(), 'request ' . $i . ': the bundle manager is open again');
            $this->assertFalse($host->isOpen(), 'The bundle must never reset the host manager.');

            if ($i === 100) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        $this->assertLessThan(4 * 1024 * 1024, memory_get_usage() - $baseline, 'memory stays flat over many requests');
    }

    private function manager(ManagerRegistry $doctrine, string $name): EntityManagerInterface
    {
        $manager = $doctrine->getManager($name);
        $this->assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function serve(HttpKernelInterface $http): void
    {
        $request  = Request::create('/ping');
        $response = $http->handle($request);
        $this->assertSame('ok', $response->getContent());
        if ($http instanceof TerminableInterface) {
            $http->terminate($request, $response);
        }
    }
}
