<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Subscribers;

// use Doctrine\Common\EventSubscriber;
// events
use Closure;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
// encryptorInterface
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Embedded;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\ClassUtils;
use InvalidArgumentException;
use LogicException;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistryInterface;
use Nowo\DoctrineEncryptBundle\Exception\DecryptionFailedException;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
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

use function array_key_exists;
use function assert;
use function count;
use function in_array;
use function is_object;
use function is_string;
use function spl_object_id;
use function sprintf;

/**
 * Doctrine event listener that encrypts entity properties on persist/update and decrypts on load.
 *
 * Listens to postUpdate, preUpdate, postLoad, onFlush, preFlush, postFlush and prePersist (only during a flush).
 * Only processes properties marked with the Encrypted attribute or listed in the `encrypted_fields` configuration
 * ({@see EncryptedFieldMap}); supports multiple encryptor configs via registry.
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
#[AsDoctrineListener(event: Events::prePersist, priority: 500, connection: 'default')]
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

    /** property_access: getter/setter through PropertyAccess when there is one, reflection otherwise (default). */
    public const PROPERTY_ACCESS_AUTO = 'auto';

    /** property_access: always reflection, so domain getters/setters never see ciphertext during a flush. */
    public const PROPERTY_ACCESS_REFLECTION = 'reflection';

    public const PROPERTY_ACCESS_MODES = [self::PROPERTY_ACCESS_AUTO, self::PROPERTY_ACCESS_REFLECTION];

    private ?EncryptorRegistryInterface $registry = null;

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
     * Class structure does not change while the process runs, so keeping it across requests is safe (FrankenPHP worker
     * without the services resetter); reset() still drops it, and it is rebuilt once per class.
     *
     * @var array<string, list<array{ReflectionProperty, Encrypted|null}>>
     */
    private array $fieldMap = []; // @igor-ignore - Per-class reflection cache (immutable class metadata), safe across requests.

    private ?PropertyAccessorInterface $propertyAccessor = null; // @igor-ignore - Stateless accessor, created once.

    /**
     * Values this listener swapped in the properties during the current event, so the unit of work's original data
     * can follow them ({@see syncOriginalData()}). Null outside an event that collects them; every event restores the
     * previous value, so it never outlives the call.
     *
     * - decrypted: ciphertext => plaintext (postLoad, postUpdate, postFlush);
     * - restored: unchanged plaintext => its stored ciphertext (preFlush), with a callback that encrypts it afresh;
     * - changed: new plaintext => new ciphertext, or a value cleared to null / "" (preFlush), with the stored
     *   ciphertext of the old value.
     *
     * @var list<array{kind: 'changed'|'decrypted'|'restored', property: string, before: mixed, after: mixed, stored: string|null, reencrypt: (Closure(): string)|null}>|null
     */
    private ?array $swappedValues = null; // @igor-ignore - Scoped to one event call (saved and restored around it), reset() clears it.

    /**
     * Entity managers between this listener's onFlush and its postFlush: an entity persisted then (by a host onFlush
     * listener that runs after this one) is encrypted when it is persisted, since the insertions were already
     * encrypted. Entries disappear with the manager.
     *
     * @var WeakMap<object, true>
     */
    private WeakMap $flushingManagers; // @igor-ignore - Set and cleared within one flush (preFlush clears it too), reset() clears it.

    /**
     * Entities prePersist encrypted, per entity manager, so postFlush decrypts them even when the flush did not insert
     * them (persisted from a postUpdate / postPersist listener, or before the next flush after a failed one). Cleared by
     * postFlush and reset(); entries disappear with the manager.
     *
     * @var WeakMap<object, list<object>>
     */
    private WeakMap $encryptedOnPersist; // @igor-ignore - Filled during a flush and drained by its postFlush, reset() clears it.

    /**
     * Managed entities this listener decrypted (postLoad, postUpdate, postFlush), whatever their class: an inheritance
     * subclass, an entity whose encrypted fields are all in embeddables, or a proxy. preFlush restores or re-encrypts
     * exactly these. Entries disappear with the entity.
     *
     * @var WeakMap<object, true>
     */
    private WeakMap $decryptedEntities; // @igor-ignore - Entity-keyed weak map, never outlives the identity map; reset() clears it.

    private readonly LoggerInterface $logger;

    private readonly EncryptedFieldMap $encryptedFields;

    /**
     * @param EncryptorInterface|EncryptorRegistryInterface|null $registryOrEncryptor Registry (normal DI), a single encryptor (BC/tests), or null
     * @param LoggerInterface|null $logger Receives a warning for every value that cannot be decrypted (wrong key, corrupted ciphertext)
     * @param string $onDecryptFailure What a property gets when its value cannot be decrypted: "keep" (ciphertext, default), "null" or "throw"
     * @param EncryptedFieldMap|null $encryptedFields Fields selected in the bundle configuration besides #[Encrypted] (null = none)
     * @param string $propertyAccess "auto" (setter/getter when there is one, default) or "reflection" (never call domain accessors)
     */
    public function __construct(
        EncryptorRegistryInterface|EncryptorInterface|null $registryOrEncryptor = null,
        ?LoggerInterface $logger = null,
        private readonly string $onDecryptFailure = self::ON_DECRYPT_FAILURE_KEEP,
        ?EncryptedFieldMap $encryptedFields = null,
        private readonly string $propertyAccess = self::PROPERTY_ACCESS_AUTO,
    ) {
        if (!in_array($onDecryptFailure, self::ON_DECRYPT_FAILURE_MODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid on_decrypt_failure "%s": use one of "%s".', $onDecryptFailure, implode('", "', self::ON_DECRYPT_FAILURE_MODES)));
        }
        if (!in_array($propertyAccess, self::PROPERTY_ACCESS_MODES, true)) {
            throw new InvalidArgumentException(sprintf('Invalid property_access "%s": use one of "%s".', $propertyAccess, implode('", "', self::PROPERTY_ACCESS_MODES)));
        }
        $this->encryptedFields = $encryptedFields ?? new EncryptedFieldMap();

        $this->cachedDecryptions  = new WeakMap();
        $this->knownCiphertexts   = new WeakMap();
        $this->flushingManagers   = new WeakMap();
        $this->encryptedOnPersist = new WeakMap();
        $this->decryptedEntities  = new WeakMap();
        $this->logger             = $logger ?? new NullLogger();

        if ($registryOrEncryptor instanceof EncryptorInterface) {
            $this->registry = new EncryptorRegistry(['default' => $registryOrEncryptor], 'default');
        } elseif ($registryOrEncryptor instanceof EncryptorRegistryInterface) {
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
        $this->decryptedEntities = new WeakMap();
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
        $this->swappedValues        = null;
        $this->flushingManagers     = new WeakMap();
        $this->encryptedOnPersist   = new WeakMap();
        $this->decryptedEntities    = new WeakMap();
        // Class-metadata caches (no entity or request data): dropped too, rebuilt once per class on next use.
        $this->accessModes      = [];
        $this->fieldMap         = [];
        $this->propertyAccessor = null;
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
        $this->processManagedEntity($args->getObjectManager(), $args->getObject(), false);
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
        $this->processManagedEntity($args->getObjectManager(), $args->getObject(), false);
    }

    /**
     * Listen to onflush event
     * Encrypt entities that are inserted into the database.
     */
    public function preFlush(PreFlushEventArgs $preFlushEventArgs): void
    {
        // Only the decrypted entities of the entity manager being flushed: the listener instance is shared by every
        // connection it is registered on, and another manager's decrypted entities still need their cached ciphertext
        // (otherwise unchanged secrets are re-encrypted and rewritten). Entities are matched by identity, not by class:
        // the identity map is keyed by root entity name, while a decrypted entity may be a subclass, a proxy, or have
        // its encrypted fields in an embeddable (cached under the embeddable object).
        $entityManager = $preFlushEventArgs->getObjectManager();
        unset($this->flushingManagers[$entityManager]);
        $unitOfWOrk = $entityManager->getUnitOfWork();
        foreach ($unitOfWOrk->getIdentityMap() as $entityArray) {
            foreach ($entityArray as $instance) {
                if (!isset($this->decryptedEntities[$instance]) && !isset($this->cachedDecryptions[$instance])) {
                    continue;
                }
                // An uninitialized proxy was never decrypted: reading it here would only load it.
                if ($unitOfWOrk->isUninitializedObject($instance)) {
                    continue;
                }
                $this->processManagedEntity($entityManager, $instance, true);
                unset($this->cachedDecryptions[$instance], $this->decryptedEntities[$instance]);
            }
        }
    }

    /**
     * Listen to onflush event
     * Encrypt entities that are inserted into the database.
     */
    public function onFlush(OnFlushEventArgs $onFlushEventArgs): void
    {
        $entityManager                          = $onFlushEventArgs->getObjectManager();
        $this->flushingManagers[$entityManager] = true;
        $unitOfWork                             = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $encryptCounterBefore = $this->encryptCounter;
            $this->processFields($entity);
            if ($this->encryptCounter > $encryptCounterBefore && $unitOfWork->getOriginalEntityData($entity) !== []) {
                $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
            }
        }
    }

    /**
     * Listen to prePersist: an entity persisted after this listener's onFlush (by a host onFlush listener that runs
     * later and calls UnitOfWork::computeChangeSet(), as Doctrine requires) would otherwise be inserted in plaintext.
     * It is encrypted here, before that change set is computed; postFlush decrypts it. Outside that window nothing
     * happens (onFlush encrypts the insertions).
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        if (isset($this->flushingManagers[$entityManager])) {
            $entity = $args->getObject();
            $this->processFields($entity);
            $tracked                                  = $this->encryptedOnPersist[$entityManager] ?? [];
            $tracked[]                                = $entity;
            $this->encryptedOnPersist[$entityManager] = $tracked;
        }
    }

    /**
     * Listen to postFlush event
     * Decrypt entities after having been inserted into the database.
     */
    public function postFlush(PostFlushEventArgs $postFlushEventArgs): void
    {
        $entityManager = $postFlushEventArgs->getObjectManager();
        unset($this->flushingManagers[$entityManager]);
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entityMap) {
            foreach ($entityMap as $entity) {
                if ($unitOfWork->isUninitializedObject($entity)) {
                    continue;
                }
                $this->processManagedEntity($entityManager, $entity, false);
            }
        }

        // Entities encrypted on persist that this flush did not insert (persisted from a postUpdate / postPersist
        // listener, or after a failed flush): they are not in the identity map yet, so decrypt them here. The next
        // flush encrypts them again on insert.
        $tracked = $this->encryptedOnPersist[$entityManager] ?? [];
        unset($this->encryptedOnPersist[$entityManager]);
        foreach ($tracked as $entity) {
            $this->processFields($entity, false);
        }
    }

    /**
     * Encrypts or decrypts a managed entity, then makes the unit of work's original data follow the values this
     * listener swapped, so the change set only shows what the application changed and never holds a plaintext that
     * was not in the database:
     *
     * - after a decryption (postLoad, postUpdate, postFlush) the original data holds the plaintext, so an untouched
     *   decrypted entity has an empty change set, also when UnitOfWork::computeChangeSets() runs outside a flush;
     * - in preFlush, an unchanged value gets its stored ciphertext back and the original data holds that ciphertext,
     *   so no UPDATE is issued for it. A changed (or cleared) value gets the stored ciphertext of the old value as
     *   original data: the change is detected (ciphertexts are randomised) and listeners of the flush see the old
     *   ciphertext, never the old plaintext.
     *
     * Only a field whose original value is exactly the value replaced (and whose current value is exactly the value
     * written) follows a decryption or an unchanged value: anything else (a getter or setter that transforms the
     * value, a value the application changed meanwhile) keeps the unit of work's own data.
     *
     * In preFlush, an entity that already has a change set (UnitOfWork::computeChangeSets() ran outside the flush, or
     * an insertion was computed early) keeps it: the flush's own computation would replace it with only the swapped
     * fields, losing the other changes. Its change set is recomputed here instead, which merges: an entry holding a
     * plaintext for an unchanged encrypted value gets a fresh ciphertext of that value (old value: the stored
     * ciphertext), the other entries stay, and the original data moves to the current values.
     */
    private function processManagedEntity(EntityManagerInterface $entityManager, object $entity, bool $isEncryptOperation): void
    {
        // Saved and restored: decrypting here may initialize a proxy whose postLoad collects its own values.
        $previous            = $this->swappedValues;
        $this->swappedValues = [];

        try {
            $this->processFields($entity, $isEncryptOperation);
            $swapped = $this->collectedSwaps();
        } finally {
            $this->swappedValues = $previous;
        }

        if ($swapped === []) {
            return;
        }

        if (!$isEncryptOperation) {
            $this->decryptedEntities[$entity] = true;
        }

        $unitOfWork = $entityManager->getUnitOfWork();
        if ($unitOfWork->getOriginalEntityData($entity) === []) {
            return;
        }

        $pending = $isEncryptOperation && $unitOfWork->getEntityState($entity) === UnitOfWork::STATE_MANAGED && (
            $unitOfWork->isScheduledForUpdate($entity)
            || $unitOfWork->isScheduledForInsert($entity)
        );

        $this->syncOriginalData($entityManager, $entity, $swapped, $pending ? $unitOfWork->getEntityChangeSet($entity) : []);

        if ($pending) {
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata($entity::class), $entity);
        }
    }

    /**
     * @param list<array{kind: 'changed'|'decrypted'|'restored', property: string, before: mixed, after: mixed, stored: string|null, reencrypt: (Closure(): string)|null}> $swapped
     * @param array<string, mixed> $pendingChangeSet Change set the entity already has (preFlush), by field
     */
    private function syncOriginalData(EntityManagerInterface $entityManager, object $entity, array $swapped, array $pendingChangeSet): void
    {
        $unitOfWork = $entityManager->getUnitOfWork();
        $original   = $unitOfWork->getOriginalEntityData($entity);

        $byProperty = [];
        foreach ($swapped as $swap) {
            $byProperty[$swap['property']][] = $swap;
        }

        $classMetadata = $entityManager->getClassMetadata($entity::class);
        $oid           = spl_object_id($entity);
        // Embedded fields are mapped as "address.street" (originalField "street"), nested ones as "a.b.street".
        foreach ($classMetadata->fieldMappings as $field => $mapping) {
            $property = $mapping->originalField ?? $field;
            if (!isset($byProperty[$property]) || !array_key_exists($field, $original)) {
                continue;
            }

            $current = $classMetadata->getFieldValue($entity, $field);
            foreach ($byProperty[$property] as $swap) {
                if ($current !== $swap['after']) {
                    continue;
                }

                if ($swap['kind'] === 'changed') {
                    if ($swap['stored'] !== null) {
                        $unitOfWork->setOriginalEntityProperty($oid, $field, $swap['stored']);
                    }
                    break;
                }

                if ($swap['kind'] === 'restored' && array_key_exists($field, $pendingChangeSet) && $swap['reencrypt'] instanceof Closure) {
                    // The pending change set holds a plaintext for this field: replace it with a fresh ciphertext.
                    ($swap['reencrypt'])();
                    $unitOfWork->setOriginalEntityProperty($oid, $field, $swap['after']);
                    break;
                }

                if ($original[$field] === $swap['before']) {
                    $unitOfWork->setOriginalEntityProperty($oid, $field, $swap['after']);
                    break;
                }
            }
        }
    }

    /**
     * @return list<array{kind: 'changed'|'decrypted'|'restored', property: string, before: mixed, after: mixed, stored: string|null, reencrypt: (Closure(): string)|null}>
     */
    private function collectedSwaps(): array
    {
        return $this->swappedValues ?? [];
    }

    /**
     * Records a value swapped in a property while an event collects them ({@see processManagedEntity()}).
     */
    /**
     * @param 'changed'|'decrypted'|'restored' $kind
     * @param (Closure(): string)|null $reencrypt
     */
    private function recordSwap(string $kind, string $property, mixed $before, mixed $after, ?string $stored = null, ?Closure $reencrypt = null): void
    {
        if ($this->swappedValues !== null) {
            $this->swappedValues[] = ['kind' => $kind, 'property' => $property, 'before' => $before, 'after' => $after, 'stored' => $stored, 'reencrypt' => $reencrypt];
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

            $propertyEncryptor = $this->encryptorOverride instanceof EncryptorInterface || !$this->registry instanceof EncryptorRegistryInterface
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
                    $this->recordSwap('decrypted', $name, $value, $currentPropValue);
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
                $config = $encryptedAttr->config;
                $this->recordSwap('restored', $name, $value, $cached['ciphertext'], $cached['ciphertext'], function () use ($entity, $refProperty, $config, $propertyEncryptor, $name, $value): string {
                    ++$this->encryptCounter;
                    $fresh = $propertyEncryptor->encrypt((string) $value) . self::ENCRYPTION_MARKER;
                    $this->writeProperty($entity, $refProperty, $fresh, $config);
                    $this->rememberCiphertext($entity, $name, $fresh);

                    return $fresh;
                });
            } elseif ($value !== null && $value !== '' && !$this->isCiphertext($entity, $name, (string) $value, $propertyEncryptor)) {
                ++$this->encryptCounter;
                $currentPropValue = $propertyEncryptor->encrypt((string) $value) . self::ENCRYPTION_MARKER;
                $this->writeProperty($entity, $refProperty, $currentPropValue, $encryptedAttr->config);
                $this->rememberCiphertext($entity, $name, $currentPropValue);
                $this->recordSwap('changed', $name, $value, $currentPropValue, $cached['ciphertext'] ?? null);
            } elseif ($cached !== null && ($value === null || $value === '')) {
                // Cleared: no ciphertext to write, but the old value's ciphertext stays the original data.
                $this->recordSwap('changed', $name, $value, $value, $cached['ciphertext']);
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
        if ($this->propertyAccess === self::PROPERTY_ACCESS_REFLECTION) {
            return ['read' => false, 'write' => false];
        }

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

        return $this->fieldMap[$className] = $this->addConfiguredFields($className, $fields);
    }

    /**
     * Appends the fields selected in the bundle configuration (encrypted_fields) to the attribute-based ones.
     * With no configuration the list is returned unchanged.
     *
     * @param list<array{ReflectionProperty, Encrypted|null}> $fields
     *
     * @return list<array{ReflectionProperty, Encrypted|null}>
     */
    private function addConfiguredFields(string $className, array $fields): array
    {
        if ($this->encryptedFields->isEmpty()) {
            return $fields;
        }

        $configured = $this->encryptedFields->getProperties($className);
        $embedded   = $this->encryptedFields->getEmbedded($className);
        if ($configured === [] && $embedded === []) {
            return $fields;
        }

        $known = [];
        foreach ($fields as [$refProperty]) {
            $known[$refProperty->getName()] = true;
        }

        $properties = $this->getClassProperties($className);
        foreach ($embedded as $name) {
            if (isset($known[$name])) {
                continue;
            }
            $fields[]     = [$this->configuredProperty($properties, $className, $name), null];
            $known[$name] = true;
        }
        foreach ($configured as $name => $profile) {
            if (isset($known[$name])) {
                continue;
            }
            $fields[]     = [$this->configuredProperty($properties, $className, $name), new Encrypted($profile)];
            $known[$name] = true;
        }

        return $fields;
    }

    /**
     * @param array<string, ReflectionProperty> $properties
     */
    private function configuredProperty(array $properties, string $className, string $name): ReflectionProperty
    {
        // Validated at compile time; a class changed without clearing the cache must not silently stay unencrypted.
        return $properties[$name] ?? throw new LogicException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s has no property "%s".', $className, $name));
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
