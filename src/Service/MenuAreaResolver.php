<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuArea;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function rawurldecode;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The areas of `nowo_dashboard_menu.areas` and the request → area rule (2.22).
 *
 * {@see resolveFor()}:
 * 1. a request without a route belongs to the default area (`area_default`);
 * 2. exclude veto (2.22.1): an area whose `exclude_route_prefixes` match the route name is never a candidate, neither
 *    by path nor by route name (a kit mounted under another area's paths, e.g. `/organizations/{uuid}/api-studio/…`);
 * 3. the path: among the remaining areas, the one whose `path_prefixes` match the longest whole-segment prefix of the
 *    path wins (a leading locale segment matching `area_locale_pattern` is ignored: `/es/developer/…` is
 *    `/developer/…`; a path starting with an empty segment, such as `//developer`, matches no path prefix);
 * 4. then the route name: the first remaining area, in configuration order, with a matching `route_prefixes` entry
 *    (kit pages mounted outside the area's paths);
 * 5. otherwise the default area (null when `area_default` is not set).
 *
 * The area is **navigation, not authorization**: resolving an area (or granting it) never protects a route; the
 * routes of an area must be protected with `access_control` or voters. With `area_fallback_when_denied` the resolver
 * does not return an area the user is not granted: it falls back to the default area (when granted), else to the first
 * granted area, else null.
 *
 * Stateless: the areas are built once from the configuration; nothing is kept between calls (FrankenPHP worker mode).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuAreaResolver
{
    /** @var array<string, MenuArea> */
    private array $areas;

    /**
     * @param array<string, array<string, mixed>> $areas `nowo_dashboard_menu.areas`
     * @param string|null $defaultArea name of the area of requests no rule matches
     * @param string|null $localePattern regex of a leading locale path segment (null = no locale segment)
     * @param bool $fallbackWhenDenied whether {@see resolveFor()} never returns an area the user is not granted
     */
    public function __construct(
        array $areas = [],
        private ?string $defaultArea = null,
        private ?string $localePattern = null,
        private MenuAreaAccessCheckerInterface $accessChecker = new RoleMenuAreaAccessChecker(),
        private bool $fallbackWhenDenied = false,
    ) {
        $built = [];
        foreach ($areas as $name => $config) {
            $built[$name] = $this->area($name, $config);
        }
        $this->areas = $built;
    }

    /**
     * Every configured area, in configuration order.
     *
     * @return list<MenuArea>
     */
    public function all(): array
    {
        return array_values($this->areas);
    }

    /** The area with that name, or null. */
    public function get(string $name): ?MenuArea
    {
        return $this->areas[$name] ?? null;
    }

    /** The default area (`area_default`), or null. */
    public function defaultArea(): ?MenuArea
    {
        return $this->defaultArea === null ? null : $this->get($this->defaultArea);
    }

    /**
     * The areas the current user may see ({@see MenuAreaAccessCheckerInterface}), in configuration order.
     *
     * @return list<MenuArea>
     */
    public function granted(): array
    {
        return array_values(array_filter($this->areas, $this->accessChecker->canAccessArea(...)));
    }

    /** Whether the current user may see the area. */
    public function isGranted(MenuArea|string $area): bool
    {
        $area = is_string($area) ? $this->get($area) : $area;

        return $area instanceof MenuArea && $this->accessChecker->canAccessArea($area);
    }

    /** The area a request belongs to (see the class description), or null. */
    public function resolveFor(MenuRequestContext $request): ?MenuArea
    {
        $area = $this->match($request);
        if (!$this->fallbackWhenDenied || !$area instanceof MenuArea || $this->accessChecker->canAccessArea($area)) {
            return $area;
        }

        $default = $this->defaultArea();
        if ($default instanceof MenuArea && $this->accessChecker->canAccessArea($default)) {
            return $default;
        }

        return $this->granted()[0] ?? null;
    }

    private function match(MenuRequestContext $request): ?MenuArea
    {
        $route = $request->route;
        if ($route === null || $route === '') {
            return $this->defaultArea();
        }

        $path    = $this->pathWithoutLocale($request->path);
        $best    = null;
        $bestLen = -1;
        if ($path !== null) {
            foreach ($this->areas as $area) {
                if ($this->isExcluded($route, $area)) {
                    continue;
                }
                foreach ($area->pathPrefixes as $prefix) {
                    if (strlen($prefix) > $bestLen && $this->pathMatches($path, $prefix)) {
                        $best    = $area;
                        $bestLen = strlen($prefix);
                    }
                }
            }
        }
        if ($best instanceof MenuArea) {
            return $best;
        }

        foreach ($this->areas as $area) {
            if ($this->startsWithAny($route, $area->routePrefixes) && !$this->isExcluded($route, $area)) {
                return $area;
            }
        }

        return $this->defaultArea();
    }

    /** Whether the route name is vetoed for the area (`exclude_route_prefixes`), at the path and the route step. */
    private function isExcluded(string $route, MenuArea $area): bool
    {
        return $this->startsWithAny($route, $area->excludeRoutePrefixes);
    }

    /**
     * The decoded path without its locale segment, or null when it cannot match a path prefix: a path whose first
     * segment (after the locale) is empty, such as `//developer/x` or `/es//developer/x`. The router and
     * `access_control` do not see `//developer` as `/developer` either, so neither does the sidebar.
     */
    private function pathWithoutLocale(string $path): ?string
    {
        // The router and access_control match the decoded path (`/%64eveloper` is `/developer`): so do we.
        $decoded = rawurldecode($path);
        if (str_starts_with($decoded, '//')) {
            return null;
        }
        // Keep empty segments until the locale is checked: `/es//` must not collapse to `/es/` (= `/`).
        $segments = explode('/', str_starts_with($decoded, '/') ? substr($decoded, 1) : $decoded);
        if ($this->localePattern !== null && $this->localePattern !== '' && preg_match($this->localePattern, $segments[0]) === 1) {
            $segments = array_slice($segments, 1);
            if (count($segments) > 1 && $segments[0] === '') {
                return null;
            }
        }

        return '/' . rtrim(implode('/', $segments), '/');
    }

    private function pathMatches(string $path, string $prefix): bool
    {
        return $prefix === '/' || $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    /**
     * @param list<string> $prefixes
     */
    private function startsWithAny(string $route, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function area(string $name, array $config): MenuArea
    {
        $menu = $config['menu'] ?? null;

        return new MenuArea(
            name: $name,
            menu: is_string($menu) && $menu !== '' ? $menu : $name,
            label: $this->nullableString($config['label'] ?? null),
            icon: $this->nullableString($config['icon'] ?? null),
            homeRoute: $this->nullableString($config['home_route'] ?? null),
            roles: $this->list($config['roles'] ?? []),
            pathPrefixes: $this->list($config['path_prefixes'] ?? []),
            routePrefixes: $this->list($config['route_prefixes'] ?? []),
            excludeRoutePrefixes: $this->list($config['exclude_route_prefixes'] ?? []),
            currentRoutes: $this->list($config['current_routes'] ?? []),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
