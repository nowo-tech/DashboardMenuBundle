<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_is_list;
use function array_key_exists;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function md5;
use function serialize;
use function unserialize;

/**
 * Loads menu tree via a single SQL path (menu + items in 2 queries), optional filesystem cache, builds nested structure.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuTreeLoader
{
    private const CACHE_KEY_PREFIX = 'nowo_dashboard_menu.tree.';

    public function __construct(
        private MenuRepository $menuRepository,
        private MenuItemRepository $menuItemRepository,
        private MenuConfigResolver $configResolver,
        private MenuIconNameResolver $menuIconNameResolver,
        private ContainerInterface $permissionCheckerLocator,
        private AllowAllMenuPermissionChecker $defaultPermissionChecker,
        private ContainerInterface $linkResolverContainer,
        /** @var array<string, string> */
        private array $menuLinkResolverChoices = [],
        private ?RequestStack $requestStack = null,
        private ?CacheItemPoolInterface $cachePool = null,
        private int $cacheTtl = 60,
        private ?DashboardMenuDataCollector $dataCollector = null,
        /** @var array<string, string> */
        private array $permissionCheckerChoices = [],
    ) {
    }

    /**
     * Returns the menu tree for the given menu code and locale as a list of root nodes.
     * When multiple menus share the same code (different context), pass $contextSets to try
     * combinations in order; the first matching menu is used. Use null/empty for "no context".
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets Ordered list of context objects to try; null = try [null, []] (no context first)
     *
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    public function loadTree(string $menuCode, string $locale, mixed $permissionContext = null, ?array $contextSets = null): array
    {
        $sets = $contextSets ?? [null, []];

        $cacheKey = $this->cachePool instanceof CacheItemPoolInterface
            ? self::CACHE_KEY_PREFIX . md5($menuCode . '.' . $locale . '.' . serialize($sets))
            : null;

        if ($cacheKey !== null) {
            $item = $this->cachePool->getItem($cacheKey);
            if ($item->isHit()) {
                $raw = unserialize($item->get(), ['allowed_classes' => false]);
                if (is_array($raw) && isset($raw['menu'], $raw['items'])) {
                    [$menu, $flat]                                  = $this->hydrateMenuAndItems($raw['menu'], $raw['items'], $locale);
                    $config                                         = $this->configResolver->getConfig($menuCode, $sets, $menu);
                    [$checker, $checkerServiceId, $checkerFallback] = $this->resolvePermissionChecker($config['permission_checker']);
                    $tree                                           = $this->buildTree($flat, $checker, $permissionContext, $menuCode, $config['permission_checker'], $checkerServiceId, $checkerFallback);
                    $tree                                           = $this->mergeDynamicServiceChildren($tree, $checker, $permissionContext, $menuCode, $config['permission_checker'], $checkerServiceId, $checkerFallback);
                    $this->markNodesWithChildren($tree);

                    return $this->pruneEmptySections($tree);
                }
            }
        }

        $raw = $this->menuRepository->findMenuAndItemsRaw($menuCode, $sets);
        if ($raw === null) {
            return $this->loadTreeLegacy($menuCode, $locale, $permissionContext, $sets);
        }

        if ($cacheKey !== null) {
            $cacheItem = $this->cachePool->getItem($cacheKey);
            $cacheItem->set(serialize($raw));
            $cacheItem->expiresAfter($this->cacheTtl);
            $this->cachePool->save($cacheItem);
        }

        [$menu, $flat]                                  = $this->hydrateMenuAndItems($raw['menu'], $raw['items'], $locale);
        $config                                         = $this->configResolver->getConfig($menuCode, $sets, $menu);
        [$checker, $checkerServiceId, $checkerFallback] = $this->resolvePermissionChecker($config['permission_checker']);
        $tree                                           = $this->buildTree($flat, $checker, $permissionContext, $menuCode, $config['permission_checker'], $checkerServiceId, $checkerFallback);
        $tree                                           = $this->mergeDynamicServiceChildren($tree, $checker, $permissionContext, $menuCode, $config['permission_checker'], $checkerServiceId, $checkerFallback);
        $this->markNodesWithChildren($tree);

        return $this->pruneEmptySections($tree);
    }

    /**
     * Legacy path: 1 query for menu (possibly N with context sets) + 1 for items. Used when findMenuAndItemsRaw is not available or returns null.
     *
     * @param list<array<string, bool|int|string>|null> $sets
     *
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    private function loadTreeLegacy(string $menuCode, string $locale, mixed $permissionContext, array $sets): array
    {
        $menu = $this->menuRepository->findForCodeWithContextSets($menuCode, $sets);
        if (!$menu instanceof Menu) {
            return [];
        }
        $flat                                           = $this->menuItemRepository->findAllForMenuOrderedByTree($menu, $locale);
        $config                                         = $this->configResolver->getConfig($menuCode, $sets, $menu);
        [$checker, $checkerServiceId, $checkerFallback] = $this->resolvePermissionChecker($config['permission_checker']);
        $tree                                           = $this->buildTree($flat, $checker, $permissionContext, $menuCode, $config['permission_checker'], $checkerServiceId, $checkerFallback);
        $tree                                           = $this->mergeDynamicServiceChildren($tree, $checker, $permissionContext, $menuCode, $config['permission_checker'], $checkerServiceId, $checkerFallback);
        $this->markNodesWithChildren($tree);

        return $this->pruneEmptySections($tree);
    }

    /**
     * @param array<string, mixed> $menuRow
     * @param list<array<string, mixed>> $itemRows
     *
     * @return array{0: Menu, 1: list<MenuItem>}
     */
    private function hydrateMenuAndItems(array $menuRow, array $itemRows, string $locale): array
    {
        $menu = $this->hydrateMenuFromRow($menuRow);

        $items = $this->hydrateItemsFromRows($itemRows, $menu, $locale);

        return [$menu, $items];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateMenuFromRow(array $row): Menu
    {
        $menu = new Menu();
        if (isset($row['id'])) {
            $this->setEntityId($menu, (int) $row['id']);
        }
        $menu->setCode((string) ($row['code'] ?? ''));
        $this->setPrivateProperty($menu, 'contextKey', (string) ($row['attributes_key'] ?? ''));
        $menu->setName(isset($row['name']) ? (string) $row['name'] : null);
        $menu->setIcon(isset($row['icon']) ? (string) $row['icon'] : null);
        $this->setMenuString($menu, 'classMenu', $row['class_menu'] ?? null);
        $this->setMenuString($menu, 'ulId', $row['ul_id'] ?? null);
        $this->setMenuString($menu, 'classItem', $row['class_item'] ?? null);
        $this->setMenuString($menu, 'classLink', $row['class_link'] ?? null);
        $this->setMenuString($menu, 'classChildren', $row['class_children'] ?? null);
        $this->setMenuString($menu, 'classSectionChildren', $row['class_section_children'] ?? null);
        $this->setMenuString($menu, 'classSectionChildItem', $row['class_section_child_item'] ?? null);
        $this->setMenuString($menu, 'classSectionChildLink', $row['class_section_child_link'] ?? null);
        $this->setMenuString($menu, 'classCurrent', $row['class_current'] ?? null);
        $this->setMenuString($menu, 'classBranchExpanded', $row['class_branch_expanded'] ?? null);
        $this->setMenuString($menu, 'classHasChildren', $row['class_has_children'] ?? null);
        $this->setMenuString($menu, 'classExpanded', $row['class_expanded'] ?? null);
        $this->setMenuString($menu, 'classCollapsed', $row['class_collapsed'] ?? null);
        $this->setMenuString($menu, 'permissionChecker', $row['permission_checker'] ?? null);
        $menu->setDepthLimit(isset($row['depth_limit']) ? (int) $row['depth_limit'] : null);
        $menu->setCollapsible(isset($row['collapsible']) ? (bool) $row['collapsible'] : null);
        $menu->setCollapsibleExpanded(isset($row['collapsible_expanded']) ? (bool) $row['collapsible_expanded'] : null);
        $menu->setNestedCollapsible(isset($row['nested_collapsible']) ? (bool) $row['nested_collapsible'] : null);
        $menu->setNestedCollapsibleSections(isset($row['nested_collapsible_sections']) ? (bool) $row['nested_collapsible_sections'] : null);
        if (isset($row['attributes'])) {
            $menu->setContext(is_array($row['attributes']) ? $row['attributes'] : (json_decode((string) $row['attributes'], true) ?: null));
        }
        $menu->setBase(isset($row['base']) && (bool) $row['base']);

        return $menu;
    }

    private function setMenuString(Menu $menu, string $property, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $setter = 'set' . $property;
        $menu->{$setter}((string) $value);
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<MenuItem>
     */
    private function hydrateItemsFromRows(array $rows, Menu $menu, string $locale): array
    {
        $items = [];
        foreach ($rows as $row) {
            $item = new MenuItem();
            if (isset($row['id'])) {
                $this->setEntityId($item, (int) $row['id']);
            }
            $item->setMenu($menu);
            $item->setPosition((int) ($row['position'] ?? 0));
            $item->setLabel((string) ($row['label'] ?? ''));
            if (isset($row['translations'])) {
                $item->setTranslations(is_array($row['translations']) ? $row['translations'] : (json_decode((string) $row['translations'], true) ?: null));
            }
            $label = $item->getLabelForLocale($locale);
            $item->setLabel($label);
            $item->setLinkType(array_key_exists('link_type', $row) && $row['link_type'] === null
                ? null
                : (string) ($row['link_type'] ?? MenuItem::LINK_TYPE_ROUTE));
            $item->setRouteName(isset($row['route_name']) ? (string) $row['route_name'] : null);
            if (isset($row['route_params'])) {
                $item->setRouteParams(is_array($row['route_params']) ? $row['route_params'] : (json_decode((string) $row['route_params'], true) ?: null));
            }
            $item->setExternalUrl(isset($row['external_url']) ? (string) $row['external_url'] : null);
            $permissionKeys = null;
            if (isset($row['permission_keys'])) {
                $permissionKeys = is_array($row['permission_keys']) ? $row['permission_keys'] : (json_decode((string) $row['permission_keys'], true) ?: null);
            }
            if (is_array($permissionKeys)) {
                $item->setPermissionKeys(array_values(array_filter($permissionKeys, static fn (mixed $v): bool => is_string($v) && trim($v) !== '')));
            } else {
                $item->setPermissionKey(isset($row['permission_key']) ? (string) $row['permission_key'] : null);
            }
            $item->setIsUnanimous(isset($row['is_unanimous']) ? (bool) $row['is_unanimous'] : true);
            $item->setIcon(isset($row['icon']) ? (string) $row['icon'] : null);
            $item->setItemType((string) ($row['item_type'] ?? MenuItem::ITEM_TYPE_LINK));
            if (isset($row['link_resolver']) && is_string($row['link_resolver']) && $row['link_resolver'] !== '') {
                $item->setLinkResolver($row['link_resolver']);
            }
            $item->setTargetBlank(isset($row['target_blank']) && (bool) $row['target_blank']);
            if (array_key_exists('section_collapsible', $row)) {
                $sc = $row['section_collapsible'];
                $item->setSectionCollapsible($sc === null ? null : (bool) $sc);
            }
            $items[] = $item;
        }

        $byId = [];
        foreach ($items as $item) {
            $id = $item->getId();
            if ($id !== null) {
                $byId[$id] = $item;
            }
        }
        foreach ($rows as $i => $row) {
            $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
            if ($parentId !== null && isset($items[$i], $byId[$parentId])) {
                $items[$i]->setParent($byId[$parentId]);
            }
        }

        return $items;
    }

    private function setEntityId(Menu|MenuItem $entity, int $id): void
    {
        $ref = new ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }

    private function setPrivateProperty(object $entity, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($entity, $property);
        $ref->setValue($entity, $value);
    }

    /**
     * Mark each node with 'had_children' (whether it had any children in the built tree) to avoid DB access when pruning.
     *
     * @param list<array<string, mixed>> $nodes
     */
    private function markNodesWithChildren(array $nodes): void
    {
        foreach ($nodes as &$node) {
            $this->markNodesWithChildren($node['children']);
            $node['had_children'] = count($node['children']) > 0;
        }
    }

    /**
     * After permission filtering:
     * - Sections are pruned when they had children but all were hidden by the permission checker.
     * - Link nodes whose children are all hidden are also pruned.
     * - Leaf links (no children at all) are always kept when the checker allows them.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    private function pruneEmptySections(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $children    = $this->pruneEmptySections($node['children']);
            $hadChildren = $node['had_children'] ?? false;
            $item        = $node['item'];
            if (count($children) > 0 || !$hadChildren) {
                $result[] = ['item' => $item, 'children' => $children];
            }
        }

        return $result;
    }

    /**
     * @return array{0: MenuPermissionCheckerInterface, 1: string|null, 2: bool}
     */
    private function resolvePermissionChecker(?string $serviceId): array
    {
        $resolvedServiceId = $this->normalizePermissionCheckerServiceId($serviceId);
        if ($resolvedServiceId !== null && $resolvedServiceId !== '' && $this->permissionCheckerLocator->has($resolvedServiceId)) {
            $service = $this->permissionCheckerLocator->get($resolvedServiceId);
            if ($service instanceof MenuPermissionCheckerInterface) {
                return [$service, $resolvedServiceId, false];
            }
        }

        return [$this->defaultPermissionChecker, null, $serviceId !== null && $serviceId !== ''];
    }

    private function normalizePermissionCheckerServiceId(?string $serviceId): ?string
    {
        if ($serviceId === null || $serviceId === '') {
            return $serviceId;
        }
        if ($this->permissionCheckerLocator->has($serviceId)) {
            return $serviceId;
        }

        foreach ($this->permissionCheckerChoices as $id => $label) {
            if ($label === $serviceId && $this->permissionCheckerLocator->has($id)) {
                return $id;
            }
        }

        return $serviceId;
    }

    /**
     * Merges dynamic child rows from {@see MenuLinkResolverInterface::resolveHref()} (when it returns a list)
     * with persisted children for itemType "service"
     * (same ordering field: position on DB items and `position` in each dynamic row).
     *
     * @param list<array{item: MenuItem, children: list<array<string, mixed>>}> $nodes
     *
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    private function mergeDynamicServiceChildren(
        array $nodes,
        MenuPermissionCheckerInterface $checker,
        mixed $permissionContext,
        string $menuCode,
        ?string $checkerSelectedServiceId,
        ?string $checkerResolvedServiceId,
        bool $checkerFallback,
    ): array {
        $out = [];
        foreach ($nodes as $node) {
            $children = $this->mergeDynamicServiceChildren(
                $node['children'],
                $checker,
                $permissionContext,
                $menuCode,
                $checkerSelectedServiceId,
                $checkerResolvedServiceId,
                $checkerFallback,
            );
            $item = $node['item'];
            if ($item->getItemType() === MenuItem::ITEM_TYPE_SERVICE) {
                $dynamic = $this->buildDynamicChildNodes(
                    $item,
                    $checker,
                    $permissionContext,
                    $menuCode,
                    $checkerSelectedServiceId,
                    $checkerResolvedServiceId,
                    $checkerFallback,
                );
                if ($dynamic !== []) {
                    $children = $this->mergeChildNodesByPosition($dynamic, $children);
                }
            }

            $out[] = ['item' => $item, 'children' => $children];
        }

        return $out;
    }

    /**
     * @param list<array{item: MenuItem, children: list<array<string, mixed>>}> $dynamicNodes
     * @param list<array{item: MenuItem, children: list<array<string, mixed>>}> $dbNodes
     *
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    private function mergeChildNodesByPosition(array $dynamicNodes, array $dbNodes): array
    {
        $merged = array_merge($dynamicNodes, $dbNodes);
        usort($merged, static fn (array $a, array $b): int => $a['item']->getPosition() <=> $b['item']->getPosition());

        return $merged;
    }

    /**
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    private function buildDynamicChildNodes(
        MenuItem $serviceItem,
        MenuPermissionCheckerInterface $checker,
        mixed $permissionContext,
        string $menuCode,
        ?string $checkerSelectedServiceId,
        ?string $checkerResolvedServiceId,
        bool $checkerFallback,
    ): array {
        $rawId = $serviceItem->getLinkResolver();
        if ($rawId === null || $rawId === '') {
            return [];
        }

        $serviceId = $this->normalizeMenuLinkResolverServiceId($rawId);
        if ($serviceId === null || !$this->linkResolverContainer->has($serviceId)) {
            return [];
        }

        try {
            $resolver = $this->linkResolverContainer->get($serviceId);
        } catch (\Throwable) {
            return [];
        }

        if (!$resolver instanceof MenuLinkResolverInterface) {
            return [];
        }

        $request = $this->requestStack?->getCurrentRequest();
        try {
            $resolved = $resolver->resolveHref($serviceItem, $request instanceof Request ? $request : null, $permissionContext);
        } catch (\Throwable) {
            return [];
        }

        if (is_string($resolved)) {
            return [];
        }

        if (!is_array($resolved) || !array_is_list($resolved)) {
            return [];
        }

        return $this->childRowsToMenuNodes(
            $resolved,
            $serviceItem,
            $checker,
            $permissionContext,
            $menuCode,
            $checkerSelectedServiceId,
            $checkerResolvedServiceId,
            $checkerFallback,
        );
    }

    /**
     * @param list<mixed> $rows
     *
     * @return list<array{item: MenuItem, children: list<array<string, mixed>>}>
     */
    private function childRowsToMenuNodes(
        array $rows,
        MenuItem $serviceItem,
        MenuPermissionCheckerInterface $checker,
        mixed $permissionContext,
        string $menuCode,
        ?string $checkerSelectedServiceId,
        ?string $checkerResolvedServiceId,
        bool $checkerFallback,
    ): array {
        $menu = $serviceItem->getMenu();
        if (!$menu instanceof Menu) {
            return [];
        }

        $nodes = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = $row['label'] ?? null;
            $href  = $row['href'] ?? null;
            if (!is_string($label) || trim($label) === '' || !is_string($href) || trim($href) === '') {
                continue;
            }
            $position = $row['position'] ?? 0;
            if (!is_int($position)) {
                $position = (int) $position;
            }
            $icon         = isset($row['icon']) && is_string($row['icon']) ? $row['icon'] : null;
            $targetBlank  = isset($row['targetBlank']) && (bool) $row['targetBlank'];
            $child        = MenuItem::createDynamicChildLink($menu, $label, trim($href), $position, $icon, $targetBlank);
            $this->normalizeItemIcon($child);

            $visible = $checker->canView($child, $permissionContext);
            if ($this->dataCollector instanceof DashboardMenuDataCollector) {
                $this->dataCollector->addPermissionCheck(
                    $menuCode,
                    $checkerSelectedServiceId,
                    $checker::class,
                    $checkerResolvedServiceId,
                    $checkerFallback,
                    $child,
                    $visible,
                );
            }
            if (!$visible) {
                continue;
            }

            $nodes[] = ['item' => $child, 'children' => []];
        }

        return $nodes;
    }

    private function normalizeMenuLinkResolverServiceId(string $serviceId): ?string
    {
        if ($this->linkResolverContainer->has($serviceId)) {
            return $serviceId;
        }

        foreach ($this->menuLinkResolverChoices as $id => $label) {
            if ($label === $serviceId && $this->linkResolverContainer->has($id)) {
                return $id;
            }
        }

        return $serviceId;
    }

    /**
     * Build nested tree from flat list (parent + position). Filters by permission.
     *
     * @param list<MenuItem> $flat
     *
     * @return list<array{item: MenuItem, children: list<array>}>
     */
    private function buildTree(
        array $flat,
        MenuPermissionCheckerInterface $checker,
        mixed $permissionContext,
        string $menuCode,
        ?string $checkerSelectedServiceId,
        ?string $checkerResolvedServiceId,
        bool $checkerFallback,
    ): array {
        foreach ($flat as $item) {
            $this->normalizeItemIcon($item);
        }

        /** @var array<int, array{item: MenuItem, children: list<array<string, mixed>>}> $map */
        $map = [];
        /** @var array<string, bool> $visibilityMap */
        $visibilityMap = [];
        /** @var list<array{item: MenuItem, children: list<array<string, mixed>>}> $roots */
        $roots = [];

        // First pass: evaluate visibility once and register diagnostics in profiler.
        foreach ($flat as $item) {
            $isVisible                            = $checker->canView($item, $permissionContext);
            $visibilityMap[$this->nodeKey($item)] = $isVisible;
            if ($this->dataCollector instanceof DashboardMenuDataCollector) {
                $this->dataCollector->addPermissionCheck(
                    $menuCode,
                    $checkerSelectedServiceId,
                    $checker::class,
                    $checkerResolvedServiceId,
                    $checkerFallback,
                    $item,
                    $isVisible,
                );
            }
            if (!$isVisible) {
                continue;
            }
            $id            = $item->getId();
            $map[$id ?? 0] = ['item' => $item, 'children' => []];
        }

        // Second pass: wire parents/children using references so updates are shared
        foreach ($flat as $item) {
            if (($visibilityMap[$this->nodeKey($item)] ?? false) !== true) {
                continue;
            }

            $id     = $item->getId();
            $parent = $item->getParent();
            if ($id === null || !isset($map[$id])) {
                continue;
            }

            if ($parent === null) {
                $roots[] = &$map[$id];
            } else {
                $parentId = $parent->getId();
                if ($parentId !== null && isset($map[$parentId])) {
                    $map[$parentId]['children'][] = &$map[$id];
                } else {
                    $roots[] = &$map[$id];
                }
            }
        }

        $sort = static function (array $nodes) use (&$sort): array {
            usort($nodes, static fn (array $a, array $b): int => $a['item']->getPosition() <=> $b['item']->getPosition());
            foreach ($nodes as &$node) {
                $node['children'] = $sort($node['children']);
            }

            return $nodes;
        };

        return $sort($roots);
    }

    private function nodeKey(MenuItem $item): string
    {
        $id = $item->getId();
        if ($id !== null) {
            return 'id:' . $id;
        }

        return 'obj:' . spl_object_id($item);
    }

    /**
     * Applies icon_library_prefix_map to the item's icon (e.g. bootstrap-icons:house → bi:house)
     * so the tree and data collector see the resolved value.
     */
    private function normalizeItemIcon(MenuItem $item): void
    {
        $item->setIcon($this->menuIconNameResolver->resolve($item->getIcon()));
    }
}
