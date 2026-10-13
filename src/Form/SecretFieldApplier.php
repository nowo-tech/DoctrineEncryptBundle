<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Form;

use function is_array;
use function is_string;

/**
 * Applies a "leave blank to keep" secret form field (plain override + optional clear checkbox).
 *
 * - clear checked      → `$setter(null)` (the stored secret is removed)
 * - non-empty plain    → `$setter($plain)` (the stored secret is replaced)
 * - otherwise          → no call (the stored secret is kept)
 *
 * Works with or without symfony/form: `apply()` takes plain values, `applyData()` takes the
 * array submitted by {@see Type\SecretType} (`['value' => ?string, 'clear' => bool]`).
 */
final class SecretFieldApplier
{
    /** Data key of the plain (new) secret submitted by {@see Type\SecretType}. */
    public const KEY_VALUE = 'value';

    /** Data key of the "clear" checkbox submitted by {@see Type\SecretType}. */
    public const KEY_CLEAR = 'clear';

    /**
     * @param bool $clear Whether the "clear" checkbox was checked (wins over $plain)
     * @param string|null $plain The submitted plain secret (null or '' keeps the stored value)
     * @param callable(string|null): mixed $setter Receives the new secret, or null to clear it
     */
    public static function apply(bool $clear, ?string $plain, callable $setter): void
    {
        if ($clear) {
            $setter(null);

            return;
        }

        if ($plain !== null && $plain !== '') {
            $setter($plain);
        }
    }

    /**
     * Same as {@see apply()} for the data of a {@see Type\SecretType} field.
     *
     * Missing keys, non-string values and a null/non-array payload keep the stored secret.
     *
     * @param mixed $data Usually `$form->get('apiKey')->getData()`: `['value' => ?string, 'clear' => bool]`
     * @param callable(string|null): mixed $setter Receives the new secret, or null to clear it
     */
    public static function applyData(mixed $data, callable $setter): void
    {
        if (!is_array($data)) {
            return;
        }

        $value = $data[self::KEY_VALUE] ?? null;

        self::apply(
            ($data[self::KEY_CLEAR] ?? false) === true,
            is_string($value) ? $value : null,
            $setter,
        );
    }
}
