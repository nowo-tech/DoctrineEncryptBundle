<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use LogicException;
use Nowo\DoctrineEncryptBundle\Mapping\EncryptedFieldMap;
use Nowo\DoctrineEncryptBundle\Mapping\ListenerFieldResolver;

use function implode;
use function sprintf;

/**
 * Checks the encryption selection against Doctrine metadata when a class's metadata is loaded (first use, or
 * cache:warmup with a metadata cache), so mistakes the container build cannot see fail loudly:
 *
 * - an encrypted_fields property that is not a mapped field of that class, or an "embedded" entry that is not an
 *   embedded mapping (the listener would encrypt nothing that reaches the database);
 * - a field selected for the listener (#[Encrypted] or encrypted_fields) that is also mapped with an encrypted DBAL
 *   type (it would be encrypted twice, and the raw SQL commands remove only one layer).
 */
final readonly class EncryptedMappingValidator
{
    private ListenerFieldResolver $resolver;

    public function __construct(private EncryptedFieldMap $fieldMap = new EncryptedFieldMap())
    {
        $this->resolver = new ListenerFieldResolver($fieldMap);
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $metadata = $args->getClassMetadata();
        $entry    = $this->fieldMap->all()[$metadata->name] ?? null;
        if ($entry !== null) {
            foreach (array_keys($entry['properties']) as $property) {
                if (!isset($metadata->fieldMappings[$property])) {
                    throw new LogicException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s::$%s is not a mapped field of that class (an embedded object goes under "embedded", its fields under the embeddable class).', $metadata->name, $property));
                }
            }
            foreach ($entry['embedded'] as $property) {
                if (!isset($metadata->embeddedClasses[$property])) {
                    throw new LogicException(sprintf('nowo_doctrine_encrypt.encrypted_fields: %s::$%s is listed under "embedded" but is not an embedded mapping.', $metadata->name, $property));
                }
            }
        }

        $doubled = $this->resolver->fieldsAlsoMappedWithEncryptedType($metadata);
        if ($doubled !== []) {
            throw new LogicException(sprintf('nowo_doctrine_encrypt: %s field(s) "%s" are selected for the listener (#[Encrypted] or encrypted_fields) and mapped with an encrypted DBAL type: they would be encrypted twice. Use one of the two.', $metadata->name, implode('", "', $doubled)));
        }
    }
}
