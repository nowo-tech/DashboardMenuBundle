<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function count;
use function is_array;

/**
 * Collects menu trees rendered on the page (code, context, items summary) and counts
 * DB queries hitting dashboard_menu tables. Only active in dev; zero cost in prod.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DashboardMenuDataCollector extends DataCollector implements LateDataCollectorInterface, ResetInterface
{
    private const MENU_TABLE_PREFIX = 'dashboard_menu';

    /** @var list<array{code: string, context_sets: mixed, resolved_context: mixed, root_count: int, items_summary: list<array>, query_count: int|null}> */
    private array $menuLoads = [];

    /** @var list<array{
     *   menu_code: string,
     *   checker_selected: string|null,
     *   checker_resolved: string,
     *   checker_service_id: string|null,
     *   checker_fallback: bool,
     *   label: string,
     *   item_type: string,
     *   permission_key: string|null,
     *   route_name: string|null,
     *   external_url: string|null,
     *   result: bool
     * }> */
    private array $permissionChecks = [];

    private ?int $menuRelatedQueryCount = null;

    public function __construct(
        private readonly ?\Symfony\Component\HttpKernel\Profiler\Profiler $profiler = null,
        private readonly ?MenuIconNameResolver $menuIconNameResolver = null,
        /** @var array<string, mixed> */
        private readonly array $bundleConfig = [],
        /** @var array<string, string> */
        private readonly array $permissionCheckerChoices = [],
        private readonly ?string $connectionName = null,
        private readonly ?string $tablePrefix = null,
        private readonly ?int $cacheTtl = null,
        private readonly ?string $cachePool = null,
        /** @var list<string> */
        private readonly array $locales = [],
        private readonly ?string $defaultLocale = null,
        /** @var array<string, string> */
        private readonly array $iconLibraryPrefixMap = [],
    ) {
        $this->data = [
            'menus'                      => [],
            'permission_checks'          => [],
            'menu_query_count'           => null,
            'bundle_config'              => $this->bundleConfig,
            'permission_checker_choices' => $this->permissionCheckerChoices,
            'connection_name'            => $this->connectionName,
            'table_prefix'               => $this->tablePrefix,
            'cache'                      => [
                'ttl'  => $this->cacheTtl,
                'pool' => $this->cachePool,
            ],
            'locales'                 => $this->locales,
            'default_locale'          => $this->defaultLocale,
            'icon_library_prefix_map' => $this->iconLibraryPrefixMap,
        ];
    }

    /**
     * Called from MenuExtension when a menu tree is loaded (dev only).
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets
     * @param list<array{item: MenuItem, children: list<array>}> $tree
     */
    public function addMenuLoad(string $code, ?array $contextSets, array $tree, mixed $resolvedContext = null, ?int $queryCount = null): void
    {
        $this->menuLoads[] = [
            'code'             => $code,
            'context_sets'     => $contextSets,
            'resolved_context' => $resolvedContext,
            'root_count'       => count($tree),
            'items_summary'    => $this->summarizeTree($tree),
            'query_count'      => $queryCount,
        ];
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
        $this->data = [
            'menus'                      => $this->menuLoads,
            'permission_checks'          => $this->permissionChecks,
            'menu_query_count'           => $this->menuRelatedQueryCount,
            'bundle_config'              => $this->bundleConfig,
            'permission_checker_choices' => $this->permissionCheckerChoices,
            'connection_name'            => $this->connectionName,
            'table_prefix'               => $this->tablePrefix,
            'cache'                      => [
                'ttl'  => $this->cacheTtl,
                'pool' => $this->cachePool,
            ],
            'locales'                 => $this->locales,
            'default_locale'          => $this->defaultLocale,
            'icon_library_prefix_map' => $this->iconLibraryPrefixMap,
        ];
    }

    public function lateCollect(): void
    {
        if (!$this->profiler instanceof \Symfony\Component\HttpKernel\Profiler\Profiler) {
            return;
        }
        try {
            $collector = $this->profiler->get('db');
        } catch (Throwable) {
            return;
        }
        if (!is_object($collector) || !method_exists($collector, 'getData')) {
            return;
        }
        $raw   = $collector->{'getData'}();
        $data  = is_array($raw) ? $raw : [];
        $count = 0;
        foreach (['queries', 'grouped_queries'] as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }
            foreach ($data[$key] as $connectionQueries) {
                if (!is_array($connectionQueries)) {
                    continue;
                }
                foreach ($connectionQueries as $q) {
                    $sql = is_array($q) ? ($q['sql'] ?? '') : (string) $q;
                    if (stripos((string) $sql, self::MENU_TABLE_PREFIX) !== false) {
                        ++$count;
                    }
                }
            }
        }
        $this->menuRelatedQueryCount    = $count;
        $this->data['menu_query_count'] = $count;
    }

    public function reset(): void
    {
        $this->menuLoads                = [];
        $this->permissionChecks         = [];
        $this->menuRelatedQueryCount    = null;
        $this->data['menus']            = [];
        $this->data['permission_checks'] = [];
        $this->data['menu_query_count'] = null;
    }

    public function getName(): string
    {
        return 'nowo_dashboard_menu';
    }

    /** @return list<array{code: string, context_sets: mixed, resolved_context: mixed, root_count: int, items_summary: list<array>, query_count: int|null}> */
    public function getMenus(): array
    {
        return $this->data['menus'] ?? [];
    }

    public function getMenuQueryCount(): ?int
    {
        return $this->data['menu_query_count'] ?? null;
    }

    /**
     * @return list<array{
     *   menu_code: string,
     *   checker_selected: string|null,
     *   checker_resolved: string,
     *   checker_service_id: string|null,
     *   checker_fallback: bool,
     *   label: string,
     *   item_type: string,
     *   permission_key: string|null,
     *   route_name: string|null,
     *   external_url: string|null,
     *   result: bool
     * }>
     */
    public function getPermissionChecks(): array
    {
        return $this->data['permission_checks'] ?? [];
    }

    public function addPermissionCheck(
        string $menuCode,
        ?string $checkerSelected,
        string $checkerResolved,
        ?string $checkerServiceId,
        bool $checkerFallback,
        MenuItem $item,
        bool $result,
    ): void {
        $this->permissionChecks[] = [
            'menu_code'          => $menuCode,
            'checker_selected'   => $checkerSelected,
            'checker_resolved'   => $checkerResolved,
            'checker_service_id' => $checkerServiceId,
            'checker_fallback'   => $checkerFallback,
            'label'              => $item->getLabel(),
            'item_type'          => $item->getItemType(),
            'permission_key'     => $item->getPermissionKey(),
            'route_name'         => $item->getRouteName(),
            'external_url'       => $item->getExternalUrl(),
            'result'             => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBundleConfig(): array
    {
        return $this->data['bundle_config'] ?? $this->bundleConfig;
    }

    /**
     * @return array<string, string>
     */
    public function getPermissionCheckerChoices(): array
    {
        return $this->data['permission_checker_choices'] ?? $this->permissionCheckerChoices;
    }

    public function getConnectionName(): ?string
    {
        return $this->data['connection_name'] ?? $this->connectionName;
    }

    public function getTablePrefix(): ?string
    {
        return $this->data['table_prefix'] ?? $this->tablePrefix;
    }

    /**
     * @return array{ttl: int|null, pool: string|null}
     */
    public function getCacheConfig(): array
    {
        $cache = $this->data['cache'] ?? [
            'ttl'  => $this->cacheTtl,
            'pool' => $this->cachePool,
        ];

        return [
            'ttl'  => $cache['ttl'] ?? $this->cacheTtl,
            'pool' => $cache['pool'] ?? $this->cachePool,
        ];
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        /** @var list<string> $locales */
        $locales = $this->data['locales'] ?? $this->locales;

        return $locales;
    }

    public function getDefaultLocale(): ?string
    {
        return $this->data['default_locale'] ?? $this->defaultLocale;
    }

    /**
     * @return array<string, string>
     */
    public function getIconLibraryPrefixMap(): array
    {
        /** @var array<string, string> $map */
        $map = $this->data['icon_library_prefix_map'] ?? $this->iconLibraryPrefixMap;

        return $map;
    }

    /**
     * @param list<array{item: MenuItem, children: list<array>}> $nodes
     *
     * @return list<array{label: string, type: string, icon: string|null, children_count: int, children: list<array>}>
     */
    private function summarizeTree(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $item     = $node['item'];
            $children = $node['children'];
            $out[]    = [
                'label'          => $item->getLabel(),
                'type'           => $item->getItemType(),
                'icon'           => $this->menuIconNameResolver instanceof MenuIconNameResolver ? $this->menuIconNameResolver->resolve($item->getIcon()) : $item->getIcon(),
                'children_count' => count($children),
                'children'       => $this->summarizeTree($children),
            ];
        }

        return $out;
    }
}
