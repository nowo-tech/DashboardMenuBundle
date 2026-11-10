<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Util\ExternalUrlPolicy;

use function is_array;
use function is_string;

/**
 * Immutable node of a menu tree built from {@see MenuItemView}s, for hosts that consume the tree outside Twig
 * (API, SPA, another UI) without handling the bundle's entities.
 *
 * `href`, `current` and `currentInBranch` are filled when the source tree was decorated by
 * {@see CurrentRouteTreeDecorator} (e.g. the `dashboard_menu_tree()` Twig function);
 * for a plain {@see MenuTreeLoader::loadTree()} tree they stay null/false.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuNodeView
{
    /**
     * @param list<MenuNodeView> $children
     */
    public function __construct(
        public MenuItemView $item,
        public array $children = [],
        public ?string $href = null,
        public bool $current = false,
        public bool $currentInBranch = false,
    ) {
    }

    /**
     * Converts a tree returned by {@see MenuTreeLoader::loadTree()} (optionally
     * decorated) into a list of nodes. Rows without a {@see MenuItem} under `item` are skipped.
     *
     * @param array<int, mixed> $tree
     *
     * @return list<MenuNodeView>
     */
    public static function listFromTree(array $tree): array
    {
        $nodes = [];
        foreach ($tree as $node) {
            if (!is_array($node) || !($node['item'] ?? null) instanceof MenuItem) {
                continue;
            }

            $children = $node['children'] ?? [];
            $href     = $node['href'] ?? null;
            $nodes[]  = new self(
                MenuItemView::fromEntity($node['item']),
                is_array($children) ? self::listFromTree($children) : [],
                is_string($href) ? ExternalUrlPolicy::sanitize($href) : null,
                ($node['isCurrent'] ?? false) === true,
                ($node['hasCurrentInBranch'] ?? false) === true,
            );
        }

        return $nodes;
    }
}
