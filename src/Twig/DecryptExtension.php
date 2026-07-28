<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Twig;

use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Util\EncryptUtil;
use Twig\Attribute\AsTwigFilter;

use function strlen;

/**
 * Twig extension for decryption filter.
 *
 * Provides filter: decrypt – decrypts values that end with the encryption marker ('<ENC>').
 *
 * Same config philosophy as EncryptUtil: optional config name to choose encryptor;
 * omit (null) = default config, or pass a config name (e.g. 'financial_data').
 *
 * Usage: {{ value|decrypt }} or {{ value|decrypt('financial_data') }}
 *
 * Output is not marked HTML-safe — Twig auto-escaping applies (do not reintroduce is_safe html).
 *
 * To decrypt then mask: {{ value|decrypt('personal_data')|mask(4) }} (mask from MaskExtension).
 */
final class DecryptExtension
{
    public function __construct(
        private readonly EncryptorRegistry $registry
    ) {
    }

    /**
     * Decrypts a value if it has the encryption marker at the end.
     *
     * @param mixed $value The value to decrypt
     * @param string|null $config Encryptor config name (e.g. 'financial_data'). Null = default config
     */
    #[AsTwigFilter('decrypt')]
    public function decrypt(mixed $value, ?string $config = null): mixed
    {
        if ($value === null) {
            return null;
        }
        $str = (string) $value;
        if ($str === '') {
            return '';
        }
        if (!str_ends_with($str, EncryptUtil::ENCRYPTION_MARKER)) {
            return $value;
        }

        $encryptor = $this->getEncryptor($config);

        return $encryptor->decrypt(substr($str, 0, -strlen(EncryptUtil::ENCRYPTION_MARKER)));
    }

    /**
     * Returns the encryptor for the given config name (or the default). Same logic as EncryptUtil::getEncryptor.
     *
     * @param string|null $config Config alias, or null for default
     */
    private function getEncryptor(?string $config): EncryptorInterface
    {
        return $config !== null
            ? $this->registry->get($config)
            : $this->registry->getDefault();
    }
}
