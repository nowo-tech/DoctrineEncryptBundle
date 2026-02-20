<?php

namespace Nowo\DoctrineEncryptBundle\Mapping;

use Attribute;
use Nowo\DoctrineEncryptBundle\Configuration\Annotation;
use ReflectionClass;

/**
 * Reads PHP 8 attributes from classes and properties, restricted to bundle annotation types.
 *
 * Only attributes implementing Annotation (e.g. Encrypted) are returned; other attributes are ignored.
 */
final class AttributeReader
{
    /** @var array<string, bool> Cache of attribute class name => is repeatable */
    private array $isRepeatableAttribute = [];

    /**
     * The function `getClassAnnotations` takes a `ReflectionClass` object as input and returns an array of
     * attribute instances.
     *
     * @param ReflectionClass $class The parameter is an instance of the `ReflectionClass` class.
     * It represents a class and provides methods to retrieve information about the class, such as its
     * name, methods, properties, and annotations.
     *
     * @return array an array of attribute instances.
     */
    public function getClassAnnotations(ReflectionClass $class): array
    {
        return $this->convertToAttributeInstances($class->getAttributes());
    }

    /**
     * The function `getClassAnnotation` retrieves a specific class annotation by name, or returns null if
     * it doesn't exist.
     *
     * @param ReflectionClass $class The parameter is an instance of the ReflectionClass class. It
     * represents a class and provides methods to retrieve information about the class, such as its name,
     * methods, properties, and annotations.
     * @param string $annotationName The parameter is a string that represents the name of
     * the annotation you want to retrieve from the class.
     *
     * @return array|Annotation|null an array, an instance of the Annotation class, or null.
     */
    public function getClassAnnotation(ReflectionClass $class, string $annotationName): array|Annotation|null
    {
        return $this->getClassAnnotations($class)[$annotationName] ?? null;
    }

    /**
     * The function `getPropertyAnnotations` returns an array of attribute instances for a given reflection
     * property.
     *
     * @param \ReflectionProperty $property The parameter is an instance of the
     * `ReflectionProperty` class. It represents a property of a class and provides information about that
     * property, such as its name, visibility, and annotations.
     *
     * @return array an array of property annotations.
     */
    public function getPropertyAnnotations(\ReflectionProperty $property): array
    {
        return $this->convertToAttributeInstances($property->getAttributes());
    }

    /**
     * @phpstan-param class-string $annotationName
     *
     * The function returns the annotation of a given property or null if it doesn't exist.
     *
     * @param \ReflectionProperty property A \ReflectionProperty object representing the property for which
     * you want to retrieve the annotation.
     * @param string annotationName The `annotationName` parameter is a string that represents the name of
     * the annotation you want to retrieve from the property.
     *
     * @return array|Annotation|null an array or an instance of the Annotation class, or null if the
     * specified annotation is not found.
     */
    public function getPropertyAnnotation(\ReflectionProperty $property, string $annotationName): array|Annotation|null
    {
        return $this->getPropertyAnnotations($property)[$annotationName] ?? null;
    }

    /**
     * Converts reflection attributes to instances, filtering to bundle annotation types only.
     *
     * @param array<\ReflectionAttribute> $attributes Reflection attributes from a class or property
     * @return array<string, Annotation|array<Annotation>> Attribute name => instance(s)
     */
    private function convertToAttributeInstances(array $attributes): array
    {
        $instances = [];

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();
            assert(is_string($attributeName));
            // Only process bundle annotation types (e.g. Encrypted)
            if (!is_subclass_of($attributeName, Annotation::class)) {
                continue;
            }

            $instance = $attribute->newInstance();
            assert($instance instanceof Annotation);

            if ($this->isRepeatable($attributeName)) {
                if (!isset($instances[$attributeName])) {
                    $instances[$attributeName] = [];
                }

                $instances[$attributeName][] = $instance;
            } else {
                $instances[$attributeName] = $instance;
            }
        }

        return $instances;
    }

    /**
     * Checks whether the attribute class is repeatable (can appear multiple times on the same target).
     *
     * @param string $attributeClassName Fully qualified attribute class name
     * @return bool
     */
    private function isRepeatable(string $attributeClassName): bool
    {
        if (isset($this->isRepeatableAttribute[$attributeClassName])) {
            return $this->isRepeatableAttribute[$attributeClassName];
        }

        $reflectionClass = new ReflectionClass($attributeClassName);
        $attribute = $reflectionClass->getAttributes()[0]->newInstance();

        return $this->isRepeatableAttribute[$attributeClassName] = ($attribute->flags & Attribute::IS_REPEATABLE) > 0;
    }
}
