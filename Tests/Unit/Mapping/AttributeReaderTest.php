<?php

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader();
    }

    public function testGetPropertyAnnotationReturnsEncryptedForEncryptedProperty(): void
    {
        $ref = new ReflectionClass(User::class);
        $nameProp = $ref->getProperty('name');

        $annotation = $this->reader->getPropertyAnnotation($nameProp, Encrypted::class);

        $this->assertInstanceOf(Encrypted::class, $annotation);
    }

    public function testGetPropertyAnnotationReturnsNullForNonEncryptedProperty(): void
    {
        $ref = new ReflectionClass(User::class);
        $nameProp = $ref->getProperty('name');

        $annotation = $this->reader->getPropertyAnnotation($nameProp, \stdClass::class);

        $this->assertNull($annotation);
    }

    public function testGetPropertyAnnotationsReturnsEncryptedInstances(): void
    {
        $ref = new ReflectionClass(User::class);
        $nameProp = $ref->getProperty('name');

        $annotations = $this->reader->getPropertyAnnotations($nameProp);

        $this->assertArrayHasKey(Encrypted::class, $annotations);
        $this->assertInstanceOf(Encrypted::class, $annotations[Encrypted::class]);
    }

    public function testGetClassAnnotationReturnsNullWhenClassHasNoMatchingAnnotation(): void
    {
        $ref = new ReflectionClass(User::class);

        $annotation = $this->reader->getClassAnnotation($ref, Encrypted::class);

        $this->assertNull($annotation);
    }

    public function testGetClassAnnotationsReturnsEmptyArrayWhenNoAnnotationSubclasses(): void
    {
        $ref = new ReflectionClass(User::class);

        $annotations = $this->reader->getClassAnnotations($ref);

        $this->assertIsArray($annotations);
        $this->assertEmpty($annotations);
    }

    public function testGetPropertyAnnotationReturnsNullForPropertyWithoutEncrypted(): void
    {
        $ref = new ReflectionClass(PropertyWithoutEncrypted::class);
        $prop = $ref->getProperty('plain');

        $annotation = $this->reader->getPropertyAnnotation($prop, Encrypted::class);

        $this->assertNull($annotation);
    }

    public function testGetPropertyAnnotationsDoesNotContainEncryptedWhenPropertyHasNone(): void
    {
        $ref = new ReflectionClass(PropertyWithoutEncrypted::class);
        $prop = $ref->getProperty('plain');

        $annotations = $this->reader->getPropertyAnnotations($prop);

        $this->assertArrayNotHasKey(Encrypted::class, $annotations);
    }
}

/** @internal */
class PropertyWithoutEncrypted
{
    public string $plain = '';
}
