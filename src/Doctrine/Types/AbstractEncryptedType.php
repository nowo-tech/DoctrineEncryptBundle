<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Nowo\DoctrineEncryptBundle\Util\EncryptedValue;
use Throwable;

use function is_resource;
use function is_string;
use function str_contains;
use function stream_get_contents;
use function strstr;
use function substr;

/**
 * Base class of the encrypted DBAL types: the value is encrypted when Doctrine converts it for the database and
 * decrypted when it converts the row back, so the entity only ever holds plaintext (no listener, no setter call).
 *
 * The stored format is the listener's: `<ciphertext><ENC>`, produced by the profile's encryptor, so a column can move
 * between `#[Encrypted]` and an encrypted type without migrating data. Like the listener, `null` and `''` are stored
 * as-is and a stored value without the marker is returned as plaintext (rows written before encryption was enabled).
 *
 * The profile is read from the registered type name: `encrypted_string` uses default_profile,
 * `encrypted_string.financial_data` uses the `financial_data` profile. Subclasses may override {@see self::getProfile()}.
 *
 * Extend a concrete type and override {@see self::toPlaintext()} / {@see self::fromPlaintext()} to store a value
 * object.
 */
abstract class AbstractEncryptedType extends Type
{
    /** Separator between the type name and the profile alias in registered type names. */
    public const PROFILE_SEPARATOR = '.';

    /**
     * Turns the PHP value into the plaintext to encrypt (null = store NULL).
     */
    abstract protected function toPlaintext(mixed $value): ?string;

    /**
     * Turns the decrypted plaintext (or a stored value without the marker) back into the PHP value.
     */
    abstract protected function fromPlaintext(string $plaintext): mixed;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        $plaintext = $this->toPlaintext($value);
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }

        return EncryptedTypeRuntime::encryptor($this->getProfile())->encrypt($plaintext) . EncryptedValue::MARKER;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        $stored = is_string($value) ? $value : (string) $value;

        if (!EncryptedValue::hasMarker($stored)) {
            return $this->fromPlaintext($stored);
        }

        $profile = $this->getProfile();

        try {
            $plaintext = EncryptedTypeRuntime::encryptor($profile)->decrypt(EncryptedValue::payload($stored));
        } catch (Throwable $e) {
            return $this->onDecryptFailure($profile, $stored, $e);
        }

        return $this->fromPlaintext($plaintext);
    }

    /**
     * Profile alias used to encrypt and decrypt ("default" = default_profile): the part of the registered type name
     * after the first dot, "default" when there is none.
     */
    public function getProfile(): string
    {
        $name = $this->getRegisteredName();

        return str_contains($name, self::PROFILE_SEPARATOR) ? substr((string) strstr($name, self::PROFILE_SEPARATOR), 1) : 'default';
    }

    /**
     * Name this instance is registered under in the DBAL type registry.
     */
    public function getRegisteredName(): string
    {
        return Type::getTypeRegistry()->lookupName($this);
    }

    /**
     * DBAL 3 compatibility (abstract there, gone in DBAL 4).
     */
    public function getName(): string
    {
        return $this->getRegisteredName();
    }

    /**
     * DBAL 3 compatibility: keep the type in the column comment so schema diffs do not see a plain string column.
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    /**
     * Applies the on_decrypt_failure policy, as the listener does: "throw" = DecryptionFailedException, "null" = null,
     * "keep" = the ciphertext without the marker (passed through {@see self::fromPlaintext()}; when that fails, e.g. JSON
     * or a value object, DecryptionFailedException). "keep" and "null" log a warning. The stored value is not rewritten
     * unless the property is changed.
     */
    private function onDecryptFailure(string $profile, string $stored, Throwable $e): mixed
    {
        $mode = EncryptedTypeRuntime::onDecryptFailure();
        $name = $this->getRegisteredName();
        if ($mode === DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_THROW) {
            throw DecryptionFailedException::forType($name, $profile, $e);
        }

        EncryptedTypeRuntime::logger()->warning('Could not decrypt a value of DBAL type {type} (config "{config}"): {exception_class}: {reason}. {outcome}', [
            'type'            => $name,
            'config'          => $profile,
            'exception_class' => $e::class,
            'reason'          => $e->getMessage(),
            'outcome'         => $mode === DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL ? 'The value is null.' : 'The raw value is kept.',
            'exception'       => $e,
        ]);

        if ($mode === DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_NULL) {
            return null;
        }

        try {
            return $this->fromPlaintext(EncryptedValue::payload($stored));
        } catch (Throwable) {
            // The PHP value cannot hold the ciphertext (JSON, value object): fail closed.
            throw DecryptionFailedException::forType($name, $profile, $e);
        }
    }
}
