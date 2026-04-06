<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the href for a menu item with itemType "service". Implement in the host application
 * (e.g. query products/courses and return a route URL). Register the implementation as a service
 * and list its service id in {@see Configuration} `menu_link_resolver_choices` or rely on auto-tagging.
 *
 * You may return either a single URL string or a list of child link rows; the latter are merged with
 * persisted children and ordered by `position` (see {@see MenuTreeLoader}).
 *
 * Optional: public const string DASHBOARD_LABEL = '…' for the dashboard dropdown label,
 * or use {@see \Nowo\DashboardMenuBundle\Attribute\MenuLinkResolverLabel}.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuLinkResolverInterface
{
    /**
     * @return string|list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}>
     *
     * @param mixed $permissionContext Same value passed to the menu tree loader (often the current Request).
     */
    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array;
}
