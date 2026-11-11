<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextCurrentMatcherInterface;

/**
 * DDD adapter of {@see HostMenuPolicy::isCurrent()}.
 */
final class ContextHostMatcher implements MenuItemContextCurrentMatcherInterface
{
    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool
    {
        return HostMenuPolicy::isCurrent($item->routeName, $request->route);
    }
}
