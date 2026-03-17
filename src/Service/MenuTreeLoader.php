<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use ReflectionProperty;

use function count;
use function is_array;
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
        private ContainerInterface $container,
        private AllowAllMenuPermissionChecker $defaultPermissionChecker,
        private ?CacheItemPoolInterface $cachePool = null,
        private int $cacheTtl = 60,
    ) {
    }

    /**
     * Returns the menu tree for the given menu code and locale as a list of root nodes.
     * When multiple menus share the same code (different context), pass $contextSets to try
     * combinations in order; the first matching menu is used. Use null/empty for "no context".
     *
     * @param list<array<string, bool|int|string>|null>|null $contextSets Ordered list of context objects to try; null = try [null, []] (no context first)
     *
     * @return list<array{item: MenuItem, children: list<array>}>
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
                    [$menu, $flat] = $this->hydrateMenuAndItems($raw['menu'], $raw['items'], $locale);
                    $config        = $this->configResolver->getConfig($menuCode, $sets, $menu);
                    $checker       = $this->resolvePermissionChecker($config['permission_checker']);
                    $tree          = $this->buildTree($flat, $checker, $permissionContext);
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

        [$menu, $flat] = $this->hydrateMenuAndItems($raw['menu'], $raw['items'], $locale);
        $config        = $this->configResolver->getConfig($menuCode, $sets, $menu);
        $checker       = $this->resolvePermissionChecker($config['permission_checker']);
        $tree          = $this->buildTree($flat, $checker, $permissionContext);
        $this->markNodesWithChildren($tree);

        return $this->pruneEmptySections($tree);
    }

    /**
     * Legacy path: 1 query for menu (possibly N with context sets) + 1 for items. Used when findMenuAndItemsRaw is not available or returns null.
     *
     * @param list<array<string, bool|int|string>|null> $sets
     *
     * @return list<array{item: MenuItem, children: list<array>}>
     */
    private function loadTreeLegacy(string $menuCode, string $locale, mixed $permissionContext, array $sets): array
    {
        $menu = $this->menuRepository->findForCodeWithContextSets($menuCode, $sets);
        if (!$menu instanceof Menu) {
            return [];
        }
        $flat    = $this->menuItemRepository->findAllForMenuOrderedByTree($menu, $locale);
        $config  = $this->configResolver->getConfig($menuCode, $sets, $menu);
        $checker = $this->resolvePermissionChecker($config['permission_checker']);
        $tree    = $this->buildTree($flat, $checker, $permissionContext);
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
        $this->setMenuString($menu, 'classItem', $row['class_item'] ?? null);
        $this->setMenuString($menu, 'classLink', $row['class_link'] ?? null);
        $this->setMenuString($menu, 'classChildren', $row['class_children'] ?? null);
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
            $item->setLinkType((string) ($row['link_type'] ?? MenuItem::LINK_TYPE_ROUTE));
            $item->setRouteName(isset($row['route_name']) ? (string) $row['route_name'] : null);
            if (isset($row['route_params'])) {
                $item->setRouteParams(is_array($row['route_params']) ? $row['route_params'] : (json_decode((string) $row['route_params'], true) ?: null));
            }
            $item->setExternalUrl(isset($row['external_url']) ? (string) $row['external_url'] : null);
            $item->setPermissionKey(isset($row['permission_key']) ? (string) $row['permission_key'] : null);
            $item->setIcon(isset($row['icon']) ? (string) $row['icon'] : null);
            $item->setItemType((string) ($row['item_type'] ?? MenuItem::ITEM_TYPE_LINK));
            $item->setTargetBlank(isset($row['target_blank']) && (bool) $row['target_blank']);
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
     * @param list<array{item: MenuItem, children: list<array>, had_children?: bool}> $nodes
     */
    private function markNodesWithChildren(array $nodes): void
    {
        foreach ($nodes as &$node) {
            $this->markNodesWithChildren($node['children']);
            $node['had_children'] = count($node['children']) > 0;
        }
    }

    /**
     * Remove parents/section headers that have no visible children after permission filtering.
     *
     * @param list<array{item: MenuItem, children: list<array>, had_children?: bool}> $nodes
     *
     * @return list<array{item: MenuItem, children: list<array>}>
     */
    private function pruneEmptySections(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $children    = $this->pruneEmptySections($node['children']);
            $hadChildren = $node['had_children'] ?? false;
            if (count($children) > 0 || !$hadChildren) {
                $result[] = ['item' => $node['item'], 'children' => $children];
            }
        }

        return $result;
    }

    private function resolvePermissionChecker(?string $serviceId): MenuPermissionCheckerInterface
    {
        if ($serviceId !== null && $serviceId !== '' && $this->container->has($serviceId)) {
            $service = $this->container->get($serviceId);
            if ($service instanceof MenuPermissionCheckerInterface) {
                return $service;
            }
        }

        return $this->defaultPermissionChecker;
    }

    /**
     * Build nested tree from flat list (parent + position). Filters by permission.
     *
     * @param list<MenuItem> $flat
     *
     * @return list<array{item: MenuItem, children: list<array>}>
     */
    private function buildTree(array $flat, MenuPermissionCheckerInterface $checker, mixed $permissionContext): array
    {
        foreach ($flat as $item) {
            $this->normalizeItemIcon($item);
        }

        /** @var array<int, array{item: MenuItem, children: list<array>}> $map */
        $map = [];
        /** @var list<array{item: MenuItem, children: list<array>}> $roots */
        $roots = [];

        // First pass: create node entries for all visible items
        foreach ($flat as $item) {
            if (!$checker->canView($item, $permissionContext)) {
                continue;
            }
            $id            = $item->getId();
            $map[$id ?? 0] = ['item' => $item, 'children' => []];
        }

        // Second pass: wire parents/children using references so updates are shared
        foreach ($flat as $item) {
            if (!$checker->canView($item, $permissionContext)) {
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

    /**
     * Applies icon_library_prefix_map to the item's icon (e.g. bootstrap-icons:house → bi:house)
     * so the tree and data collector see the resolved value.
     */
    private function normalizeItemIcon(MenuItem $item): void
    {
        $item->setIcon($this->menuIconNameResolver->resolve($item->getIcon()));
    }
}
