# Symfony Flex Recipe

This folder contains the **Symfony Flex recipe** for `nowo-tech/doctrine-encrypt-bundle`.

## Automatic installation (when recipe is on the Flex server)

Once this recipe is merged in [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib), running:

```bash
composer require nowo-tech/doctrine-encrypt-bundle
```

will automatically:

- Register the bundle in `config/bundles.php`
- Create `config/packages/nowo_doctrine_encrypt.yaml`
- Add `.Halite.key` and `.Defuse.key` to `.gitignore`

## Submitting the recipe to symfony/recipes-contrib

To enable the recipe for everyone:

1. Fork [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib).
2. Copy the contents of `Recipe/1.0/` to `nowo-tech/doctrine-encrypt-bundle/1.0/` in your fork.
3. Open a pull request.

See [Contributing to Symfony Flex Recipes](https://github.com/symfony/recipes-contrib#contributing).

## Private recipe server

To use this recipe before it is in recipes-contrib, you can set up a [private Flex recipe repository](https://symfony.com/doc/current/setup/flex_private_recipes.html) that points to a repo containing this `Recipe/` structure.
