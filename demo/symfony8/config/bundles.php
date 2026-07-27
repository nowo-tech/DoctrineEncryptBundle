<?php

declare(strict_types=1);
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;

return [
    FrameworkBundle::class           => ['all' => true],
    DoctrineBundle::class            => ['all' => true],
    TwigBundle::class                => ['all' => true],
    NowoDoctrineEncryptBundle::class => ['all' => true],
    DoctrineFixturesBundle::class    => ['dev' => true, 'test' => true],
    DebugBundle::class               => ['dev' => true],
    WebProfilerBundle::class         => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class   => ['dev' => true, 'test' => true],
];
