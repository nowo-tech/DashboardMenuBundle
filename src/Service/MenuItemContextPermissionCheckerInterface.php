<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;

/**
 * Item visibility port with bundle-owned value objects only: the item as {@see MenuItemView} and the context as
 * {@see MenuPermissionContext} (instead of `mixed`).
 *
 * Replaces {@see MenuPermissionCheckerInterface} and {@see MenuItemViewPermissionCheckerInterface}: services
 * implementing it are auto-tagged and offered in the dashboard "Permission checker" dropdown the same way (label from
 * DASHBOARD_LABEL or #[PermissionCheckerLabel]). When a class still implements one of the deprecated variants, the
 * bundle keeps calling that one (entity variant first).
 *
 * Return false for permission keys you do not know: the bundle shows exactly what the checker allows.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuItemContextPermissionCheckerInterface
{
    /**
     * Whether the given menu item should be visible in this context.
     */
    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool;
}
