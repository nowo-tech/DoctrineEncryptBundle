<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Nowo\DoctrineEncryptBundle\NowoDoctrineEncryptBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class           => ['all' => true],
    DoctrineBundle::class            => ['all' => true],
    TwigBundle::class                => ['all' => true],
    NowoDoctrineEncryptBundle::class => ['all' => true],
    DoctrineFixturesBundle::class    => ['dev' => true, 'test' => true],
    DebugBundle::class               => ['dev' => true],
    WebProfilerBundle::class         => ['dev' => true, 'test' => true],
    NowoHotReloadBundle::class       => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class   => ['dev' => true, 'test' => true],
    TwigExtraBundle::class           => ['all' => true],
    NowoFormKitBundle::class         => ['all' => true],
    NowoUiKitBundle::class           => ['all' => true],
];
