<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuArea;

/**
 * Decides whether the current user may see an area (2.22): the area switcher lists only granted areas
 * (`dashboard_menu_areas()`), and `dashboard_menu_area_granted()` asks the same question.
 *
 * The default implementation, {@see RoleMenuAreaAccessChecker}, grants an area when any of its `roles` is granted by
 * Symfony Security (roles or voter attributes, with the {@see MenuArea} as subject). Point
 * `nowo_dashboard_menu.area_access_checker` at your own service to decide otherwise (a policy port of a DDD host).
 *
 * Implementations must not keep per-request state in properties (FrankenPHP worker mode).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuAreaAccessCheckerInterface
{
    public function canAccessArea(MenuArea $area): bool;
}
