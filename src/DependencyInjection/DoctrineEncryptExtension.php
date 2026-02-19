<?php

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Initialization of bundle.
 *
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class DoctrineEncryptExtension extends Extension
{
    public const SUPPORTED_ENCRYPTOR_CLASSES = [
      'Defuse' => DefuseEncryptor::class, // 'Ambta\DoctrineEncryptBundle\Encryptors\DefuseEncryptor',
      'Halite' => HaliteEncryptor::class, // 'Ambta\DoctrineEncryptBundle\Encryptors\HaliteEncryptor',
    ];

    /**
     * This function loads the configuration, sets parameters, and loads a service file in a PHP
     * application.
     *
     * @param array configs An array of configuration values passed to the load() method.
     * @param ContainerBuilder container The `` parameter is an instance of the
     * `ContainerBuilder` class. It is used to manage and store service definitions, parameters, and other
     * configuration settings for the application.
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $hasConfigs = isset($config['configs']) && count($config['configs']) > 0;

        if ($hasConfigs) {
            $this->registerConfigs($container, $config);
        } else {
            $this->registerLegacy($container, $config);
        }
    }

    private function registerLegacy(ContainerBuilder $container, array $config): void
    {
        $encryptorClass = $this->resolveEncryptorClass($config['encryptor_class']);
        $secretKeyPath = $config['secret_directory_path'] . '/.' . $config['encryptor_class'] . '.key';

        $container->setParameter('nowo_doctrine_encrypt.encryptor_class_name', $encryptorClass);
        $container->setParameter('nowo_doctrine_encrypt.secret_key_path', $secretKeyPath);

        $container->getDefinition('nowo_doctrine_encrypt.encryptor_registry')
            ->setArguments([['default' => new Reference('nowo_doctrine_encrypt.encryptor')], 'default']);
    }

    private function registerConfigs(ContainerBuilder $container, array $config): void
    {
        $configs = $config['configs'];
        $defaultConfig = $config['default_config'] ?? array_key_first($configs);
        if (!isset($configs[$defaultConfig])) {
            $defaultConfig = array_key_first($configs);
        }

        $encryptorRefs = [];
        foreach ($configs as $name => $options) {
            $encryptorClass = $this->resolveEncryptorClass($options['encryptor_class']);
            $secretKeyPath = $options['secret_directory_path'] . '/.' . $options['encryptor_class'] . '.' . $name . '.key';
            $serviceId = 'nowo_doctrine_encrypt.encryptor.' . $name;
            $container->register($serviceId, $encryptorClass)
                ->setArgument(0, $secretKeyPath)
                ->setPublic(false);
            $encryptorRefs[$name] = new Reference($serviceId);
        }

        $encryptorRefs['default'] = $encryptorRefs[$defaultConfig];
        $container->getDefinition('nowo_doctrine_encrypt.encryptor_registry')
            ->setArguments([$encryptorRefs, $defaultConfig]);

        $container->removeDefinition('nowo_doctrine_encrypt.encryptor');
        $container->setAlias('nowo_doctrine_encrypt.encryptor', 'nowo_doctrine_encrypt.encryptor.' . $defaultConfig);

        $opts = $configs[$defaultConfig];
        $secretKeyPathDefault = $opts['secret_directory_path'] . '/.' . $opts['encryptor_class'] . '.' . $defaultConfig . '.key';
        $container->setParameter('nowo_doctrine_encrypt.encryptor_class_name', $this->resolveEncryptorClass($configs[$defaultConfig]['encryptor_class']));
        $container->setParameter('nowo_doctrine_encrypt.secret_key_path', $secretKeyPathDefault);
    }

    private function resolveEncryptorClass(string $encryptorClass): string
    {
        return array_key_exists($encryptorClass, self::SUPPORTED_ENCRYPTOR_CLASSES)
            ? self::SUPPORTED_ENCRYPTOR_CLASSES[$encryptorClass]
            : $encryptorClass;
    }

    /**
     * Get alias for configuration
     *
     * @return string
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
