<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping;

use Nowo\DoctrineEncryptBundle\Configuration\Encrypted;
use Nowo\DoctrineEncryptBundle\Mapping\AttributeReader;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures\ClassLevelTestAnnotation;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures\ClassWithRepeatableAttribute;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures\ClassWithTestAnnotation;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures\PropertyWithEncryptedAndNonAnnotation;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures\PropertyWithoutEncrypted;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Mapping\fixtures\RepeatableTestAnnotation;
use Nowo\DoctrineEncryptBundle\Tests\Unit\Subscribers\fixtures\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader();
    }

    public function testGetPropertyAnnotationReturnsEncryptedForEncryptedProperty(): void
    {
        $ref      = new ReflectionClass(User::class);
        $nameProp = $ref->getProperty('name');

        $annotation = $this->reader->getPropertyAnnotation($nameProp, Encrypted::class);

        $this->assertInstanceOf(Encrypted::class, $annotation);
    }

    public function testGetPropertyAnnotationReturnsNullForNonEncryptedProperty(): void
    {
        $ref      = new ReflectionClass(User::class);
        $nameProp = $ref->getProperty('name');

        $annotation = $this->reader->getPropertyAnnotation($nameProp, stdClass::class);

        $this->assertNull($annotation);
    }

    public function testGetPropertyAnnotationsReturnsEncryptedInstances(): void
    {
        $ref      = new ReflectionClass(User::class);
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
        $ref  = new ReflectionClass(PropertyWithoutEncrypted::class);
        $prop = $ref->getProperty('plain');

        $annotation = $this->reader->getPropertyAnnotation($prop, Encrypted::class);

        $this->assertNull($annotation);
    }

    public function testGetPropertyAnnotationsDoesNotContainEncryptedWhenPropertyHasNone(): void
    {
        $ref  = new ReflectionClass(PropertyWithoutEncrypted::class);
        $prop = $ref->getProperty('plain');

        $annotations = $this->reader->getPropertyAnnotations($prop);

        $this->assertArrayNotHasKey(Encrypted::class, $annotations);
    }

    public function testGetClassAnnotationReturnsAnnotationWhenClassHasMatchingAnnotation(): void
    {
        $ref = new ReflectionClass(ClassWithTestAnnotation::class);

        $annotation = $this->reader->getClassAnnotation($ref, ClassLevelTestAnnotation::class);

        $this->assertInstanceOf(ClassLevelTestAnnotation::class, $annotation);
    }

    public function testGetClassAnnotationsReturnsClassLevelAnnotations(): void
    {
        $ref = new ReflectionClass(ClassWithTestAnnotation::class);

        $annotations = $this->reader->getClassAnnotations($ref);

        $this->assertArrayHasKey(ClassLevelTestAnnotation::class, $annotations);
        $this->assertInstanceOf(ClassLevelTestAnnotation::class, $annotations[ClassLevelTestAnnotation::class]);
    }

    public function testGetPropertyAnnotationsReturnsArrayForRepeatableAttribute(): void
    {
        $ref  = new ReflectionClass(ClassWithRepeatableAttribute::class);
        $prop = $ref->getProperty('multi');

        $annotations = $this->reader->getPropertyAnnotations($prop);

        $this->assertArrayHasKey(RepeatableTestAnnotation::class, $annotations);
        $this->assertIsArray($annotations[RepeatableTestAnnotation::class]);
        $this->assertCount(2, $annotations[RepeatableTestAnnotation::class]);
        $this->assertInstanceOf(RepeatableTestAnnotation::class, $annotations[RepeatableTestAnnotation::class][0]);
        $this->assertInstanceOf(RepeatableTestAnnotation::class, $annotations[RepeatableTestAnnotation::class][1]);
        $this->assertSame('a', $annotations[RepeatableTestAnnotation::class][0]->value);
        $this->assertSame('b', $annotations[RepeatableTestAnnotation::class][1]->value);
    }

    public function testGetPropertyAnnotationsSkipsNonAnnotationAttributes(): void
    {
        $ref  = new ReflectionClass(PropertyWithEncryptedAndNonAnnotation::class);
        $prop = $ref->getProperty('value');

        $annotations = $this->reader->getPropertyAnnotations($prop);

        $this->assertArrayHasKey(Encrypted::class, $annotations);
        $this->assertInstanceOf(Encrypted::class, $annotations[Encrypted::class]);
        $this->assertCount(1, $annotations);
    }
}
