<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;

/**
 * DDD adapter of {@see HostMenuPolicy}: bundle-owned value objects only.
 */
final class ContextHostChecker implements MenuItemContextPermissionCheckerInterface
{
    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return HostMenuPolicy::allows($item->permissionKeys, $item->unanimous, $context->request?->route);
    }
}
