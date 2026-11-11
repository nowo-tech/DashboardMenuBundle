<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Symfony\Component\HttpFoundation\Request;

use function is_string;
use function str_starts_with;

/**
 * Marks a sidebar link current when the active Symfony route matches configured prefixes
 * keyed by the menu item's {@see MenuItem::getRouteName()}.
 *
 * Useful when the menu points at an index route that redirects to a {@code *_section} child,
 * or when a whole kit route family should keep one nav item highlighted.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 *
 * @phpstan-type RoutePrefixMap array<string, list<string>>
 */
abstract class AbstractRoutePrefixMenuCurrentMatcher implements MenuItemContextCurrentMatcherInterface, MenuCurrentMatcherInterface
{
    /**
     * @return RoutePrefixMap menu item route name => prefixes matched against the request {@code _route}
     */
    abstract protected function routePrefixes(): array;

    /**
     * Since 2.19.0; subclasses keep implementing only {@see routePrefixes()}.
     */
    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool
    {
        return $this->matchesPrefixes($item->routeName, $request->route);
    }

    /**
     * @deprecated since 2.19.0, use {@see isCurrentItemFor()}; removed in 3.0.0 (the class then implements only
     *             {@see MenuItemContextCurrentMatcherInterface}; subclasses that only define {@see routePrefixes()} need
     *             no change)
     */
    public function isCurrent(MenuItem $item, Request $request, string $resolvedHref): bool
    {
        $currentRoute = $request->attributes->get('_route');

        return $this->matchesPrefixes($item->getRouteName(), is_string($currentRoute) ? $currentRoute : null);
    }

    private function matchesPrefixes(?string $itemRoute, ?string $currentRoute): bool
    {
        if ($itemRoute === null || $itemRoute === '' || $currentRoute === null || $currentRoute === '') {
            return false;
        }

        $prefixes = $this->routePrefixes()[$itemRoute] ?? null;
        if ($prefixes === null) {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if ($currentRoute === $prefix || str_starts_with($currentRoute, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
