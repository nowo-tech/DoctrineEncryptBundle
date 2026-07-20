<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Configuration;

use Attribute;

use function is_array;

/**
 * The `Encrypted` class is a PHP attribute that can be applied to properties and is used as a
 * placeholder for encryption functionality.
 *
 * When no alias is given (e.g. #[Encrypted] or #[Encrypted('default')]), the encryptor for default_profile is used.
 * When an alias is given (e.g. #[Encrypted('personal_data')]), that profile from profiles is used.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Encrypted implements Annotation
{
    /** Profile alias from nowo_doctrine_encrypt.profiles. Use "default" when no alias: then default_profile is used. */
    public string $config = 'default';

    /**
     * @param array|string $configOrValues Config alias (e.g. 'personal_data'), or annotation array with 'config'/'value' key
     * @param string|null $config Config alias when using named argument (e.g. #[Encrypted(config: 'financial_data')])
     */
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
