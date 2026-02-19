<?php

namespace Nowo\DoctrineEncryptBundle\Configuration;

use Attribute;

/**
 * The `Encrypted` class is a PHP attribute that can be applied to properties and is used as a
 * placeholder for encryption functionality.
 *
 * When the bundle is configured with multiple encryptor configs (e.g. personal_data, financial_data),
 * use the optional first parameter or "config" to select which encryptor to use for this property.
 *
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted implements Annotation
{
    /** Config alias from nowo_doctrine_encrypt.configs (e.g. personal_data, financial_data). Use "default" for the default encryptor. */
    public string $config = 'default';

    public function __construct(string|array $configOrValues = 'default', ?string $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } elseif (is_array($configOrValues)) {
            $this->config = $configOrValues['config'] ?? $configOrValues['value'] ?? 'default';
        } else {
            $this->config = $configOrValues;
        }
    }
}
