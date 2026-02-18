<?php

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
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
        // Create configuration object
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // If empty encryptor class, use Halite encryptor
        if (in_array($config['encryptor_class'], array_keys(self::SUPPORTED_ENCRYPTOR_CLASSES))) {
            $config['encryptor_class_full'] = self::SUPPORTED_ENCRYPTOR_CLASSES[$config['encryptor_class']];
        } else {
            $config['encryptor_class_full'] = $config['encryptor_class'];
        }

        // The code is setting parameters in the Symfony container.
        $container->setParameter('nowo_doctrine_encrypt.encryptor_class_name', $config['encryptor_class_full']);
        $secretKeyPath = $config['secret_directory_path'] . '/.' . $config['encryptor_class'] . '.key';
        $container->setParameter('nowo_doctrine_encrypt.secret_key_path', $secretKeyPath);

        /*
         * The code is creating a new instance of the YamlFileLoader class, which is responsible for loading
         * service definitions from a YAML file. It takes two parameters: $container, which is an instance of
         * the ContainerBuilder class and is used to manage and store service definitions, and new
         * FileLocator(__DIR__ . '/../Resources/config'), which is used to locate the YAML file.
         **/
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
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
