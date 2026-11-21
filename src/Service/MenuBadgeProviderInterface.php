<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

/**
 * Resolves the number shown as a badge on a sidebar menu item (2.27), e.g. pending questions. Register it with
 * `#[AsMenuBadgeProvider(item: 'customers_questions')]` (autoconfigured) or with the tag
 * `nowo_dashboard_menu.menu_badge_provider` and an `item` attribute. The item key is the route name of the menu item.
 *
 * No number, null or 0 shows no badge. The provider is asked at most once per item per request, only for items that
 * are rendered (not hidden by the menu permissions, not in a collapsed section) and only by the main menu and the
 * footer. An exception it throws is logged and the badge is hidden; the menu still renders.
 *
 * The provider runs for every viewer who can see the item, so the count must be scoped to the current user (or tenant),
 * never a global total: a global count leaks the size of data the viewer may not read.
 *
 * Compute per call and never keep per-request state in properties (FrankenPHP worker mode, kernel reset false): the
 * bundle memoises the result per request itself.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuBadgeProviderInterface
{
    /**
     * @param string $itemKey the key of the item (its route name)
     *
     * @return int|null the count, or null for no badge
     */
    public function badge(string $itemKey): ?int;
}
