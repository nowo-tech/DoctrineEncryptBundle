<?php

namespace Nowo\DoctrineEncryptBundle\Configuration;

use Attribute;

/**
 * The `Encrypted` class is a PHP attribute that can be applied to properties and is used as a
 * placeholder for encryption functionality.
 *
 * When no alias is given (e.g. #[Encrypted] or #[Encrypted('default')]), the encryptor for default_config is used.
 * When an alias is given (e.g. #[Encrypted('personal_data')]), that config from configs is used.
 *
 * @Annotation
 * @Target("PROPERTY")
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted implements Annotation
{
    /** Config alias from nowo_doctrine_encrypt.configs. Use "default" when no alias: then default_config is used. */
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
