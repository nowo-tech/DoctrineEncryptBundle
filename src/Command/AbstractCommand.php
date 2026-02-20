<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Symfony\Component\Console\Command\Command;

/**
 * Base class for bundle console commands.
 *
 * Provides shared helpers: entity iteration, encrypted property discovery, and config-filtered metadata.
 */
abstract class AbstractCommand extends Command
{
    /**
     * @param EntityManagerInterface     $entityManager    Doctrine entity manager
     * @param AttributeReader            $attributeReader  Reader for Encrypted attributes
     * @param DoctrineEncryptSubscriber  $subscriber       Encrypt/decrypt event subscriber
     * @param EncryptorInterface|null    $defaultEncryptor Used by encrypt/decrypt database commands when using multi-config
     * @param EncryptorRegistry|null     $encryptorRegistry Used by encrypt/decrypt database commands to resolve config names and encryptors
     */
    public function __construct(
        public EntityManagerInterface $entityManager,
        public AttributeReader $attributeReader,
        public DoctrineEncryptSubscriber $subscriber,
        public ?EncryptorInterface $defaultEncryptor = null,
        public ?EncryptorRegistry $encryptorRegistry = null
    ) {
        parent::__construct();
    }


    /**
     * Returns an iterable over all entities of the given class (for batch processing).
     *
     * @param string $entityName Fully qualified entity class name
     * @return iterable
     */
    protected function getEntityIterator(string $entityName): iterable
    {
        $query = $this->entityManager->createQuery(sprintf('SELECT o FROM %s o', $entityName));
        return $query->toIterable();
    }


    /**
     * Returns the number of records for the given entity.
     *
     * @param string $entityName Fully qualified entity class name
     * @return int
     */
    protected function getTableCount(string $entityName): int
    {
        $query = $this->entityManager->createQuery(sprintf('SELECT COUNT(o) FROM %s o', $entityName));

        return (int) $query->getSingleScalarResult();
    }


    /**
     * Returns metadata for all entities that have at least one property marked with Encrypted.
     *
     * @return array
     */
    protected function getEncryptionableEntityMetaData(): array
    {
        $validMetaData = [];
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metaDataArray as $entityMetaData) {
            if (isset($entityMetaData->isMappedSuperclass) && $entityMetaData->isMappedSuperclass) {
                continue;
            }

            $properties = $this->getEncryptionableProperties($entityMetaData);

            if (count($properties) == 0) {
                continue;
            }

            $validMetaData[] = $entityMetaData;
        }

        return $validMetaData;
    }

    /**
     * Returns properties of the entity that are marked with the Encrypted attribute.
     *
     * @param object $entityMetaData Doctrine entity metadata (ClassMetadata)
     * @return array<\ReflectionProperty>
     */
    protected function getEncryptionableProperties($entityMetaData): array
    {
        // Create reflectionClass for each meta data object
        $reflectionClass = new \ReflectionClass($entityMetaData->name);
        $propertyArray = $reflectionClass->getProperties();
        $properties = [];
        foreach ($propertyArray as $property) {
            if ($this->attributeReader->getPropertyAnnotation($property, Encrypted::class)) {
                $properties[] = $property;
            }
        }
        // return properties
        return $properties;
    }

    /**
     * Returns properties marked with Encrypted that belong to the given config.
     * When a property has config "default", it is included if $defaultConfigName === $configName.
     *
     * @param object $entityMetaData Doctrine entity metadata
     * @param string $configName      Config name (e.g. personal_data, financial_data)
     * @param string $defaultConfigName Registry default config name (for resolving Encrypted('default'))
     *
     * @return array<\ReflectionProperty>
     */
    protected function getEncryptionablePropertiesForConfig($entityMetaData, string $configName, string $defaultConfigName): array
    {
        $reflectionClass = new \ReflectionClass($entityMetaData->name);
        $propertyArray = $reflectionClass->getProperties();
        $properties = [];
        foreach ($propertyArray as $property) {
            $annotation = $this->attributeReader->getPropertyAnnotation($property, Encrypted::class);
            if ($annotation instanceof Encrypted) {
                $effectiveConfig = $annotation->config === 'default' ? $defaultConfigName : $annotation->config;
                if ($effectiveConfig === $configName) {
                    $properties[] = $property;
                }
            }
        }

        return $properties;
    }

    /**
     * Returns metadata for entities that have at least one Encrypted property for the given config.
     *
     * @param string $configName        Config alias (e.g. personal_data)
     * @param string $defaultConfigName Registry default config name (for resolving Encrypted('default'))
     * @return array
     */
    protected function getEncryptionableEntityMetaDataForConfig(string $configName, string $defaultConfigName): array
    {
        $validMetaData = [];
        $metaDataArray = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metaDataArray as $entityMetaData) {
            if (isset($entityMetaData->isMappedSuperclass) && $entityMetaData->isMappedSuperclass) {
                continue;
            }

            $properties = $this->getEncryptionablePropertiesForConfig($entityMetaData, $configName, $defaultConfigName);
            if (count($properties) > 0) {
                $validMetaData[] = $entityMetaData;
            }
        }

        return $validMetaData;
    }
}
