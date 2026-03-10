<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Customizable permission check for menu items (e.g. by role, feature flag, or custom logic).
 *
 * Implement this interface and register your service id in nowo_dashboard_menu.permission_checker
 * to filter which items are visible for the current context.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuPermissionCheckerInterface
{
    /**
     * Whether the given menu item should be visible in the current context.
     *
     * @param mixed $context Optional context (e.g. current user, request); depends on implementation
     */
    public function canView(MenuItem $item, mixed $context = null): bool;
}
