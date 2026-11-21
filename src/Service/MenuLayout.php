<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

use function array_filter;
use function array_values;
use function in_array;

/**
 * Menu layout (2.26.0): footer slot and per-section collapse, read from `nowo_dashboard_menu.menu_layout`.
 *
 * Stateless and read-only (safe in FrankenPHP worker mode): every method is a pure function of the configuration it
 * was built with and of the tree it receives. Menus without a `menu_layout` entry are untouched.
 *
 * @phpstan-type Selector array{id?: int|null, route?: string|null, label?: string|null, collapsible?: bool, collapsed?: bool}
 * @phpstan-type MenuLayoutEntry array{storage_key?: string, footer?: list<Selector>, sections?: list<Selector>}
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuLayout
{
    public const DEFAULT_STORAGE_KEY = 'nowo.dashboardMenu.sectionCollapse';

    /**
     * @param array<string, MenuLayoutEntry> $layout menu code => layout entry
     */
    public function __construct(private array $layout = [])
    {
    }

    /**
     * Tree without the footer items.
     *
     * @param list<array<string, mixed>> $tree
     *
     * @return list<array<string, mixed>>
     */
    public function main(array $tree, string $menuCode): array
    {
        $selectors = $this->layout[$menuCode]['footer'] ?? [];
        if ($selectors === []) {
            return $tree;
        }

        return array_values(array_filter($tree, fn (array $node): bool => !$this->matchesAny($node['item'] ?? null, $selectors)));
    }

    /**
     * The top-level nodes pinned in the footer slot (with their children), in tree order.
     *
     * @param list<array<string, mixed>> $tree
     *
     * @return list<array<string, mixed>>
     */
    public function footer(array $tree, string $menuCode): array
    {
        $selectors = $this->layout[$menuCode]['footer'] ?? [];
        if ($selectors === []) {
            return [];
        }

        return array_values(array_filter($tree, fn (array $node): bool => $this->matchesAny($node['item'] ?? null, $selectors)));
    }

    /**
     * Collapse options of a section, or null when the section is not configured as collapsible.
     *
     * @return array{collapsible: true, collapsed: bool, storage_key: string, key: string}|null
     */
    public function section(mixed $item, string $menuCode): ?array
    {
        if (!$item instanceof MenuItem || $item->getItemType() !== MenuItem::ITEM_TYPE_SECTION) {
            return null;
        }

        $entry = $this->layout[$menuCode] ?? null;
        if ($entry === null) {
            return null;
        }
        foreach ($entry['sections'] ?? [] as $selector) {
            if (!$this->matches($item, $selector)) {
                continue;
            }
            if (($selector['collapsible'] ?? true) !== true) {
                return null;
            }

            return [
                'collapsible' => true,
                'collapsed'   => ($selector['collapsed'] ?? false) === true,
                'storage_key' => $entry['storage_key'] ?? self::DEFAULT_STORAGE_KEY,
                'key'         => $menuCode . ':' . $item->getId(),
            ];
        }

        return null;
    }

    /**
     * @param list<Selector> $selectors
     */
    private function matchesAny(mixed $item, array $selectors): bool
    {
        if (!$item instanceof MenuItem) {
            return false;
        }
        foreach ($selectors as $selector) {
            if ($this->matches($item, $selector)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A selector matches when every key it sets matches the item.
     *
     * @param Selector $selector
     */
    private function matches(MenuItem $item, array $selector): bool
    {
        $id = $selector['id'] ?? null;
        if ($id !== null && $item->getId() !== $id) {
            return false;
        }
        $route = $selector['route'] ?? null;
        if ($route !== null && $item->getRouteName() !== $route) {
            return false;
        }
        $label = $selector['label'] ?? null;

        return $label === null || in_array($label, $this->labels($item), true);
    }

    /**
     * @return list<string>
     */
    private function labels(MenuItem $item): array
    {
        $labels = [$item->getLabel()];
        foreach ($item->getTranslations() ?? [] as $translation) {
            $labels[] = $translation;
        }

        return $labels;
    }
}
