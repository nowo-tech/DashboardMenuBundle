<?php

declare(strict_types=1);
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\HotReloadBundle\NowoHotReloadBundle;
use Nowo\IconSelectorBundle\NowoIconSelectorBundle;
use Nowo\TwigInspectorBundle\NowoTwigInspectorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Pentatrion\ViteBundle\PentatrionViteBundle;
use Symfony\Bundle\DebugBundle\DebugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class          => ['all' => true],
    SecurityBundle::class           => ['all' => true],
    DoctrineBundle::class           => ['all' => true],
    DoctrineMigrationsBundle::class => ['all' => true],
    DoctrineFixturesBundle::class   => ['dev' => true, 'test' => true],
    TwigBundle::class               => ['all' => true],
    TwigExtraBundle::class          => ['all' => true],
    DebugBundle::class              => ['dev' => true, 'test' => true],
    WebProfilerBundle::class        => ['dev' => true, 'test' => true],
    PentatrionViteBundle::class     => ['all' => true],
    StimulusBundle::class           => ['all' => true],
    NowoDashboardMenuBundle::class  => ['all' => true],
    NowoUiKitBundle::class          => ['all' => true],
    NowoFormKitBundle::class        => ['all' => true],
    NowoHotReloadBundle::class      => ['dev' => true, 'test' => true],
    NowoTwigInspectorBundle::class  => ['dev' => true, 'test' => true],
    UXIconsBundle::class            => ['all' => true],
    NowoIconSelectorBundle::class   => ['all' => true],
    AutocompleteBundle::class       => ['all' => true],
    TwigComponentBundle::class      => ['all' => true],
    LiveComponentBundle::class      => ['all' => true],
];
