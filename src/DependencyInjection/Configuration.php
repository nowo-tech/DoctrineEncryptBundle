<?php

namespace Nowo\DoctrineEncryptBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for security bundle. Full tree you can see in Resources/docs
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
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root(self::ALIAS);
        }

        // Grammar of config tree
        $rootNode
          ->children()
          ->scalarNode('encryptor_class')->defaultValue('Halite')
          ->end()
          ->scalarNode('secret_directory_path')->defaultValue('%kernel.project_dir%')
          ->end()
          ->end();
        //
        return $treeBuilder;
    }
}
