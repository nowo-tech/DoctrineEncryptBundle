<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Subscribers;

// use Doctrine\Common\EventSubscriber;
// events
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
// encryptorInterface
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\Persistence\Mapping\ClassUtils;
use InvalidArgumentException;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Util\EncryptedValue;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
// attributes
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use WeakMap;

use function assert;
use function count;
use function in_array;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Doctrine event listener that encrypts entity properties on persist/update and decrypts on load.
 *
 * Listens to postUpdate, preUpdate, postLoad, onFlush, preFlush, postFlush.
 * Only processes properties marked with the Encrypted attribute; supports multiple encryptor configs via registry.
 *
 * Intentionally non-final: console commands and unit tests collaborate with this listener via doubles
 * (REQ-PHP-001 exception — designed collaboration/extension point for the command layer).
 */
#[AsDoctrineListener(event: Events::postUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::preUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postLoad, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::onFlush, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::preFlush, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, priority: 500, connection: 'default')]
class DoctrineEncryptSubscriber implements ResetInterface /* implements EventSubscriber */
{
    /**
     * Appended to end of encrypted value.
     */
    public const ENCRYPTION_MARKER = '<ENC>';

    /**
     * Encryptor interface namespace.
     */
    public const ENCRYPTOR_INTERFACE_NS = EncryptorInterface::class; // 'Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface';

    /**
     * Encrypted annotation full name.
     */
    public const ENCRYPTED_ANN_NAME = Encrypted::class; // 'Ambta\DoctrineEncryptBundle\Configuration\Encrypted';

    /** on_decrypt_failure: keep the stored ciphertext (marker stripped) in the property and log a warning (2.x default). */
    public const ON_DECRYPT_FAILURE_KEEP = 'keep';

    /** on_decrypt_failure: set the property to null and log a warning (the stored ciphertext is written back unchanged on flush). */
    public const ON_DECRYPT_FAILURE_NULL = 'null';

    /** on_decrypt_failure: throw {@see DecryptionFailedException}. */
    public const ON_DECRYPT_FAILURE_THROW = 'throw';

    public const ON_DECRYPT_FAILURE_MODES = [self::ON_DECRYPT_FAILURE_KEEP, self::ON_DECRYPT_FAILURE_NULL, self::ON_DECRYPT_FAILURE_THROW];

    private ?EncryptorRegistry $registry = null;

    /** When set, use this encryptor (or null to disable); when unset, use registry default. */
    private ?EncryptorInterface $encryptorOverride = null;

    private bool $encryptorOverrideSet = false;

    /**
     * Count amount of decrypted values in this service.
     */
    public int $decryptCounter = 0;

    /**
     * Count amount of encrypted values in this service.
     */
    public int $encryptCounter = 0;

    /**
     * Ciphertext of each decrypted property, per entity, so an unchanged value is written back as-is on flush.
     * Entries disappear with the entity object, so the cache never outlives the identity map.
     *
     * The plaintext is null when the value could not be decrypted and `on_decrypt_failure: null` is configured.
     *
     * @var WeakMap<object, array<string, array{plaintext: string|null, ciphertext: string}>>
     */
    private WeakMap $cachedDecryptions;

    /**
     * Ciphertext written by this listener (or restored from the decryption cache), per entity and property.
     * A value ending with the marker is only trusted without decrypting it when it is one of these.
     *
     * @var WeakMap<object, array<string, string>>
     */
    private WeakMap $knownCiphertexts;

    /**
     * Whether PropertyAccess can read / write a property, per "class::property" (false = reflection fallback).
     *
     * @var array<string, array{read: bool, write: bool}>
     */
    private array $accessModes = []; // @igor-ignore - Per-class accessor capability cache (immutable class metadata), safe across requests.

    /**
     * Encrypted and embedded properties per class (null config = #[Embedded]), resolved once: postLoad runs for every
     * hydrated entity, so reflecting every property and instantiating #[Encrypted] per row was the hot path.
     * Class structure does not change while the process runs, so this is not cleared by reset().
     *
     * @var array<string, list<array{ReflectionProperty, Encrypted|null}>>
     */
    private array $fieldMap = []; // @igor-ignore - Per-class reflection cache (immutable class metadata), safe across requests.

    private ?PropertyAccessorInterface $propertyAccessor = null; // @igor-ignore - Stateless accessor, created once.

    private readonly LoggerInterface $logger;

    /**
     * @param EncryptorInterface|EncryptorRegistry|null $registryOrEncryptor Registry (normal DI), a single encryptor (BC/tests), or null
     * @param LoggerInterface|null $logger Receives a warning for every value that cannot be decrypted (wrong key, corrupted ciphertext)
     * @param string $onDecryptFailure What a property gets when its value cannot be decrypted: "keep" (ciphertext, default), "null" or "throw"
     */
    public function __construct(
        EncryptorRegistry|EncryptorInterface|null $registryOrEncryptor = null,
        ?LoggerInterface $logger = null,
        private readonly string $onDecryptFailure = self::ON_DECRYPT_FAILURE_KEEP,
    ) {
        if (!in_array($onDecryptFailure, self::ON_DECRYPT_FAILURE_MODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid on_decrypt_failure "%s": use one of "%s".', $onDecryptFailure, implode('", "', self::ON_DECRYPT_FAILURE_MODES)));
        }

        $this->cachedDecryptions = new WeakMap();
        $this->knownCiphertexts  = new WeakMap();
        $this->logger            = $logger ?? new NullLogger();

        if ($registryOrEncryptor instanceof EncryptorInterface) {
            $this->registry = new EncryptorRegistry(['default' => $registryOrEncryptor], 'default');
        } elseif ($registryOrEncryptor instanceof EncryptorRegistry) {
            $this->registry = $registryOrEncryptor;
        } else {
            $this->registry = null;
        }
    }

    /*
    public function getSubscribedEvents(): array
    {
      return array(
        Events::postUpdate,
        Events::preUpdate,
        Events::postLoad,
        Events::onFlush,
        Events::preFlush,
        Events::postFlush,
      );
    }
    */

    /**
     * Temporarily overrides the encryptor (used by encrypt/decrypt database commands). Pass null to disable encryption.
     *
     * @param EncryptorInterface|null $encryptor Encryptor to use, or null to disable
     */
    public function setEncryptor(?EncryptorInterface $encryptor = null): void
    {
        $this->encryptorOverride    = $encryptor;
        $this->encryptorOverrideSet = true;
    }

    /**
     * Returns the current encryptor: override if set (including null), otherwise default from registry.
     */
    public function getEncryptor(): ?EncryptorInterface
    {
        if ($this->encryptorOverrideSet) {
            return $this->encryptorOverride;
        }

        return $this->registry?->getDefault();
    }

    /** Restore after override (used by decrypt command). */
    public function restoreEncryptor(): void
    {
        $this->encryptorOverride    = null;
        $this->encryptorOverrideSet = false;
    }

    /**
     * Clear the decryption cache to free memory (e.g. during batch decrypt).
     */
    public function clearDecryptionCache(): void
    {
        $this->cachedDecryptions = new WeakMap();
        $this->knownCiphertexts  = new WeakMap();
    }

    /**
     * Clears the decryption cache, encryptor override and counters (kernel.reset).
     *
     * Assignments must be visible in this method body for Igor IncompleteReset analysis
     * (does not follow clearDecryptionCache() / restoreEncryptor() calls).
     */
    public function reset(): void
    {
        $this->cachedDecryptions    = new WeakMap();
        $this->knownCiphertexts     = new WeakMap();
        $this->encryptorOverride    = null;
        $this->encryptorOverrideSet = false;
        $this->decryptCounter       = 0;
        $this->encryptCounter       = 0;
    }

    /**
     * Listen a postUpdate lifecycle event.
     * Decrypt entities property's values when post updated.
     *
     * So for example after form submit the preUpdate encrypted the entity
     * We have to decrypt them before showing them again.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->processFields($entity, false);
    }

    /**
     * Listen a preUpdate lifecycle event.
     * Encrypt entities property's values on preUpdate, so they will be stored encrypted.
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->processFields($entity);
    }

    /**
     * Listen a postLoad lifecycle event.
     * Decrypt entities property's values when loaded into the entity manger.
     */
    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        $this->processFields($entity, false);
    }

    /**
     * Listen to onflush event
     * Encrypt entities that are inserted into the database.
     */
    public function preFlush(PreFlushEventArgs $preFlushEventArgs): void
    {
        $decryptedClasses = [];
        foreach ($this->cachedDecryptions as $decryptedEntity => $unused) {
            $decryptedClasses[$decryptedEntity::class] = true;
        }

        // Only forget the entities of the entity manager being flushed: the listener instance is shared
        // by every connection it is registered on, and another manager's decrypted entities still need
        // their cached ciphertext (otherwise unchanged secrets are re-encrypted and rewritten).
        $unitOfWOrk = $preFlushEventArgs->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWOrk->getIdentityMap() as $entityName => $entityArray) {
            if (isset($decryptedClasses[$entityName])) {
                foreach ($entityArray as $instance) {
                    $this->processFields($instance);
                    unset($this->cachedDecryptions[$instance]);
                }
            }
        }
    }

    /**
     * Listen to onflush event
     * Encrypt entities that are inserted into the database.
     */
    public function onFlush(OnFlushEventArgs $onFlushEventArgs): void
    {
        $unitOfWork = $onFlushEventArgs->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $encryptCounterBefore = $this->encryptCounter;
            $this->processFields($entity);
            if ($this->encryptCounter > $encryptCounterBefore) {
                $classMetadata = $onFlushEventArgs->getObjectManager()->getClassMetadata($entity::class);
                $unitOfWork->recomputeSingleEntityChangeSet($classMetadata, $entity);
            }
        }
    }

    /**
     * Listen to postFlush event
     * Decrypt entities after having been inserted into the database.
     */
    public function postFlush(PostFlushEventArgs $postFlushEventArgs): void
    {
        $unitOfWork = $postFlushEventArgs->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entityMap) {
            foreach ($entityMap as $entity) {
                $this->processFields($entity, false);
            }
        }
    }

    /**
     * Process (encrypt/decrypt) entities fields.
     *
     * @param object $entity doctrine entity
     * @param bool $isEncryptOperation If true - encrypt, false - decrypt entity
     * @param string|null $configFilter When set, only process properties that use this config (e.g. personal_data). Resolves "default" via registry default.
     */
    public function processFields(object $entity, bool $isEncryptOperation = true, ?string $configFilter = null): ?object
    {
        $encryptor = $this->getEncryptor();
        if (!$encryptor instanceof EncryptorInterface) {
            return $entity;
        }

        $encryptorMethod   = $isEncryptOperation ? 'encrypt' : 'decrypt';
        $realClass         = $this->getRealClass($entity);
        $defaultConfigName = $this->registry?->getDefaultName();

        foreach ($this->fieldMap($realClass) as [$refProperty, $encryptedAttr]) {
            if (!$encryptedAttr instanceof Encrypted) {
                $this->handleEmbeddedAnnotation($entity, $refProperty, $isEncryptOperation, $configFilter);
                continue;
            }

            if ($configFilter !== null && $defaultConfigName !== null) {
                $effectiveConfig = $encryptedAttr->config === 'default' ? $defaultConfigName : $encryptedAttr->config;
                if ($effectiveConfig !== $configFilter) {
                    continue;
                }
            }

            $propertyEncryptor = $this->encryptorOverride instanceof EncryptorInterface || !$this->registry instanceof EncryptorRegistry
                ? $encryptor
                : $this->registry->get($encryptedAttr->config);

            $name  = $refProperty->getName();
            $value = $this->readProperty($entity, $refProperty);
            if ($encryptorMethod === 'decrypt') {
                if (is_string($value) && EncryptedValue::hasMarker($value)) {
                    // Already decrypted in this flush (postUpdate, then postFlush): a plaintext that ends with the
                    // marker must not be decrypted a second time.
                    $alreadyDecrypted = $this->cachedDecryptions[$entity][$name] ?? null;
                    if ($alreadyDecrypted !== null && $alreadyDecrypted['plaintext'] === $value) {
                        continue;
                    }
                    $currentPropValue = $this->decryptValue($entity, $refProperty, $encryptedAttr->config, $propertyEncryptor, $value);
                    $this->writeProperty($entity, $refProperty, $currentPropValue, $encryptedAttr->config);
                    $cached                           = $this->cachedDecryptions[$entity] ?? [];
                    $cached[$name]                    = ['plaintext' => $currentPropValue, 'ciphertext' => $value];
                    $this->cachedDecryptions[$entity] = $cached;
                }

                continue;
            }

            $cached = $this->cachedDecryptions[$entity][$name] ?? null;
            if ($cached !== null && $cached['plaintext'] === ($value === null ? null : (string) $value)) {
                // Unchanged since it was decrypted (or could not be decrypted): write the stored ciphertext back as-is.
                $this->writeProperty($entity, $refProperty, $cached['ciphertext'], $encryptedAttr->config);
                $this->rememberCiphertext($entity, $name, $cached['ciphertext']);
            } elseif ($value !== null && $value !== '' && !$this->isCiphertext($entity, $name, (string) $value, $propertyEncryptor)) {
                ++$this->encryptCounter;
                $currentPropValue = $propertyEncryptor->encrypt((string) $value) . self::ENCRYPTION_MARKER;
                $this->writeProperty($entity, $refProperty, $currentPropValue, $encryptedAttr->config);
                $this->rememberCiphertext($entity, $name, $currentPropValue);
            }
        }

        return $entity;
    }

    /**
     * Decrypts a marked value; on failure applies the on_decrypt_failure policy (keep / null / throw).
     */
    private function decryptValue(object $entity, ReflectionProperty $refProperty, string $config, EncryptorInterface $encryptor, string $value): ?string
    {
        try {
            $plaintext = $encryptor->decrypt(EncryptedValue::payload($value));
            ++$this->decryptCounter;

            return $plaintext;
        } catch (Throwable $e) {
            // Wrong or rotated key, corrupted ciphertext, or (data written before 2.10) a plaintext that ended with <ENC>.
            if ($this->onDecryptFailure === self::ON_DECRYPT_FAILURE_THROW) {
                throw DecryptionFailedException::forProperty($entity::class, $refProperty->getName(), $config, $e);
            }

            $this->logger->warning('Could not decrypt {entity}::${property} (config "{config}"): {exception_class}: {reason}. {outcome}', [
                'entity'          => $entity::class,
                'property'        => $refProperty->getName(),
                'config'          => $config,
                'exception_class' => $e::class,
                'reason'          => $e->getMessage(),
                'outcome'         => $this->onDecryptFailure === self::ON_DECRYPT_FAILURE_NULL ? 'The property is set to null.' : 'The raw value is kept.',
                'exception'       => $e,
            ]);

            return $this->onDecryptFailure === self::ON_DECRYPT_FAILURE_NULL ? null : EncryptedValue::payload($value);
        }
    }

    /**
     * Whether a value about to be written is already ciphertext and must not be encrypted again.
     *
     * The marker alone is not enough (a plaintext may end with "<ENC>"): the value must be ciphertext this listener
     * wrote or restored, or its payload must decrypt with the property encryptor.
     */
    private function isCiphertext(object $entity, string $property, string $value, EncryptorInterface $encryptor): bool
    {
        if (!EncryptedValue::hasMarker($value)) {
            return false;
        }

        if (($this->knownCiphertexts[$entity][$property] ?? null) === $value) {
            return true;
        }

        if (!EncryptedValue::isDecryptable($value, $encryptor)) {
            return false;
        }

        $this->rememberCiphertext($entity, $property, $value);

        return true;
    }

    private function rememberCiphertext(object $entity, string $property, string $ciphertext): void
    {
        $known                           = $this->knownCiphertexts[$entity] ?? [];
        $known[$property]                = $ciphertext;
        $this->knownCiphertexts[$entity] = $known;
    }

    /**
     * Reads a property through PropertyAccess (getter, isser, public property) or, when it is not readable that way
     * (private property without getter), through reflection. An uninitialized typed property reads as null.
     */
    private function readProperty(object $entity, ReflectionProperty $refProperty): mixed
    {
        $pac = $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
        if ($this->accessMode($entity, $refProperty)['read']) {
            return $pac->getValue($entity, $refProperty->getName());
        }

        return $refProperty->isInitialized($entity) ? $refProperty->getValue($entity) : null;
    }

    /**
     * Writes a property through PropertyAccess (one-argument setter, public property) or, when it is not writable that
     * way (private property without setter, setter with more than one required argument), through reflection.
     */
    private function writeProperty(object $entity, ReflectionProperty $refProperty, ?string $value, string $config): void
    {
        $pac = $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();

        try {
            if ($this->accessMode($entity, $refProperty)['write']) {
                $pac->setValue($entity, $refProperty->getName(), $value);

                return;
            }

            $refProperty->setValue($entity, $value);
        } catch (Throwable $e) {
            if ($value !== null) {
                throw $e;
            }

            // on_decrypt_failure: null on a property that does not accept null: fail closed instead of keeping ciphertext.
            throw DecryptionFailedException::forProperty($entity::class, $refProperty->getName(), $config, $e);
        }
    }

    /**
     * @return array{read: bool, write: bool}
     */
    private function accessMode(object $entity, ReflectionProperty $refProperty): array
    {
        $key = $entity::class . '::' . $refProperty->getName();
        if (!isset($this->accessModes[$key])) {
            $pac                     = $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
            $this->accessModes[$key] = [
                'read'  => $pac->isReadable($entity, $refProperty->getName()),
                'write' => $pac->isWritable($entity, $refProperty->getName()),
            ];
        }

        return $this->accessModes[$key];
    }

    /**
     * @param array<ReflectionAttribute<object>> $attributes
     */
    private function getEncryptedAttributeInstance(array $attributes): ?Encrypted
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === self::ENCRYPTED_ANN_NAME) {
                $instance = $attribute->newInstance();

                return $instance instanceof Encrypted ? $instance : null;
            }
        }

        return null;
    }

    private function handleEmbeddedAnnotation(object $entity, ReflectionProperty $embeddedProperty, bool $isEncryptOperation = true, ?string $configFilter = null): void
    {
        $embeddedEntity = $this->readProperty($entity, $embeddedProperty);

        if (is_object($embeddedEntity)) {
            $this->processFields($embeddedEntity, $isEncryptOperation, $configFilter);
        }
    }

    /**
     * The function checks if a given string class exists in an array of attributes.
     *
     * @param array<ReflectionAttribute<object>> $attributes An array of objects representing attributes
     * @param string $stringClass The name of a class to check for in the attributes
     *
     * @return bool a boolean value. It returns true if the given string class is found in the array of
     *              attributes, and false otherwise.
     */
    private function defineAtributeType(array $attributes, string $stringClass): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute->getName() == $stringClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the real class name of an entity (resolves Doctrine proxy classes).
     * Uses Doctrine ClassUtils when available, otherwise get_class() + proxy detection.
     */
    private function getRealClass(object $entity): string
    {
        if (class_exists(ClassUtils::class)) {
            return ClassUtils::getClass($entity);
        }
        if (class_exists(\Doctrine\Common\Util\ClassUtils::class)) {
            return \Doctrine\Common\Util\ClassUtils::getClass($entity);
        }
        $class = $entity::class;
        // Resolve Doctrine proxy to real entity class
        if (str_contains($class, '\\__CG__\\') || str_contains($class, 'Proxies\\')) {
            $parent = get_parent_class($entity);

            return $parent ?: $class;
        }

        return $class;
    }

    /**
     * Recursive function to get an associative array of class properties
     * including inherited ones from extended classes.
     *
     * @param string $className Class name
     *
     * @return array<string, ReflectionProperty>
     */
    /**
     * @return list<array{ReflectionProperty, Encrypted|null}>
     */
    private function fieldMap(string $className): array
    {
        if (isset($this->fieldMap[$className])) {
            return $this->fieldMap[$className];
        }

        $fields = [];
        foreach ($this->getClassProperties($className) as $refProperty) {
            $attributes = $refProperty->getAttributes();
            if ($this->defineAtributeType($attributes, Embedded::class)) {
                $fields[] = [$refProperty, null];
                continue;
            }
            $encryptedAttr = $this->getEncryptedAttributeInstance($attributes);
            if ($encryptedAttr instanceof Encrypted) {
                $fields[] = [$refProperty, $encryptedAttr];
            }
        }

        return $this->fieldMap[$className] = $fields;
    }

    /**
     * @return array<string, ReflectionProperty>
     */
    private function getClassProperties(string $className): array
    {
        assert(class_exists($className));
        $reflectionClass = new ReflectionClass($className);
        $properties      = $reflectionClass->getProperties();
        $propertiesArray = [];

        foreach ($properties as $property) {
            $propertyName                   = $property->getName();
            $propertiesArray[$propertyName] = $property;
        }

        if ($parentClass = $reflectionClass->getParentClass()) {
            $parentPropertiesArray = $this->getClassProperties($parentClass->getName());
            if (count($parentPropertiesArray) > 0) {
                $propertiesArray = array_merge($parentPropertiesArray, $propertiesArray);
            }
        }

        return $propertiesArray;
    }
}
