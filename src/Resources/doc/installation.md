# Installation

1. Download DoctrineEncryptBundle using composer
2. Enable the database encryption bundle
3. Configure the database encryption bundle

### Requirements

 - PHP >=8.0
 - Comes with package: [paragonie/sodium_compat](https://github.com/paragonie/sodium_compat) ^1.5
 - Comes with package: [Halite](https://github.com/paragonie/halite) ^3.0
 - [doctrine/orm](https://packagist.org/packages/doctrine/orm) >= 2.0
 - [symfony/framework-bundle](https://packagist.org/packages/symfony/framework-bundle) >= 2.0

### Step 1: Download DoctrineEncryptBundle using composer

DoctrineEncryptBundle should be installed using [Composer](http://getcomposer.org/):

``` js
{
    "require": {
        "michaeldegroot/doctrine-encrypt-bundle": "3.0.*"
    }
}
```

Now tell composer to download the bundle by running the command:

``` bash
$ php composer.phar update michaeldegroot/doctrine-encrypt-bundle
```

Composer will install the bundle to your project's `vendor/ambta` directory.

### Step 2: Enable the bundle

Enable the bundle in the Symfony2 kernel by adding it in your /app/AppKernel.php file:

``` php
public function registerBundles()
{
    $bundles = array(
        // ...
        Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class,
    );
}
```

### Step 3: Set your configuration

All configuration value's are optional.
On the following page you can find the configuration information.

#### [Configuration](https://github.com/michaeldegroot/DoctrineEncryptBundle/blob/master/src/Resources/doc/configuration.md)
