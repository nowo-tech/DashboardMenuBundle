<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;

/**
 * Item visibility port that receives a scalar {@see MenuItemView} instead of the {@see MenuItem}
 * entity. Alternative to {@see MenuPermissionCheckerInterface}: services implementing it are auto-tagged and offered
 * in the dashboard "Permission checker" dropdown the same way (label from DASHBOARD_LABEL or #[PermissionCheckerLabel]).
 *
 * When a class implements both interfaces, the bundle calls {@see MenuPermissionCheckerInterface::canView()}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuItemViewPermissionCheckerInterface
{
    /**
     * Whether the given menu item should be visible in the current context.
     *
     * @param mixed $context Optional context (e.g. current user, request); same value as for {@see MenuPermissionCheckerInterface}
     */
    public function canViewItem(MenuItemView $item, mixed $context = null): bool;
}
