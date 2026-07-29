<?php

declare(strict_types=1);
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

// Declare stub classes for optional dependencies not installed as dev-deps.
// AsDoctrineListener is provided by doctrine/doctrine-bundle at runtime; we
// declare it here so PHPStan can resolve the attribute without requiring that
// package as a dev dependency.
if (!class_exists(AsDoctrineListener::class, false)) {
    require __DIR__ . '/stubs/AsDoctrineListener.php';
}
