<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Symfony\Component\HttpFoundation\Request;

/**
 * "Current item" rule that receives a scalar {@see MenuItemView} instead of the
 * {@see MenuItem} entity. Alternative to {@see MenuCurrentMatcherInterface}:
 * implementations are tagged {@code nowo_dashboard_menu.current_matcher} automatically (autoconfigure) and
 * ORed with the built-in match by {@see CurrentRouteTreeDecorator}.
 *
 * When a class implements both interfaces, the bundle calls {@see MenuCurrentMatcherInterface::isCurrent()}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuItemViewCurrentMatcherInterface
{
    /**
     * @param string $resolvedHref Absolute-path href already resolved for the item (may be "#" on failure)
     */
    public function isCurrentItem(MenuItemView $item, Request $request, string $resolvedHref): bool;
}
