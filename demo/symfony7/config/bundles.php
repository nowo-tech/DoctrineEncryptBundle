<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class        => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class         => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                  => ['all' => true],
    Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle::class  => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                => ['dev' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class    => ['dev' => true, 'test' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class        => ['dev' => true, 'test' => true],
];
