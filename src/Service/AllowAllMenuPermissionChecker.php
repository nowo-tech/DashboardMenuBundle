<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Default permission checker: allows all items (no filtering).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class AllowAllMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'form.menu_type.permission_checker.allow_all';

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }
}
