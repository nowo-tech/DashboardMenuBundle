<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use PHPUnit\Framework\TestCase;

final class MenuIconNameResolverTest extends TestCase
{
    public function testResolveReturnsNullForNull(): void
    {
        $resolver = new MenuIconNameResolver(['bootstrap-icons' => 'bi']);
        self::assertNull($resolver->resolve(null));
    }

    public function testResolveReturnsEmptyForEmptyString(): void
    {
        $resolver = new MenuIconNameResolver(['bootstrap-icons' => 'bi']);
        self::assertSame('', $resolver->resolve(''));
    }

    public function testResolveReturnsIconWhenNoColon(): void
    {
        $resolver = new MenuIconNameResolver(['bootstrap-icons' => 'bi']);
        self::assertSame('house', $resolver->resolve('house'));
    }

    public function testResolveWithLibraryPrefixReplacesPrefix(): void
    {
        $resolver = new MenuIconNameResolver(['bootstrap-icons' => 'bi']);
        self::assertSame('bi:house', $resolver->resolve('bootstrap-icons:house'));
    }

    public function testResolveWithUnderscoreKeyFallback(): void
    {
        $resolver = new MenuIconNameResolver(['bootstrap_icons' => 'bi']);
        self::assertSame('bi:house', $resolver->resolve('bootstrap-icons:house'));
    }

    public function testResolveReturnsIconWhenPrefixNotInMap(): void
    {
        $resolver = new MenuIconNameResolver(['other' => 'o']);
        self::assertSame('unknown:icon', $resolver->resolve('unknown:icon'));
    }
}
