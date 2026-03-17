<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use PHPUnit\Framework\TestCase;

final class MenuLocaleResolverTest extends TestCase
{
    public function testResolveLocaleReturnsRequestLocaleWhenNoLocalesConfigured(): void
    {
        $resolver = new MenuLocaleResolver([]);

        self::assertSame('es', $resolver->resolveLocale('es'));
        self::assertSame('en', $resolver->resolveLocale('en'));
    }

    public function testResolveLocaleUsesRequestLocaleWhenInConfiguredList(): void
    {
        $resolver = new MenuLocaleResolver(['en', 'es'], 'en');

        self::assertSame('en', $resolver->resolveLocale('en'));
        self::assertSame('es', $resolver->resolveLocale('es'));
        self::assertSame('es', $resolver->resolveLocale('  es  '));
    }

    public function testResolveLocaleFallsBackToDefaultWhenRequestNotInList(): void
    {
        $resolver = new MenuLocaleResolver(['en', 'es'], 'es');

        self::assertSame('es', $resolver->resolveLocale('fr'));
        self::assertSame('es', $resolver->resolveLocale(''));
        self::assertSame('es', $resolver->resolveLocale('  '));
    }

    public function testResolveLocaleFallsBackToFirstLocaleWhenNoDefault(): void
    {
        $resolver = new MenuLocaleResolver(['en', 'es']);

        self::assertSame('en', $resolver->resolveLocale('fr'));

        $resolverWithEmptyDefault = new MenuLocaleResolver(['en', 'es'], '');
        self::assertSame('en', $resolverWithEmptyDefault->resolveLocale('fr'));
    }

    public function testGetLocalesReturnsConfiguredList(): void
    {
        $locales  = ['en', 'es'];
        $resolver = new MenuLocaleResolver($locales);

        self::assertSame($locales, $resolver->getLocales());
    }
}
