<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Service\MenuAreaAccessCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuAreaResolver;

use function array_values;
use function is_array;
use function is_string;
use function rtrim;
use function trim;

/**
 * One area of the application (e.g. Dashboard, Development, Organization, Preferences): its own sidebar menu, its home
 * page and the rules that tell which requests belong to it. Built from `nowo_dashboard_menu.areas` (2.22).
 *
 * {@see MenuAreaResolver} maps a request to its area; {@see MenuAreaAccessCheckerInterface} decides whether a user may
 * see it (the default checker: any of {@see $roles} granted). Framework-free value object.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuArea
{
    /** @var list<string> */
    public array $roles;

    /** @var list<string> */
    public array $pathPrefixes;

    /** @var list<string> */
    public array $routePrefixes;

    /** @var list<string> */
    public array $excludeRoutePrefixes;

    /** @var array<string, list<string>> */
    public array $currentRoutes;

    /**
     * @param string $name area name (the key under `areas`)
     * @param string $menu code of the menu rendered as this area's sidebar
     * @param string|null $label label or translation key (the host translates it)
     * @param string|null $icon icon name (e.g. a UX icon `tabler:shield-cog`)
     * @param string|null $homeRoute route of the area's home page (its hub)
     * @param array<array-key, mixed> $roles attributes passed to isGranted() by the default access checker; any one is
     *                                       enough; empty = every user
     * @param array<array-key, mixed> $pathPrefixes path prefixes of the area (`/developer`), matched on whole segments
     * @param array<array-key, mixed> $routePrefixes route name prefixes of the area's pages outside its paths (kit pages)
     * @param array<array-key, mixed> $excludeRoutePrefixes route name prefixes that never belong to the area
     * @param array<array-key, mixed> $currentRoutes menu item route => request route prefixes that keep it current
     *
     * Lists keep their non-empty strings only (configuration values are not trusted to be clean)
     */
    public function __construct(
        public string $name,
        public string $menu,
        public ?string $label = null,
        public ?string $icon = null,
        public ?string $homeRoute = null,
        array $roles = [],
        array $pathPrefixes = [],
        array $routePrefixes = [],
        array $excludeRoutePrefixes = [],
        array $currentRoutes = [],
    ) {
        $this->roles                = $this->strings($roles);
        $this->routePrefixes        = $this->strings($routePrefixes);
        $this->excludeRoutePrefixes = $this->strings($excludeRoutePrefixes);
        $paths                      = [];
        foreach ($this->strings($pathPrefixes) as $prefix) {
            $paths[] = '/' . trim(rtrim($prefix, '/'), '/');
        }
        $this->pathPrefixes = $paths;
        $current            = [];
        foreach ($currentRoutes as $route => $prefixes) {
            if (is_string($route) && $route !== '' && is_array($prefixes)) {
                $current[$route] = $this->strings($prefixes);
            }
        }
        $this->currentRoutes = $current;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string> the non-empty strings, in order
     */
    private function strings(array $values): array
    {
        $strings = [];
        foreach (array_values($values) as $value) {
            if (is_string($value) && $value !== '') {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
