<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Functional\Worker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function sys_get_temp_dir;

/**
 * Host with two entity managers: "default" (host-owned, no encrypt listener) and "encrypted" (the only connection
 * listed in nowo_doctrine_encrypt.connections).
 */
final class WorkerTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new NowoDoctrineEncryptBundle();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-doctrine-encrypt-tests/worker/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-doctrine-encrypt-tests/worker/' . $this->environment . '/log';
    }

    public function ping(): Response
    {
        return new Response('ok');
    }

    public function whoami(Request $request): Response
    {
        return new Response('user:' . $request->query->getString('user'));
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'doctrine-encrypt-test',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'php_errors'           => ['log' => false],
        ]);
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default'   => ['url' => 'sqlite:///:memory:'],
                    'encrypted' => ['url' => 'sqlite:///:memory:'],
                ],
            ],
            'orm' => [
                'default_entity_manager' => 'default',
                'entity_managers'        => [
                    'default'   => ['connection' => 'default'],
                    'encrypted' => ['connection' => 'encrypted'],
                ],
            ],
        ]);
        $container->loadFromExtension('nowo_doctrine_encrypt', [
            'connections' => ['encrypted'],
            'profiles'    => ['default' => ['encryptor_class' => 'Halite', 'secret_directory_path' => '%kernel.cache_dir%']],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('ping', '/ping')->controller('kernel::ping');
        $routes->add('whoami', '/whoami')->controller('kernel::whoami');
    }
}
