<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Mapping;

use function array_values;
use function class_exists;
use function class_parents;
use function in_array;
use function ltrim;

/**
 * Encrypted fields selected in the bundle configuration (`nowo_doctrine_encrypt.encrypted_fields`) instead of the
 * `#[Encrypted]` attribute, so an entity or embeddable mapped in XML/YAML (no bundle import in the class) can be
 * encrypted by the listener.
 *
 * Entries of a parent class apply to its subclasses (entity inheritance). The map is built at compile time and never
 * changes while the process runs.
 */
final readonly class EncryptedFieldMap
{
    /**
     * @var array<string, array{properties: array<string, string>, embedded: list<string>}>
     */
    private array $classes;

    /**
     * @param array<string, array{properties?: array<string, string|null>, embedded?: list<string>}> $classes class FQCN => {properties: property => profile alias (null = default_profile), embedded: list of embedded property names}
     */
    public function __construct(array $classes = [])
    {
        $normalized = [];
        foreach ($classes as $class => $entry) {
            $properties = [];
            foreach ($entry['properties'] ?? [] as $property => $profile) {
                $properties[(string) $property] = $profile ?? 'default';
            }
            $normalized[ltrim((string) $class, '\\')] = [
                'properties' => $properties,
                'embedded'   => array_values($entry['embedded'] ?? []),
            ];
        }

        $this->classes = $normalized;
    }

    /**
     * Whether no field is selected through configuration.
     */
    public function isEmpty(): bool
    {
        return $this->classes === [];
    }

    /**
     * Encrypted properties configured for the class and its parent classes (property => profile alias; "default"
     * means default_profile). The class's own entries win over its parents'.
     *
     * @return array<string, string>
     */
    public function getProperties(string $className): array
    {
        $properties = [];
        foreach ($this->hierarchy($className) as $class) {
            $properties = ($this->classes[$class]['properties'] ?? []) + $properties;
        }

        return $properties;
    }

    /**
     * Embedded properties configured for the class and its parent classes: the listener follows them like
     * properties carrying `#[ORM\Embedded]`.
     *
     * @return list<string>
     */
    public function getEmbedded(string $className): array
    {
        $embedded = [];
        foreach ($this->hierarchy($className) as $class) {
            foreach ($this->classes[$class]['embedded'] ?? [] as $property) {
                if (!in_array($property, $embedded, true)) {
                    $embedded[] = $property;
                }
            }
        }

        return $embedded;
    }

    /**
     * All configured entries, keyed by class.
     *
     * @return array<string, array{properties: array<string, string>, embedded: list<string>}>
     */
    public function all(): array
    {
        return $this->classes;
    }

    /**
     * The class followed by its parents, from the root parent down (so the class's own entries are applied last).
     *
     * @return list<string>
     */
    private function hierarchy(string $className): array
    {
        if ($this->classes === []) {
            return [];
        }

        $className = ltrim($className, '\\');
        $parents   = class_exists($className) ? class_parents($className) : [];

        return [...array_reverse(array_values($parents === false ? [] : $parents)), $className];
    }
}
