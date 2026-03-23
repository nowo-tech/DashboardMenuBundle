<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

use function array_key_exists;

/**
 * Exports menus and their items to a JSON-serializable array (config + links).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuExporter
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuItemRepository $menuItemRepository,
    ) {
    }

    /**
     * Export one menu (config + items tree) to array. No IDs.
     *
     * @return array{menu: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function exportMenu(Menu $menu): array
    {
        $items = $this->menuItemRepository->findAllForMenuOrderedByTreeForExport($menu);
        $tree  = $this->buildItemTree($items);

        return [
            'menu'  => $this->menuToArray($menu),
            'items' => $tree,
        ];
    }

    /**
     * Export all menus (config + items) to array.
     *
     * @return array{menus: list<array{menu: array<string, mixed>, items: list<array<string, mixed>>}>}
     */
    public function exportAll(): array
    {
        $menus       = $this->menuRepository->findAll();
        $itemsByMenu = $this->menuItemRepository->findAllForMenusOrderedByTreeForExport($menus);
        $out         = [];
        foreach ($menus as $menu) {
            $menuId = $menu->getId();
            $items  = $menuId !== null ? ($itemsByMenu[$menuId] ?? []) : [];
            $tree   = $this->buildItemTree($items);
            $out[]  = [
                'menu'  => $this->menuToArray($menu),
                'items' => $tree,
            ];
        }

        return ['menus' => $out];
    }

    /**
     * @return array<string, mixed>
     */
    private function menuToArray(Menu $menu): array
    {
        $data = array_filter([
            'code'                      => $menu->getCode(),
            'name'                      => $menu->getName(),
            'context'                   => $menu->getContext(),
            'icon'                      => $menu->getIcon(),
            'classMenu'                 => $menu->getClassMenu(),
            'ulId'                      => $menu->getUlId(),
            'classItem'                 => $menu->getClassItem(),
            'classLink'                 => $menu->getClassLink(),
            'classChildren'             => $menu->getClassChildren(),
            'classSectionLabel'         => $menu->getClassSectionLabel(),
            'classCurrent'              => $menu->getClassCurrent(),
            'classBranchExpanded'       => $menu->getClassBranchExpanded(),
            'classHasChildren'          => $menu->getClassHasChildren(),
            'classExpanded'             => $menu->getClassExpanded(),
            'classCollapsed'            => $menu->getClassCollapsed(),
            'permissionChecker'         => $menu->getPermissionChecker(),
            'depthLimit'                => $menu->getDepthLimit(),
            'collapsible'               => $menu->getCollapsible(),
            'collapsibleExpanded'       => $menu->getCollapsibleExpanded(),
            'nestedCollapsible'         => $menu->getNestedCollapsible(),
            'nestedCollapsibleSections' => $menu->getNestedCollapsibleSections(),
            'base'                      => $menu->isBase(),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        // Keep permission metadata keys always present in exports to avoid
        // ambiguity for downstream importers/processors.
        if (!array_key_exists('permissionChecker', $data)) {
            $data['permissionChecker'] = $menu->getPermissionChecker();
        }

        return $data;
    }

    /**
     * @param list<MenuItem> $flatItems Flat list ordered by parent then position
     *
     * @return list<array<string, mixed>>
     */
    private function buildItemTree(array $flatItems): array
    {
        /** @var array<string, list<MenuItem>> $byParent */
        $byParent = [];
        foreach ($flatItems as $item) {
            $pid = $item->getParent()?->getId();
            $key = $pid !== null ? (string) $pid : '__root';
            if (!isset($byParent[$key])) {
                $byParent[$key] = [];
            }
            $byParent[$key][] = $item;
        }

        return $this->buildChildren($byParent['__root'] ?? [], $byParent);
    }

    /**
     * @param list<MenuItem> $siblings
     * @param array<int|string, list<MenuItem>> $byParent
     *
     * @return list<array<string, mixed>>
     */
    private function buildChildren(array $siblings, array $byParent): array
    {
        $out = [];
        foreach ($siblings as $item) {
            $id       = $item->getId();
            $key      = $id !== null ? (string) $id : '';
            $children = $this->buildChildren($byParent[$key] ?? [], $byParent);
            $out[]    = $this->itemToArray($item, $children);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private function itemToArray(MenuItem $item, array $children): array
    {
        $data = [
            'label'         => $item->getLabel(),
            'translations'  => $item->getTranslations(),
            'linkType'      => $item->getLinkType(),
            'routeName'     => $item->getRouteName(),
            'routeParams'   => $item->getRouteParams(),
            'externalUrl'   => $item->getExternalUrl(),
            'icon'          => $item->getIcon(),
            'permissionKey' => $item->getPermissionKey(),
            'permissionKeys' => $item->getPermissionKeys(),
            'isUnanimous'   => $item->isUnanimous(),
            'itemType'      => $item->getItemType(),
            'targetBlank'   => $item->getTargetBlank(),
            'position'      => $item->getPosition(),
        ];
        $data = array_filter($data, static fn (mixed $v): bool => $v !== null && $v !== '');
        if (!array_key_exists('permissionKey', $data)) {
            $data['permissionKey'] = $item->getPermissionKey();
        }
        if (!array_key_exists('permissionKeys', $data)) {
            $data['permissionKeys'] = $item->getPermissionKeys();
        }
        if (!array_key_exists('isUnanimous', $data)) {
            $data['isUnanimous'] = $item->isUnanimous();
        }
        if ($children !== []) {
            $data['children'] = $children;
        }

        return $data;
    }
}
