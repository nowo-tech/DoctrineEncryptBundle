<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\Php81\Rector\Array_\ArrayToFirstClassCallableRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/demo',
        // Rector crash on ObjectShapeType (PHPStanStaticTypeMapper) — keep until upstream fix
        __DIR__ . '/tests/Unit/Command/AbstractCommandTest.php',
        // The listener writes ciphertext into these fixtures' properties in place: they must stay writable.
        ReadOnlyPropertyRector::class => [
            __DIR__ . '/tests/Functional/DddMapping/fixtures/Domain',
            __DIR__ . '/tests/Unit/Subscribers/fixtures',
        ],
        ReadOnlyClassRector::class => [
            __DIR__ . '/tests/Functional/DddMapping/fixtures/Domain',
            __DIR__ . '/tests/Unit/Subscribers/fixtures',
        ],
        // `is_callable([$configuration, 'getTypeProvider'])` is a runtime check that the method exists (DBAL 4 only; the
        // bundle also runs on DBAL 3). `$configuration->getTypeProvider(...)` would throw an Error on DBAL 3 instead.
        ArrayToFirstClassCallableRector::class => [
            __DIR__ . '/src/Doctrine/Types/DbalTypeLookup.php',
            __DIR__ . '/tests/Support/DbalTypeFixtures.php',
        ],
        RemoveUnusedVariableAssignRector::class => [
            __DIR__ . '/tests/Functional/BasicQueryTest/AbstractBasicQueryTestCase.php',
        ],
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
