<?php

namespace Nowo\DoctrineEncryptBundle\Subscribers;

// use Doctrine\Common\EventSubscriber;
// events
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
//
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
// encryptorInterface
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use ReflectionClass;
use ReflectionProperty;
// attributes
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Doctrine event subscriber which encrypt/decrypt entities
 */
#[AsDoctrineListener(event: Events::postUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::preUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postLoad, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::onFlush, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::preFlush, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postFlush, priority: 500, connection: 'default')]
class DoctrineEncryptSubscriber /*implements EventSubscriber*/
{
    /**
     * Appended to end of encrypted value
     */
    public const ENCRYPTION_MARKER = '<ENC>';

    /**
     * Encryptor interface namespace
     */
    public const ENCRYPTOR_INTERFACE_NS = EncryptorInterface::class;// 'Ambta\DoctrineEncryptBundle\Encryptors\EncryptorInterface';

    /**
     * Encrypted annotation full name
     */
    public const ENCRYPTED_ANN_NAME = Encrypted::class;// 'Ambta\DoctrineEncryptBundle\Configuration\Encrypted';

    private ?EncryptorRegistry $registry = null;

    /** When set, use this encryptor (or null to disable); when unset, use registry default. */
    private ?EncryptorInterface $encryptorOverride = null;

    private bool $encryptorOverrideSet = false;

    /** Used for restoring after override. */
    private ?EncryptorInterface $restoreEncryptor = null;

    /**
     * Count amount of decrypted values in this service
     * @var integer
     */
    public int $decryptCounter = 0;

    /**
     * Count amount of encrypted values in this service
     * @var integer
     */
    public int $encryptCounter = 0;

    /** @var array */
    private array $cachedDecryptions = [];

    /**
     * @param EncryptorRegistry|EncryptorInterface|null $registryOrEncryptor Registry (normal DI), a single encryptor (BC/tests), or null.
     */
    public function __construct(EncryptorRegistry|EncryptorInterface|null $registryOrEncryptor = null)
    {
        if ($registryOrEncryptor instanceof EncryptorInterface) {
            $this->registry = new EncryptorRegistry(['default' => $registryOrEncryptor], 'default');
        } elseif ($registryOrEncryptor instanceof EncryptorRegistry) {
            $this->registry = $registryOrEncryptor;
        } else {
            $this->registry = null;
        }
        if ($this->registry !== null) {
            $this->restoreEncryptor = $this->registry->getDefault();
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

    /** Temporarily override encryptor (used by encrypt/decrypt database commands). Pass null to disable encryption. */
    public function setEncryptor(?EncryptorInterface $encryptor = null): void
    {
        $this->encryptorOverride = $encryptor;
        $this->encryptorOverrideSet = true;
    }

    /** Current encryptor: override if set (including null), otherwise default from registry. */
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
        $this->encryptorOverride = null;
        $this->encryptorOverrideSet = false;
    }

    /**
     * Listen a postUpdate lifecycle event.
     * Decrypt entities property's values when post updated.
     *
     * So for example after form submit the preUpdate encrypted the entity
     * We have to decrypt them before showing them again.
     *
     * @param PostUpdateEventArgs $args
     */
    public function postUpdate(PostUpdateEventArgs $args)
    {
        $entity = $args->getObject();
        $this->processFields($entity, false);
    }

    /**
     * Listen a preUpdate lifecycle event.
     * Encrypt entities property's values on preUpdate, so they will be stored encrypted
     *
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getObject();
        $this->processFields($entity);
    }

    /**
     * Listen a postLoad lifecycle event.
     * Decrypt entities property's values when loaded into the entity manger
     *
     * @param LifecycleEventArgs $args
     */
    public function postLoad(PostLoadEventArgs $args)
    {
        $entity = $args->getObject();
        $this->processFields($entity, false);
    }

    /**
     * Listen to onflush event
     * Encrypt entities that are inserted into the database
     *
     * @param PreFlushEventArgs $preFlushEventArgs
     */
    public function preFlush(PreFlushEventArgs $preFlushEventArgs)
    {
        $unitOfWOrk = $preFlushEventArgs->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWOrk->getIdentityMap() as $entityName => $entityArray) {
            if (isset($this->cachedDecryptions[$entityName])) {
                foreach ($entityArray as $entityId => $instance) {
                    $this->processFields($instance);
                }
            }
        }
        $this->cachedDecryptions = [];
    }

    /**
     * Listen to onflush event
     * Encrypt entities that are inserted into the database
     *
     * @param OnFlushEventArgs $onFlushEventArgs
     */
    public function onFlush(OnFlushEventArgs $onFlushEventArgs)
    {
        $unitOfWork = $onFlushEventArgs->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            $encryptCounterBefore = $this->encryptCounter;
            $this->processFields($entity);
            if ($this->encryptCounter > $encryptCounterBefore) {
                $classMetadata = $onFlushEventArgs->getObjectManager()->getClassMetadata(get_class($entity));
                $unitOfWork->recomputeSingleEntityChangeSet($classMetadata, $entity);
            }
        }
    }

    /**
     * Listen to postFlush event
     * Decrypt entities after having been inserted into the database
     *
     * @param PostFlushEventArgs $postFlushEventArgs
     */
    public function postFlush(PostFlushEventArgs $postFlushEventArgs)
    {
        $unitOfWork = $postFlushEventArgs->getObjectManager()->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap() as $entityMap) {
            foreach ($entityMap as $entity) {
                $this->processFields($entity, false);
            }
        }
    }

    /**
     * Process (encrypt/decrypt) entities fields
     *
     * @param Object $entity doctrine entity
     * @param Boolean $isEncryptOperation If true - encrypt, false - decrypt entity
     *
     * @return object|null
     *@throws \RuntimeException
     *
     */
    public function processFields(object $entity, bool $isEncryptOperation = true): ?object
    {
        $encryptor = $this->getEncryptor();
        if ($encryptor === null) {
            return $entity;
        }

        $encryptorMethod = $isEncryptOperation ? 'encrypt' : 'decrypt';
        $realClass = $this->getRealClass($entity);
        $properties = $this->getClassProperties($realClass);

        foreach ($properties as $refProperty) {
            $attributes = $refProperty->getAttributes();
            $isEmbebed = $this->defineAtributeType($attributes, 'Doctrine\ORM\Mapping\Embedded');
            if ($isEmbebed) {
                $this->handleEmbeddedAnnotation($entity, $refProperty, $isEncryptOperation);
                continue;
            }

            $encryptedAttr = $this->getEncryptedAttributeInstance($attributes);
            if ($encryptedAttr === null) {
                continue;
            }

            $propertyEncryptor = $this->encryptorOverride !== null
                ? $encryptor
                : $this->registry->get($encryptedAttr->config);

            $pac = PropertyAccess::createPropertyAccessor();
            $value = $pac->getValue($entity, $refProperty->getName());
            if ($encryptorMethod === 'decrypt') {
                if ($value !== null && $value !== '') {
                    if (str_ends_with($value, self::ENCRYPTION_MARKER)) {
                        $this->decryptCounter++;
                        $currentPropValue = $propertyEncryptor->decrypt(substr($value, 0, -strlen(self::ENCRYPTION_MARKER)));
                        $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                        $this->cachedDecryptions[get_class($entity)][spl_object_id($entity)][$refProperty->getName()][$currentPropValue] = $value;
                    }
                }
            } else {
                if ($value !== null && $value !== '') {
                    if (isset($this->cachedDecryptions[get_class($entity)][spl_object_id($entity)][$refProperty->getName()][$value])) {
                        $pac->setValue($entity, $refProperty->getName(), $this->cachedDecryptions[get_class($entity)][spl_object_id($entity)][$refProperty->getName()][$value]);
                    } elseif (!str_ends_with($value, self::ENCRYPTION_MARKER)) {
                        $this->encryptCounter++;
                        $currentPropValue = $propertyEncryptor->encrypt($value) . self::ENCRYPTION_MARKER;
                        $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                    }
                }
            }
        }

        return $entity;
    }

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

    private function handleEmbeddedAnnotation($entity, ReflectionProperty $embeddedProperty, bool $isEncryptOperation = true)
    {
        $propName = $embeddedProperty->getName();

        $pac = PropertyAccess::createPropertyAccessor();

        $embeddedEntity = $pac->getValue($entity, $propName);

        if ($embeddedEntity) {
            $this->processFields($embeddedEntity, $isEncryptOperation);
        }
    }


    /**
     * The function checks if a given string class exists in an array of attributes.
     *
     * @param array attributes An array of objects representing attributes.
     * @param string stringClass The parameter `` is a string that represents the name of a
     * class.
     *
     * @return bool a boolean value. It returns true if the given string class is found in the array of
     * attributes, and false otherwise.
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
        if (class_exists(\Doctrine\Persistence\Mapping\ClassUtils::class)) {
            return \Doctrine\Persistence\Mapping\ClassUtils::getClass($entity);
        }
        if (class_exists(\Doctrine\Common\Util\ClassUtils::class)) {
            return \Doctrine\Common\Util\ClassUtils::getClass($entity);
        }
        $class = get_class($entity);
        // Resolve Doctrine proxy to real entity class
        if (str_contains($class, '\\__CG__\\') || str_contains($class, 'Proxies\\')) {
            $parent = get_parent_class($entity);
            return $parent ?: $class;
        }
        return $class;
    }

    /**
     * Recursive function to get an associative array of class properties
     * including inherited ones from extended classes
     *
     * @param string $className Class name
     *
     * @return array
     */
    private function getClassProperties(string $className): array
    {
        $reflectionClass = new ReflectionClass($className);
        $properties = $reflectionClass->getProperties();
        $propertiesArray = [];

        foreach ($properties as $property) {
            $propertyName = $property->getName();
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
