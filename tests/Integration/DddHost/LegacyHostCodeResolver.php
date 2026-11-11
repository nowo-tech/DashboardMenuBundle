<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * Legacy (deprecated port) adapter of {@see HostMenuPolicy::menuCode()}.
 */
final class LegacyHostCodeResolver implements MenuCodeResolverInterface
{
    public function resolveMenuCode(Request $request, string $hint): string
    {
        $route = $request->attributes->get('_route');

        return HostMenuPolicy::menuCode($hint, is_string($route) ? $route : null);
    }
}
