<?php

namespace Nowo\DoctrineEncryptBundle\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorInterface;
use Nowo\DoctrineEncryptBundle\Encryptors\EncryptorRegistry;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Subscribers\DoctrineEncryptSubscriber;
use Symfony\Component\Console\Command\Command;

/**
 * Base command containing usefull base methods.
 **/
abstract class AbstractCommand extends Command
{
    /**
     * AbstractCommand constructor.
     *
     * @param EntityManager             $entityManager
     * @param DoctrineEncryptSubscriber  $subscriber
     * @param EncryptorInterface|null    $defaultEncryptor Used by encrypt/decrypt database commands when subscriber has no encryptor (e.g. multi-config).
     * @param EncryptorRegistry|null     $encryptorRegistry Used by encrypt/decrypt database commands to resolve config names and encryptors.
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
     * The function returns an iterable object containing entities of a specified type.
     *
     * @param string entityName The `entityName` parameter is a string that represents the name of the
     * entity class you want to retrieve data from.
     *
     * @return iterable an iterable object.
     */
    protected function getEntityIterator(string $entityName): iterable
    {
        $query = $this->entityManager->createQuery(sprintf('SELECT o FROM %s o', $entityName));
        return $query->toIterable();
    }


    /**
     * The function returns the count of records in a database table for a given entity name.
     *
     * @param string entityName The parameter `` is a string that represents the name of the
     * entity for which you want to retrieve the count of records in its corresponding database table.
     *
     * @return int The method is returning an integer value, which is the count of records in the specified
     * entity table.
     */
    protected function getTableCount(string $entityName): int
    {
        $query = $this->entityManager->createQuery(sprintf('SELECT COUNT(o) FROM %s o', $entityName));

        return (int) $query->getSingleScalarResult();
    }


    /**
     * The function retrieves the metadata of entities that have properties eligible for encryption.
     *
     * @return array an array of valid entity metadata.
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
     * The function "getEncryptionableProperties" returns an array of properties that are marked with the
     * "Encrypted" annotation.
     *
     * @param $entityMetaData, the  parameter is an object that contains metadata information
     * about an entity in a Doctrine ORM (Object-Relational Mapping) system. It typically includes details
     * such as the entity's name, table name, column names, associations, and other mapping information.
     *
     * @return array an array of properties that are marked with the `Encrypted` annotation.
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
     * Returns entity metadata that have at least one Encrypted property for the given config.
     *
     * @param string $configName        Config name (e.g. personal_data)
     * @param string $defaultConfigName Registry default config name
     *
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
