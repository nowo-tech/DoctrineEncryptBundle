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
// property access
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
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

    /**
     * Used for restoring the encryptor after changing it
     * @var EncryptorInterface|null
     */
    private ?EncryptorInterface $restoreEncryptor;

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
     * Initialization of subscriber
     *
     * @param EncryptorInterface $encryptor (Optional)  An EncryptorInterface.
     */
    public function __construct(public ?EncryptorInterface $encryptor = null)
    {
        $this->restoreEncryptor = $this->encryptor;
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
     * Change the encryptor
     *
     * @param EncryptorInterface|null $encryptor
     */
    public function setEncryptor(?EncryptorInterface $encryptor = null)
    {
        $this->encryptor = $encryptor;
    }

    /**
     * Get the current encryptor
     *
     * @return EncryptorInterface|null returns the encryptor class or null
     */
    public function getEncryptor(): ?EncryptorInterface
    {
        return $this->encryptor;
    }

    /**
     * Restore encryptor to the one set in the constructor.
     */
    public function restoreEncryptor()
    {
        $this->encryptor = $this->restoreEncryptor;
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
        if (!empty($this->encryptor)) {
            // Check which operation to be used
            $encryptorMethod = $isEncryptOperation ? 'encrypt' : 'decrypt';

            $realClass = $this->getRealClass($entity);

            // Get ReflectionClass of our entity
            $properties = $this->getClassProperties($realClass);

            // Foreach property in the reflection class
            foreach ($properties as $refProperty) {
                $attributes = $refProperty->getAttributes();
                $isEmbebed = $this->defineAtributeType($attributes, 'Doctrine\ORM\Mapping\Embedded');
                if ($isEmbebed) {
                    $this->handleEmbeddedAnnotation($entity, $refProperty, $isEncryptOperation);
                    continue;
                }

                /**
                 * If property is an normal value and contains the Encrypt tag, lets encrypt/decrypt that property
                 */
                $isEncrypetdAnnName = $this->defineAtributeType($attributes, self::ENCRYPTED_ANN_NAME);
                if ($isEncrypetdAnnName) {
                    $pac = PropertyAccess::createPropertyAccessor();
                    $value = $pac->getValue($entity, $refProperty->getName());
                    if ($encryptorMethod == 'decrypt') {
                        if (!is_null($value) and !empty($value)) {
                            if (substr($value, -strlen(self::ENCRYPTION_MARKER)) == self::ENCRYPTION_MARKER) {
                                $this->decryptCounter++;
                                $currentPropValue = $this->encryptor->decrypt(substr($value, 0, -5));
                                $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                                $this->cachedDecryptions[get_class($entity)][spl_object_id($entity)][$refProperty->getName()][$currentPropValue] = $value;
                            }
                        }
                    } else {
                        if (!is_null($value) and !empty($value)) {
                            if (isset($this->cachedDecryptions[get_class($entity)][spl_object_id($entity)][$refProperty->getName()][$value])) {
                                $pac->setValue($entity, $refProperty->getName(), $this->cachedDecryptions[get_class($entity)][spl_object_id($entity)][$refProperty->getName()][$value]);
                            } elseif (substr($value, -strlen(self::ENCRYPTION_MARKER)) != self::ENCRYPTION_MARKER) {
                                $this->encryptCounter++;
                                $currentPropValue = $this->encryptor->encrypt($value) . self::ENCRYPTION_MARKER;
                                $pac->setValue($entity, $refProperty->getName(), $currentPropValue);
                            }
                        }
                    }
                }
            }

            return $entity;
        }

        return $entity;
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
