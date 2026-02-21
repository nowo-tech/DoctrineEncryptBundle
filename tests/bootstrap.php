<?php

declare(strict_types=1);

$file = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($file)) {
    throw new RuntimeException('Install dependencies using composer to run the test suite.');
}

$autoload = require $file;
if (method_exists(Doctrine\Common\Annotations\AnnotationRegistry::class, 'registerLoader')) {
    Doctrine\Common\Annotations\AnnotationRegistry::registerLoader([$autoload, 'loadClass']);
}
