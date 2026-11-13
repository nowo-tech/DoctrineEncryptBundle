<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Doctrine\Types;

use InvalidArgumentException;
use LogicException;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function in_array;
use function sprintf;

/**
 * Where the encrypted DBAL types ({@see AbstractEncryptedType}) get their encryptors from.
 *
 * DBAL types are stateless singletons created by Doctrine (`new $class()`), so they cannot receive services. The bundle
 * stores the encryptor registry and the `on_decrypt_failure` policy here when the kernel boots
 * ({@see EncryptedTypeRuntimeConfigurator}) and clears it when that kernel shuts down.
 *
 * One process, one configuration: with several kernels booted in the same process (some test suites, multi-kernel
 * apps) the last booted kernel's encryptors are used by every connection. Until it is configured, the types refuse to
 * convert values (never plaintext to the database).
 */
final class EncryptedTypeRuntime
{
    // Deliberate process-wide state: DBAL types cannot receive services. Set once per kernel boot from immutable
    // services and configuration (no per-request or per-user data), cleared on kernel shutdown.
    // @igor-ignore - Set once per kernel boot from the container (immutable services), cleared on kernel shutdown.
    private static ?EncryptorRegistryInterface $registry = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty

    // @igor-ignore - Set once per kernel boot from the bundle configuration.
    private static string $onDecryptFailure = DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty

    // @igor-ignore - Set once per kernel boot from the container (stateless logger service).
    private static ?LoggerInterface $logger = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty

    /**
     * Makes the encrypted DBAL types use these encryptors and this decrypt-failure policy.
     *
     * @param string $onDecryptFailure "keep", "null" or "throw" (same values and meaning as the listener option)
     */
    public static function configure(EncryptorRegistryInterface $registry, string $onDecryptFailure = DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP, ?LoggerInterface $logger = null): void
    {
        if (!in_array($onDecryptFailure, DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_MODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid on_decrypt_failure "%s": use one of "%s".', $onDecryptFailure, implode('", "', DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_MODES)));
        }

        // @igor-ignore - Boot-time configuration of the stateless DBAL types (no request or user data).
        self::$registry = $registry; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - Boot-time configuration of the stateless DBAL types (no request or user data).
        self::$onDecryptFailure = $onDecryptFailure; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - Boot-time configuration of the stateless DBAL types (no request or user data).
        self::$logger = $logger; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
    }

    /**
     * Forgets the configuration. With $onlyIf, only when that registry is the configured one (a kernel shutting down
     * must not clear the configuration of another kernel booted after it).
     */
    public static function clear(?EncryptorRegistryInterface $onlyIf = null): void
    {
        if ($onlyIf instanceof EncryptorRegistryInterface && self::$registry !== $onlyIf) {
            return;
        }

        // @igor-ignore - Boot-time configuration of the stateless DBAL types (no request or user data).
        self::$registry = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - Boot-time configuration of the stateless DBAL types (no request or user data).
        self::$onDecryptFailure = DoctrineEncryptSubscriber::ON_DECRYPT_FAILURE_KEEP; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
        // @igor-ignore - Boot-time configuration of the stateless DBAL types (no request or user data).
        self::$logger = null; // @phpstan-ignore frankenphp.worker.noMutableStaticProperty
    }

    public static function isConfigured(): bool
    {
        return self::$registry instanceof EncryptorRegistryInterface;
    }

    /**
     * The encryptor of a profile ("default" = default_profile).
     *
     * @throws LogicException When the bundle has not configured the types (kernel not booted, dbal_types disabled)
     */
    public static function encryptor(string $profile): EncryptorInterface
    {
        if (!self::$registry instanceof EncryptorRegistryInterface) {
            throw new LogicException('The nowo_doctrine_encrypt DBAL types are not configured: enable nowo_doctrine_encrypt.dbal_types and boot the kernel, or call EncryptedTypeRuntime::configure() (plain Doctrine setups).');
        }

        return self::$registry->get($profile);
    }

    public static function onDecryptFailure(): string
    {
        return self::$onDecryptFailure;
    }

    public static function logger(): LoggerInterface
    {
        return self::$logger ?? new NullLogger();
    }
}
