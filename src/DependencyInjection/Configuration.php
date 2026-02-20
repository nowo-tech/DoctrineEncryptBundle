<?php

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the bundle. Full reference: docs/CONFIGURATION.md
 *
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_doctrine_encrypt';

    /**
     * The getConfigTreeBuilder function creates a config tree builder for a PHP application, with default
     * values for encryptor_class and secret_directory_path.
     *
     * @return TreeBuilder The `TreeBuilder` object is being returned.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        // Create tree builder
        $treeBuilder = new TreeBuilder(self::ALIAS);
        if (\method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older (TreeBuilder::root() before getRootNode() existed)
            /** @codeCoverageIgnoreStart - getRootNode() exists in current symfony/config */
            $rootNode = \call_user_func([$treeBuilder, 'root'], self::ALIAS);
            /** @codeCoverageIgnoreEnd */
        }

        // Single grammar: default_config + configs. When #[Encrypted] has no alias (or "default"), the encryptor for default_config is used.
        $rootNode
            ->children()
                ->scalarNode('default_config')
                    ->defaultValue('default')
                    ->info('Config alias to use when #[Encrypted] has no alias or uses "default".')
                ->end()
                ->arrayNode('configs')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('encryptor_class')->defaultValue('Halite')->end()
                            ->scalarNode('secret_directory_path')->defaultValue('%kernel.project_dir%')->end()
                        ->end()
                    ->end()
                    ->info('Map of config alias => { encryptor_class, secret_directory_path }.')
                ->end()
            ->end();
        //
        return $treeBuilder;
    }
}
