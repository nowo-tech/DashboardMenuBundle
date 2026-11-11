<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;

use function is_string;

/**
 * DDD adapter of {@see HostMenuPolicy::projects()}.
 */
final class ContextHostLinkResolver implements MenuItemContextLinkResolverInterface
{
    /**
     * @return list<array{label: string, href: string, position: int}>
     */
    public function resolveItemHrefFor(MenuItemView $item, ?MenuRequestContext $request, MenuPermissionContext $context): array
    {
        $tenant = $request?->routeParam('tenant');

        return HostMenuPolicy::projects(is_string($tenant) ? $tenant : null);
    }
}
