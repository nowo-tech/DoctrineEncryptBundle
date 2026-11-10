<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedJsonType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedStringType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTextType;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\EncryptedTypeRuntimeConfigurator;
use Nowo\DoctrineEncryptBundle\Encryptors\DefuseEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\HaliteEncryptor;
use Nowo\DoctrineEncryptBundle\Encryptors\MysqlAesEncryptor;
use Nowo\DoctrineEncryptBundle\EventListener\SecretKeyPermissionsListener;
use Nowo\DoctrineEncryptBundle\Security\SecretKeyPermissions;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function ltrim;
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
final class DoctrineEncryptExtension extends Extension implements PrependExtensionInterface
{
    /** Service that hands the encryptors to the encrypted DBAL types at kernel boot (only with dbal_types: true). */
    public const DBAL_TYPE_RUNTIME_SERVICE = 'nowo_doctrine_encrypt.dbal_type_runtime_configurator';

    /** Base names of the encrypted DBAL types; "<name>.<profile>" is registered per profile too. */
    public const DBAL_TYPES = [
        EncryptedStringType::NAME => EncryptedStringType::class,
        EncryptedTextType::NAME   => EncryptedTextType::class,
        EncryptedJsonType::NAME   => EncryptedJsonType::class,
    ];

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

        $this->configureListenerConnections($container, $config['connections'] ?? []);
        $this->assertNoMysqlAesInProduction($container, $profiles);
        $this->deprecateMysqlAes($profiles);
        $this->registerProfiles($container, $config);
        $container->getDefinition('nowo_doctrine_encrypt.orm_subscriber')
            ->replaceArgument(2, $config['on_decrypt_failure'] ?? DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP)
            ->replaceArgument(4, $config['property_access'] ?? DoctrineEncryptSubscriber::PROPERTY_ACCESS_AUTO);
        $this->registerEncryptedFields($container, $config['encrypted_fields'] ?? [], $profiles);
        $this->registerDbalTypeRuntime($container, (bool) ($config['dbal_types'] ?? false), $config['on_decrypt_failure'] ?? DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP, array_map(strval(...), array_keys($profiles)));
        $this->registerSecretPermissions($container, $config['secret_permissions'] ?? []);
    }

    /**
     * With dbal_types: true, registers the encrypted DBAL types in DoctrineBundle (doctrine.dbal.types).
     *
     * Reads the raw bundle configuration: only "dbal_types" and the profile names are needed here.
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $enabled  = false;
        $profiles = [];
        foreach ($container->getExtensionConfig($this->getAlias()) as $rawConfig) {
            if (isset($rawConfig['dbal_types'])) {
                $enabled = (bool) $rawConfig['dbal_types'];
            }
            foreach (['profiles', 'configs'] as $key) {
                foreach ((array) ($rawConfig[$key] ?? []) as $name => $profile) {
                    $name = is_int($name) && is_array($profile) && isset($profile['name']) ? $profile['name'] : $name;
                    if (!is_int($name)) {
                        // The Config component turns "personal-data" into "personal_data" (the registry name).
                        $profiles[] = str_replace('-', '_', (string) $name);
                    }
                }
            }
        }

        if (!$enabled) {
            return;
        }

        $types = [];
        foreach (self::dbalTypeMap($profiles) as $name => $class) {
            $types[$name] = ['class' => $class];
        }
        $container->prependExtensionConfig('doctrine', ['dbal' => ['types' => $types]]);
    }

    /**
     * Encrypted DBAL type names => classes: the base names (default_profile) and "<name>.<profile>" per profile.
     *
     * @param list<string> $profileNames
     *
     * @return array<string, class-string<AbstractEncryptedType>>
     */
    public static function dbalTypeMap(array $profileNames): array
    {
        $map = self::DBAL_TYPES;
        foreach (array_values(array_unique($profileNames)) as $profile) {
            if ($profile === '' || $profile === 'default') {
                continue;
            }
            foreach (self::DBAL_TYPES as $name => $class) {
                $map[$name . '.' . $profile] = $class;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $profileNames
     */
    private function registerDbalTypeRuntime(ContainerBuilder $container, bool $enabled, string $onDecryptFailure, array $profileNames): void
    {
        $container->setParameter('nowo_doctrine_encrypt.dbal_types', $enabled ? array_keys(self::dbalTypeMap($profileNames)) : []);
        if (!$enabled) {
            return;
        }

        $container->register(self::DBAL_TYPE_RUNTIME_SERVICE, EncryptedTypeRuntimeConfigurator::class)
            ->setArguments([
                new Reference('nowo_doctrine_encrypt.encryptor_registry'),
                $onDecryptFailure,
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
    }

    /**
     * Validates encrypted_fields (class, properties, profiles) and passes it to the field map service. A typo must
     * fail the container build: otherwise the field would silently stay in plaintext.
     *
     * @param array<string, array{properties?: array<string, string|null>, embedded?: list<string>}> $fields
     * @param array<string, mixed> $profiles
     */
    private function registerEncryptedFields(ContainerBuilder $container, array $fields, array $profiles): void
    {
        foreach ($fields as $class => $entry) {
            $reflection = $container->getReflectionClass($class, false);
            if (!$reflection instanceof ReflectionClass) {
                throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: class "%s" does not exist.', $class));
            }
            if ($reflection->getName() !== ltrim($class, '\\')) {
                // PHP class names are case-insensitive, the map (and the listener lookup) is not.
                throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: write "%s" as "%s" (exact case).', $class, $reflection->getName()));
            }
            if ($reflection->isTrait() || $reflection->isInterface() || $reflection->isEnum()) {
                throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: "%s" is not a class; list the entity or embeddable classes that use it.', $class));
            }
            $properties = $this->classProperties($reflection);

            $embedded = $entry['embedded'] ?? [];
            foreach ($entry['properties'] ?? [] as $property => $profile) {
                $property = (string) $property;
                if (!isset($properties[$property])) {
                    throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s has no property "%s".', $class, $property));
                }
                if ($profile !== null && $profile !== 'default' && !isset($profiles[$profile])) {
                    throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s::$%s uses unknown profile "%s". Available: %s.', $class, $property, $profile, implode(', ', array_keys($profiles))));
                }
                if ($properties[$property]->getAttributes(Encrypted::class) !== []) {
                    throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s::$%s already carries #[Encrypted]; select it in one place only.', $class, $property));
                }
                if (in_array($property, $embedded, true)) {
                    throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s::$%s cannot be both an encrypted property and an embedded one.', $class, $property));
                }
            }
            foreach ($embedded as $property) {
                if (!isset($properties[$property])) {
                    throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s has no property "%s".', $class, $property));
                }
                $type = $properties[$property]->getType();
                if ($type instanceof ReflectionNamedType && $type->isBuiltin() && !in_array($type->getName(), ['object', 'mixed'], true)) {
                    throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s::$%s is typed %s; "embedded" takes embedded objects only (list scalar fields under "properties").', $class, $property, $type->getName()));
                }
            }
        }

        $container->getDefinition('nowo_doctrine_encrypt.encrypted_field_map')->setArgument(0, $fields);
    }

    /**
     * Properties of the class and its parents (private parent properties included).
     *
     * @param ReflectionClass<object> $reflection
     *
     * @return array<string, ReflectionProperty>
     */
    private function classProperties(ReflectionClass $reflection): array
    {
        $properties = [];
        for ($class = $reflection; $class instanceof ReflectionClass; $class = $class->getParentClass() ?: null) {
            foreach ($class->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        }

        return $properties;
    }

    /**
     * Tags the ORM listener for the configured connections (none listed = every connection, which is
     * what DoctrineBundle does for a doctrine.event_listener tag without a "connection" attribute).
     *
     * @param list<string> $connections
     */
    private function configureListenerConnections(ContainerBuilder $container, array $connections): void
    {
        foreach (['nowo_doctrine_encrypt.orm_subscriber', 'nowo_doctrine_encrypt.mapping_validator'] as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $tags       = $definition->getTag('doctrine.event_listener');
            $definition->clearTag('doctrine.event_listener');

            foreach ($tags as $attributes) {
                unset($attributes['connection']);
                if ($connections === []) {
                    $definition->addTag('doctrine.event_listener', $attributes);
                    continue;
                }

                foreach (array_values(array_unique($connections)) as $connection) {
                    $definition->addTag('doctrine.event_listener', $attributes + ['connection' => $connection]);
                }
            }
        }
    }

    /**
     * Configures (or removes) the key file permission listener.
     *
     * @param array<string, mixed> $options Processed secret_permissions node
     */
    private function registerSecretPermissions(ContainerBuilder $container, array $options): void
    {
        $directoryMode = (int) ($options['directory_mode'] ?? SecretKeyPermissions::DEFAULT_DIRECTORY_MODE);
        $fileMode      = (int) ($options['file_mode'] ?? SecretKeyPermissions::DEFAULT_FILE_MODE);
        $container->setParameter('nowo_doctrine_encrypt.secret_permissions.directory_mode', $directoryMode);
        $container->setParameter('nowo_doctrine_encrypt.secret_permissions.file_mode', $fileMode);

        if (!($options['enabled'] ?? true)) {
            $container->removeDefinition(SecretKeyPermissionsListener::class);

            return;
        }

        $container->getDefinition(SecretKeyPermissionsListener::class)
            ->setArguments([
                '%nowo_doctrine_encrypt.key_paths%',
                $directoryMode,
                $fileMode,
                (int) ($options['http_check_interval'] ?? 60),
            ]);
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
            if ($this->isMysqlAes((string) ($options['encryptor_class'] ?? ''))) {
                throw new InvalidArgumentException(sprintf('nowo_doctrine_encrypt.profiles.%s uses MysqlAes, which is blocked in production. Use Halite or Defuse and migrate legacy ciphertext.', $name));
            }
        }
    }

    /**
     * MysqlAes is AES-128-ECB without authentication (malleable, foreign keys pass the padding check about 1 time in 256).
     *
     * @param array<string, array<string, mixed>> $profiles
     */
    private function deprecateMysqlAes(array $profiles): void
    {
        foreach ($profiles as $name => $options) {
            if ($this->isMysqlAes((string) ($options['encryptor_class'] ?? ''))) {
                trigger_deprecation('nowo-tech/doctrine-encrypt-bundle', '2.10', 'nowo_doctrine_encrypt.profiles.%s uses MysqlAes (AES-128-ECB, unauthenticated), which is insecure and will be removed in 3.0. Use Halite and migrate the ciphertext (see docs/MYSQL_AES.md).', $name);
            }
        }
    }

    private function isMysqlAes(string $encryptorClass): bool
    {
        return $encryptorClass === 'MysqlAes' || $encryptorClass === MysqlAesEncryptor::class;
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
                // @igor-ignore - Justified false positive for FrankenPHP worker audit
                $def->setArgument(1, $keyContent);
            }
            if ($encryptorClass === HaliteEncryptor::class || $encryptorClass === DefuseEncryptor::class) {
                // null = only in debug: outside debug a missing key file is an error, never a silent new key.
                // @igor-ignore - Container definition at compile time (not a runtime service)
                $def->setArgument(1, $keyContent);
                // @igor-ignore - Container definition at compile time (not a runtime service)
                $def->setArgument(2, $config['generate_missing_key'] ?? '%kernel.debug%');
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
