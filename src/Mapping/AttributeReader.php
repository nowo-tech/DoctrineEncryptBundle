<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Mapping;

use Attribute;
use Nowo\DoctrineEncryptBundle\Configuration\Annotation;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

use function assert;
use function is_array;

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
     * @param ReflectionClass<object> $class the parameter is an instance of the `ReflectionClass` class
     *
     * @return array<string, Annotation|array<Annotation>>
     */
    public function getClassAnnotations(ReflectionClass $class): array
    {
        return $this->convertToAttributeInstances($class->getAttributes());
    }

    /**
     * The function `getClassAnnotation` retrieves a specific class annotation by name, or returns null if
     * it doesn't exist.
     *
     * @param ReflectionClass<object> $class the parameter is an instance of the ReflectionClass class
     * @param string $annotationName the parameter is a string that represents the name of
     *                               the annotation you want to retrieve from the class
     *
     * @return Annotation|array<Annotation>|null an array, an instance of the Annotation class, or null
     */
    public function getClassAnnotation(ReflectionClass $class, string $annotationName): array|Annotation|null
    {
        return $this->getClassAnnotations($class)[$annotationName] ?? null;
    }

    /**
     * The function `getPropertyAnnotations` returns an array of attribute instances for a given reflection
     * property.
     *
     * @param ReflectionProperty $property the parameter is an instance of the `ReflectionProperty` class
     *
     * @return array<string, Annotation|array<Annotation>>
     */
    public function getPropertyAnnotations(ReflectionProperty $property): array
    {
        return $this->convertToAttributeInstances($property->getAttributes());
    }

    /**
     * @param ReflectionProperty $property a \ReflectionProperty object representing the property for which
     *                                     you want to retrieve the annotation
     * @param string $annotationName the name of the annotation you want to retrieve from the property
     *
     * @phpstan-param class-string $annotationName
     *
     * @return Annotation|array<Annotation>|null an array or an instance of the Annotation class, or null if the
     *                                           specified annotation is not found
     */
    public function getPropertyAnnotation(ReflectionProperty $property, string $annotationName): array|Annotation|null
    {
        return $this->getPropertyAnnotations($property)[$annotationName] ?? null;
    }

    /**
     * Converts reflection attributes to instances, filtering to bundle annotation types only.
     *
     * @param array<ReflectionAttribute<object>> $attributes Reflection attributes from a class or property
     *
     * @return array<string, Annotation|array<Annotation>> Attribute name => instance(s)
     */
    private function convertToAttributeInstances(array $attributes): array
    {
        $instances = [];

        foreach ($attributes as $attribute) {
            $attributeName = $attribute->getName();
            // Only process bundle annotation types (e.g. Encrypted)
            if (!is_subclass_of($attributeName, Annotation::class)) {
                continue;
            }

            $instance = $attribute->newInstance();
            assert($instance instanceof Annotation);

            if ($this->isRepeatable($attributeName)) {
                if (!isset($instances[$attributeName]) || !is_array($instances[$attributeName])) {
                    $instances[$attributeName] = [];
                }
                /** @var array<Annotation> $list */
                $list                      = $instances[$attributeName];
                $list[]                    = $instance;
                $instances[$attributeName] = $list;
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
     */
    private function isRepeatable(string $attributeClassName): bool
    {
        if (isset($this->isRepeatableAttribute[$attributeClassName])) {
            return $this->isRepeatableAttribute[$attributeClassName];
        }

        assert(class_exists($attributeClassName));
        $reflectionClass = new ReflectionClass($attributeClassName);
        $attribs         = $reflectionClass->getAttributes(Attribute::class);
        if ($attribs === []) {
            return $this->isRepeatableAttribute[$attributeClassName] = false;
        }
        /** @var Attribute $attrInstance */
        $attrInstance = $attribs[0]->newInstance();

        return $this->isRepeatableAttribute[$attributeClassName] = ($attrInstance->flags & Attribute::IS_REPEATABLE) > 0;
    }
}
