<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Mapping;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Embedded;
use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Doctrine\Types\AbstractEncryptedType;
use ReflectionClass;
use ReflectionProperty;

use function array_slice;
use function explode;
use function implode;
use function is_string;
use function ksort;

/**
 * Which mapped fields the listener encrypts, seen from Doctrine metadata: the same selection as
 * DoctrineEncryptSubscriber (#[Encrypted] or encrypted_fields; embeddables followed through #[ORM\Embedded] or the
 * "embedded" list), resolved to field names such as "address.street" so their columns can be found.
 *
 * Used by the raw SQL commands (so embedded fields are rotated too) and by the mapping validation.
 */
final class ListenerFieldResolver
{
    /** @var array<string, array<string, string>> */
    private array $properties = []; // @igor-ignore - Per-class reflection cache (immutable class metadata).

    /** @var array<string, array<string, true>> */
    private array $followed = []; // @igor-ignore - Per-class reflection cache (immutable class metadata).

    public function __construct(private readonly EncryptedFieldMap $fieldMap = new EncryptedFieldMap())
    {
    }

    /**
     * Properties the listener encrypts on objects of this class (declared or inherited), with their profile alias as
     * written ("default" = default_profile).
     *
     * @return array<string, string>
     */
    public function properties(string $className): array
    {
        if (!isset($this->properties[$className])) {
            $this->load($className);
        }

        return $this->properties[$className];
    }

    /**
     * Whether the listener follows this embedded property (#[ORM\Embedded] or encrypted_fields "embedded").
     */
    public function followsEmbedded(string $className, string $property): bool
    {
        if (!isset($this->followed[$className])) {
            $this->load($className);
        }

        return isset($this->followed[$className][$property]);
    }

    /**
     * Listener-encrypted fields inside the embeddables of an entity ("address.street", nested "a.b.c"), with their
     * profile alias as written. Only embeddables the listener actually follows, level by level, are included.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return list<array{field: string, config: string}>
     */
    public function embeddedFields(ClassMetadata $metadata): array
    {
        $embedded = $metadata->embeddedClasses;
        ksort($embedded);

        $fields = [];
        foreach ($embedded as $path => $mapping) {
            $segments = explode('.', (string) $path);
            $owner    = $metadata->name;
            $followed = true;
            foreach ($segments as $i => $segment) {
                if (!$this->followsEmbedded($owner, $segment)) {
                    $followed = false;
                    break;
                }
                $prefix = implode('.', array_slice($segments, 0, $i + 1));
                $owner  = $embedded[$prefix]->class ?? $mapping->class;
            }
            if (!$followed) {
                continue;
            }
            foreach ($this->properties($mapping->class) as $property => $config) {
                $field = $path . '.' . $property;
                if (isset($metadata->fieldMappings[$field])) {
                    $fields[] = ['field' => $field, 'config' => $config];
                }
            }
        }

        return $fields;
    }

    /**
     * Fields the listener encrypts that are mapped with an encrypted DBAL type too (they would be encrypted twice).
     *
     * @param ClassMetadata<object> $metadata
     *
     * @return list<string>
     */
    public function fieldsAlsoMappedWithEncryptedType(ClassMetadata $metadata): array
    {
        $fields = array_keys($this->properties($metadata->name));
        foreach ($this->embeddedFields($metadata) as $embedded) {
            $fields[] = $embedded['field'];
        }

        $doubled = [];
        foreach ($fields as $field) {
            if (!isset($metadata->fieldMappings[$field])) {
                continue;
            }
            $type = $metadata->getTypeOfField($field);
            if (is_string($type) && Type::hasType($type) && Type::getType($type) instanceof AbstractEncryptedType) {
                $doubled[] = $field;
            }
        }

        return $doubled;
    }

    private function load(string $className): void
    {
        $properties = [];
        $followed   = [];
        if (class_exists($className)) {
            foreach ($this->hierarchyProperties(new ReflectionClass($className)) as $name => $property) {
                $attribute = $property->getAttributes(Encrypted::class)[0] ?? null;
                if ($attribute !== null) {
                    $properties[$name] = $attribute->newInstance()->config;
                }
                if ($property->getAttributes(Embedded::class) !== []) {
                    $followed[$name] = true;
                }
            }
        }
        foreach ($this->fieldMap->getProperties($className) as $name => $config) {
            $properties[$name] ??= $config;
        }
        foreach ($this->fieldMap->getEmbedded($className) as $name) {
            $followed[$name] = true;
        }

        // @igor-ignore - Per-class reflection cache (immutable class metadata), safe across requests.
        $this->properties[$className] = $properties;
        // @igor-ignore - Per-class reflection cache (immutable class metadata), safe across requests.
        $this->followed[$className] = $followed;
    }

    /**
     * Properties of the class and its parents; the class's own declaration wins (like the listener).
     *
     * @param ReflectionClass<object> $class
     *
     * @return array<string, ReflectionProperty>
     */
    private function hierarchyProperties(ReflectionClass $class): array
    {
        $parent     = $class->getParentClass();
        $properties = $parent instanceof ReflectionClass ? $this->hierarchyProperties($parent) : [];
        foreach ($class->getProperties() as $property) {
            $properties[$property->getName()] = $property;
        }

        return $properties;
    }
}
