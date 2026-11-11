<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuRequestContext;

/**
 * Resolves the effective menu code from the request and a hint (e.g. menu name), without HttpFoundation types.
 *
 * Replaces {@see MenuCodeResolverInterface}. To use it, alias this interface to your service
 * (`Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface: '@App\Menu\MyResolver'`); the bundle then calls
 * it from `dashboard_menu_tree()`, `dashboard_menu_config()` and the JSON API. Return the first matching menu code,
 * e.g. try (operatorId + partnerId + name), then (partnerId + name), then (name).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuCodeContextResolverInterface
{
    /**
     * Resolve the menu code to use for loading the tree.
     *
     * @param MenuRequestContext $request the request the menu is rendered for
     * @param string $hint The requested menu name/code (e.g. "sidebar") from the template or API
     */
    public function resolveMenuCodeFor(MenuRequestContext $request, string $hint): string;
}
