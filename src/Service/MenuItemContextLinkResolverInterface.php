<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;

/**
 * Link resolver port (item type "service") with bundle-owned value objects only: the item as {@see MenuItemView}, the
 * request as {@see MenuRequestContext} and the permission context as {@see MenuPermissionContext}.
 *
 * Replaces {@see MenuLinkResolverInterface} and {@see MenuItemViewLinkResolverInterface}: services implementing it are
 * auto-tagged and offered in the "Link resolver" dropdown the same way, and the return value has the same meaning (one
 * URL, or a list of child link rows). When a class still implements one of the deprecated variants, the bundle keeps
 * calling that one (entity variant first).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuItemContextLinkResolverInterface
{
    /**
     * @param MenuRequestContext|null $request the request the menu is rendered for, or null outside a request
     * @param MenuPermissionContext $context the same context the permission checker receives
     *
     * @return list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}>|string
     */
    public function resolveItemHrefFor(MenuItemView $item, ?MenuRequestContext $request, MenuPermissionContext $context): string|array;
}
