<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function count;
use function sprintf;

/**
 * Loads bundle configuration and registers encryptor services per profile.
 *
 * Builds one encryptor service per profile (e.g. nowo_doctrine_encrypt.encryptor.personal_data),
 * the registry, and parameters (key_paths, secret_key_path, etc.).
 * During transition both new and legacy container parameters are set.
 *
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
final class DoctrineEncryptExtension extends Extension
{
    /** Short names mapped to encryptor FQCN (Halite, Defuse). */
    public const SUPPORTED_ENCRYPTOR_CLASSES = [
        'Defuse'   => DefuseEncryptor::class,
        'Halite'   => HaliteEncryptor::class,
        'MysqlAes' => MysqlAesEncryptor::class,
    ];

    /**
     * Loads the bundle configuration, service definitions, and registers encryptor profiles.
     *
     * @param array<mixed> $configs Merged config from config files
     * @param ContainerBuilder $container Container to register services and parameters in
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $profiles = $config['profiles'];
        if (count($profiles) === 0) {
            $profiles                  = [Configuration::DEFAULT_PROFILE_NAME => ['encryptor_class' => 'Halite', 'secret_directory_path' => '%kernel.project_dir%']];
            $config['default_profile'] = Configuration::DEFAULT_PROFILE_NAME;
        }
        $config['profiles'] = $profiles;

        $this->assertNoMysqlAesInProduction($container, $profiles);
        $this->registerProfiles($container, $config);
    }

    /**
     * @param array<string, array<string, mixed>> $profiles
     */
    private function assertNoMysqlAesInProduction(ContainerBuilder $container, array $profiles): void
    {
        if ($container->getParameter('kernel.environment') !== 'prod') {
            return;
        }

        foreach ($profiles as $name => $options) {
            $encryptorClass = $options['encryptor_class'] ?? '';
            if ($encryptorClass === 'MysqlAes' || $encryptorClass === MysqlAesEncryptor::class) {
                throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.profiles.%s uses MysqlAes, which is blocked in production. Use Halite or Defuse and migrate legacy ciphertext.', $name));
            }
        }
    }

    /**
     * Registers one encryptor service per profile and sets the registry and parameters.
     *
     * @param ContainerBuilder $container Container builder
     * @param array<mixed> $config Processed config (profiles, default_profile)
     */
    private function registerProfiles(ContainerBuilder $container, array $config): void
    {
        $profiles       = $config['profiles'];
        $defaultProfile = $config['default_profile'] ?? array_key_first($profiles);
        if (!isset($profiles[$defaultProfile])) {
            throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.default_profile "%s" must be a key in nowo_doctrine_encrypt.profiles. Available: %s.', $defaultProfile, implode(', ', array_keys($profiles))));
        }

        $encryptorRefs = [];
        $keyPaths      = [];
        foreach ($profiles as $name => $options) {
            $encryptorClass = $this->resolveEncryptorClass($options['encryptor_class']);
            $useEnv         = !empty($options['secret_key_env_var']);
            if ($useEnv) {
                $secretKeyPath   = '';
                $keyContent      = $options['secret_key_env_var'];
                $keyPaths[$name] = ['path' => null, 'encryptor_class' => $options['encryptor_class']];
            } else {
                $dir             = $options['secret_directory_path'] ?? '%kernel.project_dir%';
                $filename        = $options['secret_key_filename'] ?? ('.' . $options['encryptor_class'] . '.' . $name . '.key');
                $secretKeyPath   = $dir . '/' . $filename;
                $keyContent      = null;
                $keyPaths[$name] = ['path' => $secretKeyPath, 'encryptor_class' => $options['encryptor_class']];
            }
            $def = $container->register($serviceId = 'nowo_doctrine_encrypt.encryptor.' . $name, $encryptorClass)
                ->setArgument(0, $secretKeyPath)
                ->setPublic(false);
            if ($keyContent !== null) {
                $def->setArgument(1, $keyContent);
            }
            $encryptorRefs[$name] = new Reference($serviceId);
        }
        $container->setParameter('nowo_doctrine_encrypt.key_paths', $keyPaths);

        $encryptorRefs['default'] = $encryptorRefs[$defaultProfile];
        $container->getDefinition('nowo_doctrine_encrypt.encryptor_registry')
            ->setArguments([$encryptorRefs, $defaultProfile]);

        $container->removeDefinition('nowo_doctrine_encrypt.encryptor');
        $container->setAlias('nowo_doctrine_encrypt.encryptor', 'nowo_doctrine_encrypt.encryptor.' . $defaultProfile);

        $opts = $profiles[$defaultProfile];
        if (!empty($opts['secret_key_env_var'])) {
            $secretKeyPathDefault = '';
        } else {
            $dir                  = $opts['secret_directory_path'] ?? '%kernel.project_dir%';
            $filename             = $opts['secret_key_filename'] ?? ('.' . $opts['encryptor_class'] . '.' . $defaultProfile . '.key');
            $secretKeyPathDefault = $dir . '/' . $filename;
        }
        $container->setParameter('nowo_doctrine_encrypt.encryptor_class_name', $this->resolveEncryptorClass($profiles[$defaultProfile]['encryptor_class']));
        $container->setParameter('nowo_doctrine_encrypt.secret_key_path', $secretKeyPathDefault);
        $container->setParameter('nowo_doctrine_encrypt.batch_size', $config['batch_size'] ?? 5);
        $container->setParameter('nowo_doctrine_encrypt.profiles', $profiles);
        $container->setParameter('nowo_doctrine_encrypt.default_profile', $defaultProfile);
        // BC: legacy parameter names (same values)
        $container->setParameter('nowo_doctrine_encrypt.configs', $profiles);
        $container->setParameter('nowo_doctrine_encrypt.default_config', $defaultProfile);
    }

    /**
     * Resolves short encryptor name (Halite, Defuse) to FQCN, or returns custom class name as-is.
     *
     * @param string $encryptorClass Halite, Defuse, or a custom class FQCN
     *
     * @return string Encryptor FQCN
     */
    private function resolveEncryptorClass(string $encryptorClass): string
    {
        return array_key_exists($encryptorClass, self::SUPPORTED_ENCRYPTOR_CLASSES)
            ? self::SUPPORTED_ENCRYPTOR_CLASSES[$encryptorClass]
            : $encryptorClass;
    }

    /**
     * Returns the configuration alias (e.g. nowo_doctrine_encrypt).
     */
    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
