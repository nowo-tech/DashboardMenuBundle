<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;

use function array_key_exists;
use function count;
use function in_array;
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
        private MenuItemRepository $menuItemRepository,
        private MenuRepository $menuRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Import from array. Expects either:
     * - { "menu": {...}, "items": [...] } for one menu
     * - { "menus": [ { "menu": {...}, "items": [...] }, ... ] } for multiple
     * - [ { "menu": {...}, "items": [...] }, ... ] — same as "menus", as a root JSON array (non-empty)
     *
     * @param array<string|int, mixed> $data
     * @param self::STRATEGY_* $strategy skip_existing = do not overwrite menu with same code+context; replace = replace items of existing menu
     *
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function import(array $data, string $strategy = self::STRATEGY_SKIP_EXISTING): array
    {
        $data   = self::normalizeImportPayload($data);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (isset($data['menus']) && is_array($data['menus'])) {
            /** @var array<string, true> $seenMenuKeys code+context signatures already imported in this payload */
            $seenMenuKeys = [];
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
                $code = isset($menuData['code']) && is_string($menuData['code']) ? trim($menuData['code']) : '';
                if ($code === '') {
                    $result['errors'][] = "Entry {$i}: menu \"code\" is required.";
                    continue;
                }
                $ctx = $this->menuContextFromMenuData($menuData);
                $sig = $code . "\0" . Menu::canonicalContextKey($ctx);
                if (isset($seenMenuKeys[$sig])) {
                    // Same menu+context appears twice in one file (e.g. merged exports). Import once only.
                    continue;
                }
                $seenMenuKeys[$sig] = true;
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
     * Normalizes a root-level JSON array of menu blocks to {"menus": [...]} so importers can use
     * either an object with a "menus" key or a literal JSON array of exports.
     * Empty [] is left unchanged (still invalid for import).
     *
     * @param array<string|int, mixed> $data
     *
     * @return array<string|int, mixed>
     */
    public static function normalizeImportPayload(array $data): array
    {
        if ($data === [] || !array_is_list($data)) {
            return $data;
        }
        foreach ($data as $block) {
            if (!is_array($block) || !isset($block['menu'], $block['items'])) {
                return $data;
            }
        }

        return ['menus' => array_values($data)];
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

        $context  = $this->menuContextFromMenuData($menuData);
        $existing = $this->menuRepository->findOneByCodeAndContext($code, $context);

        if ($existing instanceof Menu) {
            if ($strategy === self::STRATEGY_SKIP_EXISTING) {
                ++$result['skipped'];

                return;
            }
            // Replace: remove existing items and re-import.
            // Use repository lookup to guarantee we remove the full tree even when
            // the ORM collection isn't fully initialized in this request context.
            $existingItems = $this->menuItemRepository->findAllForMenuOrderedByTreeForExport($existing);
            foreach ($existingItems as $item) {
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
        $this->reindexImportedPositionsWithinSiblingGroupsIfDuplicates($menu);
    }

    /**
     * Resolves menu context from import JSON (same rules as export: missing key => null; only arrays accepted).
     *
     * @param array<string, mixed> $menuData
     */
    /**
     * @param array<string, mixed> $menuData
     *
     * @return array<string, bool|int|string>|null
     */
    private function menuContextFromMenuData(array $menuData): ?array
    {
        if (!isset($menuData['context']) || !is_array($menuData['context'])) {
            return null;
        }

        return $menuData['context'];
    }

    /**
     * When item type is "link" and the item has children, linkType (and route/external) must be null.
     */
    private function clearLinkDataForLinkItemsWithChildren(Menu $menu): void
    {
        // Use repository query (menu->getItems may not be populated yet during import).
        $items          = $this->menuItemRepository->findAllForMenuOrderedByTreeForExport($menu);
        $hasChildrenMap = [];
        foreach ($items as $item) {
            $parentId = $item->getParent()?->getId();
            if ($parentId !== null) {
                $hasChildrenMap[$parentId] = true;
            }
        }

        foreach ($items as $item) {
            $itemId = $item->getId();
            if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK && $itemId !== null && isset($hasChildrenMap[$itemId])) {
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
        $menu->setUlId($this->stringOrNull($menuData['ulId'] ?? null));
        $menu->setClassItem($this->stringOrNull($menuData['classItem'] ?? null));
        $menu->setClassLink($this->stringOrNull($menuData['classLink'] ?? null));
        $menu->setClassChildren($this->stringOrNull($menuData['classChildren'] ?? null));
        $menu->setClassSectionLabel($this->stringOrNull($menuData['classSectionLabel'] ?? null));
        $menu->setClassSection($this->stringOrNull($menuData['classSection'] ?? null));
        $menu->setClassDivider($this->stringOrNull($menuData['classDivider'] ?? null));
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

            // If `position` is provided in the JSON, honor it; otherwise fall back to
            // the sequential `basePosition` used for the sibling list ordering.
            $rowPosition = array_key_exists('position', $row) ? $this->intOrNull($row['position']) : null;
            if ($rowPosition !== null) {
                $item->setPosition($rowPosition);
                // Keep a sensible pointer for subsequent siblings without `position`.
                $position = $rowPosition + 1;
            } else {
                $item->setPosition($position++);
            }

            $item->setLabel(is_string($row['label'] ?? '') ? $row['label'] : '');
            $item->setTranslations($this->translationsFromRow($row));
            $item->setLinkType($this->stringOrDefault($row['linkType'] ?? null, MenuItem::LINK_TYPE_ROUTE));
            $item->setRouteName($this->stringOrNull($row['routeName'] ?? null));
            $item->setRouteParams($this->routeParamsFromRow($row));
            $item->setExternalUrl($this->stringOrNull($row['externalUrl'] ?? null));
            $item->setIcon($this->stringOrNull($row['icon'] ?? null));
            $item->setPermissionKeys($this->permissionKeysFromRow($row));
            $item->setIsUnanimous($this->boolOrDefault($row['isUnanimous'] ?? null, true));
            $item->setItemType($this->stringOrDefault($row['itemType'] ?? null, MenuItem::ITEM_TYPE_LINK));
            $item->setTargetBlank(!empty($row['targetBlank']));
            $this->entityManager->persist($item);

            $children = isset($row['children']) && is_array($row['children']) ? array_values($row['children']) : [];
            if ($children !== []) {
                $this->persistItemTree($children, $menu, $item, 0);
            }
        }
    }

    /**
     * Ensures ordering is deterministic after importing by reindexing sibling groups
     * when duplicates are found (common when importing data that had all `position = 0`).
     */
    private function reindexImportedPositionsWithinSiblingGroupsIfDuplicates(Menu $menu): void
    {
        $items = $this->menuItemRepository->findAllForMenuOrderedByTreeForExport($menu);
        if ($items === []) {
            return;
        }

        /** @var array<string, list<MenuItem>> $byParent */
        $byParent = [];
        foreach ($items as $item) {
            $pid = $item->getParent()?->getId();
            $key = $pid !== null ? (string) $pid : '__root';
            if (!isset($byParent[$key])) {
                $byParent[$key] = [];
            }
            $byParent[$key][] = $item;
        }

        $changed = false;
        foreach ($byParent as $siblings) {
            $unique = [];
            foreach ($siblings as $sibling) {
                $unique[$sibling->getPosition()] = true;
            }

            if (count($unique) === count($siblings)) {
                continue; // already unique
            }

            foreach ($siblings as $i => $sibling) {
                $sibling->setPosition($i);
                $changed = true;
            }
        }

        if ($changed) {
            $this->entityManager->flush();
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

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>|null
     */
    private function permissionKeysFromRow(array $row): ?array
    {
        if (isset($row['permissionKeys']) && is_array($row['permissionKeys'])) {
            $out = [];
            foreach ($row['permissionKeys'] as $v) {
                if (!is_string($v)) {
                    continue;
                }
                $k = trim($v);
                if ($k === '' || in_array($k, $out, true)) {
                    continue;
                }
                $out[] = $k;
            }

            return $out !== [] ? $out : null;
        }

        $single = $this->stringOrNull($row['permissionKey'] ?? null);

        return $single !== null ? [$single] : null;
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

    private function boolOrDefault(mixed $v, bool $default): bool
    {
        $parsed = $this->boolOrNull($v);

        return $parsed ?? $default;
    }
}
