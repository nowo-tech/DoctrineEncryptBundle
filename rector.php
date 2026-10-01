<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/demo',
        // Rector crash on ObjectShapeType (PHPStanStaticTypeMapper) — keep until upstream fix
        __DIR__ . '/tests/Unit/Command/AbstractCommandTest.php',
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
