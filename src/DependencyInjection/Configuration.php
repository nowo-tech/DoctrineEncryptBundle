<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Security\SecretKeyPermissions;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function sprintf;

/**
 * Defines and validates the bundle configuration tree (default_profile, profiles per encryptor).
 *
 * Legacy YAML keys "default_config" / "configs" are accepted via beforeNormalization.
 *
 * @see docs/CONFIGURATION.md
 * @see http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_doctrine_encrypt';

    public const DEFAULT_PROFILE_NAME = 'default';

    /**
     * Builds the configuration tree (default_profile, profiles with encryptor_class, secret_directory_path, etc.).
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        // Create tree builder
        $treeBuilder = new TreeBuilder(self::ALIAS);
        $rootNode    = $treeBuilder->getRootNode();

        // Single grammar: default_profile + profiles. When #[Encrypted] has no alias (or "default"), the encryptor for default_profile is used.
        $rootNode
            ->beforeNormalization()
                ->always()
                ->then(static function (?array $config): array {
                    $config ??= [];

                    // BC: default_config → default_profile
                    if (!isset($config['default_profile']) && isset($config['default_config'])) {
                        $config['default_profile'] = $config['default_config'];
                        unset($config['default_config']);
                    }

                    // BC: configs → profiles
                    if (!isset($config['profiles']) && isset($config['configs'])) {
                        $config['profiles'] = $config['configs'];
                        unset($config['configs']);
                    }

                    if (!isset($config['default_profile'])) {
                        $profileNames              = array_keys($config['profiles'] ?? []);
                        $config['default_profile'] = $profileNames[0] ?? self::DEFAULT_PROFILE_NAME;
                    }

                    return $config;
                })
            ->end()
            ->children()
                ->scalarNode('default_profile')
                    ->defaultValue(self::DEFAULT_PROFILE_NAME)
                    ->info('Profile name to use when #[Encrypted] has no alias or uses "default".')
                ->end()
                ->integerNode('batch_size')
                    ->defaultValue(5)
                    ->min(1)
                    ->info('Default batch size for doctrine:decrypt:database and doctrine:encrypt:database (raw SQL). Overridable per run via the batchSize argument.')
                ->end()
                ->arrayNode('secret_permissions')
                    ->addDefaultsIfNotSet()
                    ->info('Filesystem hardening for file-based keys: creates missing key directories with directory_mode and chmods key files to file_mode (console: every command; HTTP: at most once per http_check_interval seconds per worker).')
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('directory_mode')
                            ->defaultValue(SecretKeyPermissions::DEFAULT_DIRECTORY_MODE)
                            ->min(0)->max(0o777)
                            ->beforeNormalization()->ifString()->then(static fn (string $v): int => self::parseMode($v))->end()
                            ->info('Mode for key directories created by the bundle (octal string "0770" or "0700", or integer). Existing directories are not changed.')
                        ->end()
                        ->integerNode('file_mode')
                            ->defaultValue(SecretKeyPermissions::DEFAULT_FILE_MODE)
                            ->min(0)->max(0o777)
                            ->beforeNormalization()->ifString()->then(static fn (string $v): int => self::parseMode($v))->end()
                            ->info('Mode enforced on key files (octal string "0600" or integer).')
                        ->end()
                        ->integerNode('http_check_interval')
                            ->defaultValue(60)
                            ->min(0)
                            ->info('Seconds between HTTP checks per worker (0 = every main request).')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('profiles')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('encryptor_class')->defaultValue('Halite')->end()
                            ->scalarNode('secret_directory_path')->defaultNull()->info('Directory for the key file. Required unless secret_key_env_var is set.')->end()
                            ->scalarNode('secret_key_filename')->defaultNull()->info('Optional custom key filename (e.g. .my_app.key). Only used when secret_directory_path is set.')->end()
                            ->scalarNode('secret_key_env_var')->defaultNull()->info('Key content from env: use %env(APP_ENCRYPT_KEY)% so Symfony resolves it at config load and the bundle receives the value. When set, secret_directory_path and secret_key_filename are not allowed.')->end()
                        ->end()
                        ->validate()
                    ->ifTrue(static function (array $v): bool {
                        $useEnv  = isset($v['secret_key_env_var']) && $v['secret_key_env_var'] !== '';
                        $usePath = isset($v['secret_directory_path']) && $v['secret_directory_path'] !== '';

                        return $useEnv && $usePath;
                    })
                            ->thenInvalid('Cannot set both secret_key_env_var and secret_directory_path.')
                        ->end()
                        ->beforeNormalization()
                            ->ifTrue(static fn (array $v): bool => empty($v['secret_key_env_var']) && ($v['secret_directory_path'] ?? null) === null)
                            ->then(static function (array $v): array {
                                $v['secret_directory_path'] = '%kernel.project_dir%';

                                return $v;
                            })
                        ->end()
                    ->end()
                    ->info('Map of profile name => { encryptor_class, secret_directory_path?, secret_key_filename?, secret_key_env_var? }.')
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * Parses an octal permission string ("0770", "0o770", "770") into an integer mode.
     */
    private static function parseMode(string $value): int
    {
        $value = strtolower(trim($value));
        if (str_starts_with($value, '0o')) {
            $value = substr($value, 2);
        }
        if ($value === '' || preg_match('/^[0-7]{1,4}$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid permission mode "%s": use an octal string such as "0770" or "0600".', $value));
        }

        return (int) octdec($value);
    }
}
