<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
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
abstract class AbstractRoutePrefixMenuCurrentMatcher implements MenuCurrentMatcherInterface
{
    /**
     * @return RoutePrefixMap menu item route name => prefixes matched against the request {@code _route}
     */
    abstract protected function routePrefixes(): array;

    public function isCurrent(MenuItem $item, Request $request, string $resolvedHref): bool
    {
        $itemRoute = $item->getRouteName();
        $currentRoute = $request->attributes->get('_route');
        if (!is_string($itemRoute) || $itemRoute === '' || !is_string($currentRoute) || $currentRoute === '') {
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
