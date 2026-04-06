<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

use function is_array;
use function is_string;

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
            'items' => $tree,
            'menu'  => $this->menuToArray($menu),
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
        $data = [
            'code'                      => $menu->getCode(),
            'name'                      => $menu->getName(),
            'context'                   => $menu->getContext(),
            'icon'                      => $menu->getIcon(),
            'classMenu'                 => $menu->getClassMenu(),
            'ulId'                      => $menu->getUlId(),
            'classItem'                 => $menu->getClassItem(),
            'classLink'                 => $menu->getClassLink(),
            'classChildren'             => $menu->getClassChildren(),
            'classSectionChildren'      => $menu->getClassSectionChildren(),
            'classSectionChildItem'     => $menu->getClassSectionChildItem(),
            'classSectionChildLink'     => $menu->getClassSectionChildLink(),
            'classSectionLabel'         => $menu->getClassSectionLabel(),
            'classSection'              => $menu->getClassSection(),
            'classDivider'              => $menu->getClassDivider(),
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
        ];

        return $this->sortAssociativeKeysRecursive($this->normalizeAssociativeForExport($data));
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
            'label'              => $item->getLabel(),
            'translations'       => $item->getTranslations(),
            'linkType'           => $item->getLinkType(),
            'routeName'          => $item->getRouteName(),
            'routeParams'        => $item->getRouteParams(),
            'externalUrl'        => $item->getExternalUrl(),
            'icon'               => $item->getIcon(),
            'permissionKeys'     => $item->getPermissionKeys(),
            'isUnanimous'        => $item->isUnanimous(),
            'itemType'           => $item->getItemType(),
            'linkResolver'       => $item->getItemType() === MenuItem::ITEM_TYPE_SERVICE ? $item->getLinkResolver() : null,
            'targetBlank'        => $item->getTargetBlank(),
            'sectionCollapsible' => $item->getItemType() === MenuItem::ITEM_TYPE_SECTION ? $item->getSectionCollapsible() : null,
            'position'           => $item->getPosition(),
            'children'           => $children !== [] ? $children : null,
        ];

        return $this->sortAssociativeKeysRecursive($this->normalizeAssociativeForExport($data));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeAssociativeForExport(array $data): array
    {
        $normalized = [];
        foreach ($data as $k => $v) {
            $normalized[$k] = $this->normalizeValueForExport($v);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function sortAssociativeKeysRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $list = [];
                    foreach ($value as $entry) {
                        $list[] = is_array($entry) && !array_is_list($entry)
                            ? $this->sortAssociativeKeysRecursive($entry)
                            : $entry;
                    }
                    $data[$key] = $list;
                    continue;
                }

                $data[$key] = $this->sortAssociativeKeysRecursive($value);
            }
        }

        return $data;
    }

    private function normalizeValueForExport(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value === '' ? null : $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return null;
        }

        if (array_is_list($value)) {
            $normalizedList = [];
            foreach ($value as $entry) {
                $normalizedList[] = $this->normalizeValueForExport($entry);
            }

            return $normalizedList;
        }

        return $this->normalizeAssociativeForExport($value);
    }
}
