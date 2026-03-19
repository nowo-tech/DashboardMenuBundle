<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_string;

/**
 * Imports menus and items from a JSON-serializable array (exported by MenuExporter).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuImporter
{
    public const STRATEGY_SKIP_EXISTING = 'skip_existing';
    public const STRATEGY_REPLACE       = 'replace';

    public function __construct(
        private MenuRepository $menuRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Import from array. Expects either:
     * - { "menu": {...}, "items": [...] } for one menu
     * - { "menus": [ { "menu": {...}, "items": [...] }, ... ] } for multiple
     *
     * @param array<string, mixed> $data
     * @param self::STRATEGY_* $strategy skip_existing = do not overwrite menu with same code+context; replace = replace items of existing menu
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(array $data, string $strategy = self::STRATEGY_SKIP_EXISTING): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (isset($data['menus']) && is_array($data['menus'])) {
            foreach ($data['menus'] as $i => $block) {
                if (!is_array($block) || !isset($block['menu']) || !isset($block['items'])) {
                    $result['errors'][] = "Entry {$i}: missing 'menu' or 'items'.";
                    continue;
                }
                $menuData  = $block['menu'];
                $itemsData = is_array($block['items']) ? array_values($block['items']) : [];
                if (!is_array($menuData)) {
                    $result['errors'][] = "Entry {$i}: 'menu' must be an array.";
                    continue;
                }
                $this->importOne($menuData, $itemsData, $strategy, $result);
            }
        } elseif (isset($data['menu']) && is_array($data['menu']) && isset($data['items']) && is_array($data['items'])) {
            $this->importOne($data['menu'], array_values($data['items']), $strategy, $result);
        } else {
            $result['errors'][] = 'Invalid format: expected "menu" + "items" or "menus" array.';
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $menuData
     * @param list<array<string, mixed>> $itemsData Tree of items (each may have "children")
     * @param array{created: int, updated: int, skipped: int, errors: list<string>} $result
     */
    private function importOne(array $menuData, array $itemsData, string $strategy, array &$result): void
    {
        $code = isset($menuData['code']) && is_string($menuData['code']) ? trim($menuData['code']) : '';
        if ($code === '') {
            $result['errors'][] = 'Menu "code" is required.';

            return;
        }

        $context  = isset($menuData['context']) && is_array($menuData['context']) ? $menuData['context'] : null;
        $existing = $this->menuRepository->findOneByCodeAndContext($code, $context);

        if ($existing instanceof Menu) {
            if ($strategy === self::STRATEGY_SKIP_EXISTING) {
                ++$result['skipped'];

                return;
            }
            // Replace: remove existing items and re-import
            foreach ($existing->getItems() as $item) {
                $this->entityManager->remove($item);
            }
            $this->entityManager->flush();
            $menu = $existing;
            $this->applyMenuData($menu, $menuData);
            ++$result['updated'];
        } else {
            $menu = new Menu();
            $this->applyMenuData($menu, $menuData);
            $menu->setBase(false);
            $this->entityManager->persist($menu);
            $this->entityManager->flush();
            ++$result['created'];
        }

        $this->persistItemTree($itemsData, $menu, null, 0);
        $this->entityManager->flush();
        $this->clearLinkDataForLinkItemsWithChildren($menu);
        $this->entityManager->flush();
    }

    /**
     * When item type is "link" and the item has children, linkType (and route/external) must be null.
     */
    private function clearLinkDataForLinkItemsWithChildren(Menu $menu): void
    {
        foreach ($menu->getItems() as $item) {
            if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK && $item->getChildren()->count() > 0) {
                $item->setLinkType(null);
                $item->setRouteName(null);
                $item->setRouteParams(null);
                $item->setExternalUrl(null);
            }
        }
    }

    /**
     * @param array<string, mixed> $menuData
     */
    private function applyMenuData(Menu $menu, array $menuData): void
    {
        $menu->setCode($menuData['code'] ?? '');
        if (isset($menuData['name']) && is_string($menuData['name'])) {
            $menu->setName(trim($menuData['name']) ?: null);
        }
        if (array_key_exists('context', $menuData)) {
            $menu->setContext(is_array($menuData['context']) ? $menuData['context'] : null);
        }
        $menu->setIcon($this->stringOrNull($menuData['icon'] ?? null));
        $menu->setClassMenu($this->stringOrNull($menuData['classMenu'] ?? null));
        $menu->setClassItem($this->stringOrNull($menuData['classItem'] ?? null));
        $menu->setClassLink($this->stringOrNull($menuData['classLink'] ?? null));
        $menu->setClassChildren($this->stringOrNull($menuData['classChildren'] ?? null));
        $menu->setClassSectionLabel($this->stringOrNull($menuData['classSectionLabel'] ?? null));
        $menu->setClassCurrent($this->stringOrNull($menuData['classCurrent'] ?? null));
        $menu->setClassBranchExpanded($this->stringOrNull($menuData['classBranchExpanded'] ?? null));
        $menu->setClassHasChildren($this->stringOrNull($menuData['classHasChildren'] ?? null));
        $menu->setClassExpanded($this->stringOrNull($menuData['classExpanded'] ?? null));
        $menu->setClassCollapsed($this->stringOrNull($menuData['classCollapsed'] ?? null));
        $menu->setPermissionChecker($this->stringOrNull($menuData['permissionChecker'] ?? null));
        $menu->setDepthLimit($this->intOrNull($menuData['depthLimit'] ?? null));
        $menu->setCollapsible($this->boolOrNull($menuData['collapsible'] ?? null));
        $menu->setCollapsibleExpanded($this->boolOrNull($menuData['collapsibleExpanded'] ?? null));
        $menu->setNestedCollapsible($this->boolOrNull($menuData['nestedCollapsible'] ?? null));
        $menu->setNestedCollapsibleSections($this->boolOrNull($menuData['nestedCollapsibleSections'] ?? null));
        if (array_key_exists('base', $menuData)) {
            $menu->setBase(!empty($menuData['base']));
        }
    }

    /**
     * @param list<array<string, mixed>> $itemsData
     */
    private function persistItemTree(array $itemsData, Menu $menu, ?MenuItem $parent, int $basePosition): void
    {
        $position = $basePosition;
        foreach ($itemsData as $row) {
            $item = new MenuItem();
            $item->setMenu($menu);
            $item->setParent($parent);
            $item->setPosition($position++);
            $item->setLabel(is_string($row['label'] ?? '') ? $row['label'] : '');
            $item->setTranslations($this->translationsFromRow($row));
            $item->setLinkType($this->stringOrDefault($row['linkType'] ?? null, MenuItem::LINK_TYPE_ROUTE));
            $item->setRouteName($this->stringOrNull($row['routeName'] ?? null));
            $item->setRouteParams($this->routeParamsFromRow($row));
            $item->setExternalUrl($this->stringOrNull($row['externalUrl'] ?? null));
            $item->setIcon($this->stringOrNull($row['icon'] ?? null));
            $item->setPermissionKey($this->stringOrNull($row['permissionKey'] ?? null));
            $item->setItemType($this->stringOrDefault($row['itemType'] ?? null, MenuItem::ITEM_TYPE_LINK));
            $item->setTargetBlank(!empty($row['targetBlank']));
            $this->entityManager->persist($item);
            $this->entityManager->flush();

            $children = isset($row['children']) && is_array($row['children']) ? array_values($row['children']) : [];
            if ($children !== []) {
                $this->persistItemTree($children, $menu, $item, 0);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, string>|null
     */
    private function translationsFromRow(array $row): ?array
    {
        if (!isset($row['translations']) || !is_array($row['translations'])) {
            return null;
        }
        $out = [];
        foreach ($row['translations'] as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $out[$k] = $v;
            }
        }

        return $out ?: null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    private function routeParamsFromRow(array $row): ?array
    {
        if (!isset($row['routeParams']) || !is_array($row['routeParams'])) {
            return null;
        }

        return $row['routeParams'];
    }

    private function stringOrNull(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_string($v) ? $v : (string) $v;
    }

    private function stringOrDefault(mixed $v, string $default): string
    {
        $s = $this->stringOrNull($v);

        return $s ?? $default;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_int($v) ? $v : (int) $v;
    }

    private function boolOrNull(mixed $v): ?bool
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (bool) $v;
    }
}
