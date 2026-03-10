<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Psr\Container\ContainerInterface;

use function count;

/**
 * Loads menu tree in one DB query (with Translatable hint), builds nested structure, applies per-menu permission filter.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuTreeLoader
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuItemRepository $menuItemRepository,
        private MenuConfigResolver $configResolver,
        private ContainerInterface $container,
        private AllowAllMenuPermissionChecker $defaultPermissionChecker,
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
        $menu = $this->menuRepository->findForCodeWithContextSets($menuCode, $sets);
        if (!$menu instanceof \Nowo\DashboardMenuBundle\Entity\Menu) {
            return [];
        }

        $flat    = $this->menuItemRepository->findAllForMenuOrderedByTree($menu, $locale);
        $config  = $this->configResolver->getConfig($menuCode, $sets);
        $checker = $this->resolvePermissionChecker($config['permission_checker']);

        /** @var list<array{item: MenuItem, children: list<array>}> $tree */
        $tree = $this->buildTree($flat, $checker, $permissionContext);
        $this->markNodesWithChildren($tree);

        return $this->pruneEmptySections($tree);
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
        $map   = [];
        $roots = [];

        foreach ($flat as $item) {
            if (!$checker->canView($item, $permissionContext)) {
                continue;
            }
            $map[$item->getId()] = ['item' => $item, 'children' => []];
        }

        foreach ($flat as $item) {
            if (!$checker->canView($item, $permissionContext)) {
                continue;
            }
            $node   = $map[$item->getId()];
            $parent = $item->getParent();
            if ($parent === null) {
                $roots[] = $node;
            } else {
                $parentId = $parent->getId();
                if (isset($map[$parentId])) {
                    $map[$parentId]['children'][] = $node;
                } else {
                    $roots[] = $node;
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
}
