<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;

/**
 * "Current item" rule with bundle-owned value objects only. Implementations are tagged
 * {@code nowo_dashboard_menu.current_matcher} automatically (autoconfigure) and ORed with the built-in path + query
 * match by {@see CurrentRouteTreeDecorator}.
 *
 * Replaces {@see MenuCurrentMatcherInterface} and {@see MenuItemViewCurrentMatcherInterface}. When a class still
 * implements one of the deprecated variants, the bundle keeps calling that one (entity variant first).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuItemContextCurrentMatcherInterface
{
    /**
     * Whether the given menu item should be marked current for this request.
     *
     * @param MenuRequestContext $request the request the menu is rendered for
     * @param string $resolvedHref Absolute-path href already resolved for the item (may be "#" on failure)
     */
    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool;
}
