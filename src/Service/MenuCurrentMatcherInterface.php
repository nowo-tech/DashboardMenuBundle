<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Host-defined rule to mark a menu item current beyond the default path+query match.
 *
 * Any service implementing this interface is tagged {@code nowo_dashboard_menu.current_matcher}
 * automatically (autoconfigure). {@see CurrentRouteTreeDecorator} ORs each matcher with the
 * built-in exact path match so index routes can stay lit on child pages (e.g. admin index → section).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuCurrentMatcherInterface
{
    /**
     * Whether the given menu item should be marked current for this request.
     *
     * @param string $resolvedHref Absolute-path href already resolved for the item (may be "#" on failure)
     */
    public function isCurrent(MenuItem $item, Request $request, string $resolvedHref): bool;
}
