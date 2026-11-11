<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Symfony\Component\HttpFoundation\Request;

/**
 * Link resolver port (item type "service") that receives a scalar {@see MenuItemView} instead of the
 * {@see MenuItem} entity. Alternative to {@see MenuLinkResolverInterface}: services
 * implementing it are auto-tagged and offered in the "Link resolver" dropdown the same way, and the return value
 * has the same meaning (one URL, or a list of child link rows).
 *
 * When a class implements both interfaces, the bundle calls {@see MenuLinkResolverInterface::resolveHref()}.
 *
 * @deprecated since 2.19.0, implement {@see MenuItemContextLinkResolverInterface} instead (resolveItemHrefFor(MenuItemView, ?MenuRequestContext, MenuPermissionContext), bundle-owned value
 *             objects only, no HttpFoundation type or `mixed` context); removed in 3.0.0
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuItemViewLinkResolverInterface
{
    /**
     * @param mixed $permissionContext same value passed to the menu tree loader (often the current Request)
     *
     * @return list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}>|string
     */
    public function resolveItemHref(MenuItemView $item, ?Request $request, mixed $permissionContext = null): string|array;
}
