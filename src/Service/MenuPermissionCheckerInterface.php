<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Customizable permission check for menu items (e.g. by role, feature flag, or custom logic).
 *
 * Any service whose class implements this interface is automatically included in the dashboard
 * "Permission checker" dropdown (no need to add the tag in services.yaml). Optionally set the
 * dropdown label via the class constant DASHBOARD_LABEL or the attribute #[PermissionCheckerLabel('...')].
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
