<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DefaultMenuCodeResolverTest extends TestCase
{
    public function testResolveMenuCodeReturnsHintUnchanged(): void
    {
        $resolver = new DefaultMenuCodeResolver();
        $request  = new Request();

        self::assertSame('sidebar', $resolver->resolveMenuCode($request, 'sidebar'));
        self::assertSame('topbar', $resolver->resolveMenuCode($request, 'topbar'));
    }
}
