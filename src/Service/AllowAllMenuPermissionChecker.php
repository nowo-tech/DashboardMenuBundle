<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;

/**
 * Default permission checker: allows all items (no filtering).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AllowAllMenuPermissionChecker implements MenuItemContextPermissionCheckerInterface, MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'form.menu_type.permission_checker.allow_all';

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return true;
    }

    /**
     * @deprecated since 2.19.0, use {@see canViewItemFor()}; removed in 3.0.0
     */
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}
