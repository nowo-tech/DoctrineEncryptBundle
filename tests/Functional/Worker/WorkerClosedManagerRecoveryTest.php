<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

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
