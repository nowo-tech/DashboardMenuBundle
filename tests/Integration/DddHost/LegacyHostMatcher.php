<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuCurrentMatcherInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * Legacy (deprecated port) adapter of {@see HostMenuPolicy::isCurrent()}.
 */
final class LegacyHostMatcher implements MenuCurrentMatcherInterface
{
    public function isCurrent(MenuItem $item, Request $request, string $resolvedHref): bool
    {
        $route = $request->attributes->get('_route');

        return HostMenuPolicy::isCurrent($item->getRouteName(), is_string($route) ? $route : null);
    }
}
