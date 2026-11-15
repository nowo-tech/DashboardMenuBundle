<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;

use function str_starts_with;

/**
 * The route-prefix current matcher fed by configuration (2.22), so hosts no longer subclass
 * {@see AbstractRoutePrefixMenuCurrentMatcher} once per menu: a link stays current while the request route starts with
 * one of the prefixes listed for the link's route.
 *
 * Rules come from `nowo_dashboard_menu.current_routes` (every menu) and from each area's `current_routes` (only the
 * links of that area's menu, so two areas can give the same route different prefixes). Like every current matcher, it
 * is ORed with the built-in path + query match ({@see CurrentRouteTreeDecorator}): exact single-page entries need no
 * rule. Registered only when some rule is configured.
 *
 * Stateless (FrankenPHP worker mode): the rules are configuration.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ConfiguredRouteMenuCurrentMatcher implements MenuItemContextCurrentMatcherInterface
{
    /**
     * @param array<string, list<string>> $currentRoutes rules of every menu: item route => request route prefixes
     */
    public function __construct(
        private MenuAreaResolver $areas,
        private array $currentRoutes = [],
    ) {
    }

    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool
    {
        $itemRoute    = $item->routeName;
        $currentRoute = $request->route;
        if ($itemRoute === null || $itemRoute === '' || $currentRoute === null || $currentRoute === '') {
            return false;
        }

        if ($this->matches($this->currentRoutes[$itemRoute] ?? [], $currentRoute)) {
            return true;
        }
        foreach ($this->areas->all() as $area) {
            if ($area->menu === $item->menuCode && $this->matches($area->currentRoutes[$itemRoute] ?? [], $currentRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $prefixes
     */
    private function matches(array $prefixes, string $currentRoute): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($currentRoute, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
