<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuHubBlock;
use Nowo\DashboardMenuBundle\Model\MenuHubCard;
use Nowo\DashboardMenuBundle\Model\MenuHubCardExtras;
use Nowo\DashboardMenuBundle\Model\MenuHubSection;
use Nowo\DashboardMenuBundle\Model\MenuItemView;

use function array_filter;
use function array_push;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function str_replace;

/**
 * Builds a hub page from an area's menu tree (2.22), so the hub and the sidebar show the same permission-filtered
 * entries, labels and order (one source):
 * - each top-level section becomes a heading ({@see MenuHubSection}); top-level links outside sections are gathered
 *   in a heading without a label;
 * - inside it, consecutive links share one block, and each nested section (a group) is its own titled block
 *   ({@see MenuHubBlock});
 * - each link becomes a card ({@see MenuHubCard}); a link with children lists its children after it.
 *
 * Skipped: dividers, links without a usable href (`#`), the hub pages themselves (`hub.exclude_routes` and every
 * area's `home_route`), and headings or groups left without cards. Card extras come from the
 * {@see MenuHubCardExtrasProviderInterface} services, then `hub.cards`.
 *
 * Stateless (FrankenPHP worker mode).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuHubBuilder
{
    /** @var list<string> */
    private array $excludeRoutes;

    /**
     * @param iterable<MenuHubCardExtrasProviderInterface> $extrasProviders
     * @param array<string, array{intro?: string|null, testid?: string|null, tour?: string|null, badge?: string|null}> $cards `hub.cards`
     * @param list<string> $excludeRoutes `hub.exclude_routes`
     * @param string $testidPrefix prefix of the default card test id (`hub.testid_prefix`)
     */
    public function __construct(
        private MenuUrlResolver $urlResolver,
        private MenuAreaResolver $areas,
        private iterable $extrasProviders = [],
        private array $cards = [],
        array $excludeRoutes = [],
        private string $testidPrefix = 'dashboard-menu-hub-',
    ) {
        $exclude = $excludeRoutes;
        foreach ($this->areas->all() as $area) {
            if ($area->homeRoute !== null) {
                $exclude[] = $area->homeRoute;
            }
        }
        $this->excludeRoutes = $exclude;
    }

    /**
     * @param list<array<string, mixed>> $tree a menu tree (`dashboard_menu_tree()`)
     *
     * @return list<MenuHubSection>
     */
    public function build(array $tree): array
    {
        $sections = [];
        /** @var list<array<string, mixed>> $looseTop */
        $looseTop = [];
        foreach ($tree as $node) {
            $item = $node['item'] ?? null;
            if (!$item instanceof MenuItem) {
                continue;
            }
            if ($item->getItemType() !== MenuItem::ITEM_TYPE_SECTION) {
                $looseTop[] = $node;

                continue;
            }
            $this->flushLooseTop($sections, $looseTop);
            $blocks = $this->blocks($this->childNodes($node));
            if ($blocks !== []) {
                $sections[] = new MenuHubSection($item->getLabel(), $blocks);
            }
        }
        $this->flushLooseTop($sections, $looseTop);

        return $sections;
    }

    /**
     * @param list<MenuHubSection> $sections
     * @param list<array<string, mixed>> $looseTop
     */
    private function flushLooseTop(array &$sections, array &$looseTop): void
    {
        if ($looseTop === []) {
            return;
        }
        $blocks = $this->blocks($looseTop);
        if ($blocks !== []) {
            $sections[] = new MenuHubSection(null, $blocks);
        }
        $looseTop = [];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<MenuHubBlock>
     */
    private function blocks(array $nodes): array
    {
        $blocks = [];
        $loose  = [];
        foreach ($nodes as $child) {
            $childItem = $child['item'] ?? null;
            if (!$childItem instanceof MenuItem || $childItem->getItemType() !== MenuItem::ITEM_TYPE_SECTION) {
                array_push($loose, ...$this->cards([$child]));

                continue;
            }
            $cards = $this->cards($this->childNodes($child));
            if ($cards === []) {
                continue;
            }
            if ($loose !== []) {
                $blocks[] = new MenuHubBlock(null, $loose);
                $loose    = [];
            }
            $blocks[] = new MenuHubBlock($childItem->getLabel(), $cards);
        }
        if ($loose !== []) {
            $blocks[] = new MenuHubBlock(null, $loose);
        }

        return $blocks;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<MenuHubCard>
     */
    private function cards(array $nodes): array
    {
        $cards = [];
        foreach ($nodes as $node) {
            $item = $node['item'] ?? null;
            if (!$item instanceof MenuItem) {
                continue;
            }
            $type = $item->getItemType();
            if ($type === MenuItem::ITEM_TYPE_LINK || $type === MenuItem::ITEM_TYPE_SERVICE) {
                $card = $this->card($node, $item);
                if ($card instanceof MenuHubCard) {
                    $cards[] = $card;
                }
            }
            if ($type !== MenuItem::ITEM_TYPE_SECTION) {
                array_push($cards, ...$this->cards($this->childNodes($node)));
            }
        }

        return $cards;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function card(array $node, MenuItem $item): ?MenuHubCard
    {
        $route = $item->getRouteName();
        if (is_string($route) && in_array($route, $this->excludeRoutes, true)) {
            return null;
        }
        $href = is_string($node['href'] ?? null) ? $node['href'] : $this->urlResolver->getHref($item);
        if ($href === '' || $href === '#') {
            return null;
        }
        $view   = MenuItemView::fromEntity($item);
        $extras = $this->extras($view);
        $testid = $extras->testid ?? $this->testidPrefix . str_replace('_', '-', $route ?? ('item-' . ($item->getId() ?? 0)));

        return new MenuHubCard(
            item: $view,
            href: $href,
            testid: $testid,
            intro: $extras->intro,
            tour: $extras->tour,
            badge: $extras->badge,
            current: ($node['hasCurrentInBranch'] ?? false) === true,
        );
    }

    private function extras(MenuItemView $item): MenuHubCardExtras
    {
        foreach ($this->extrasProviders as $provider) {
            $extras = $provider->extrasFor($item);
            if ($extras instanceof MenuHubCardExtras) {
                return $extras;
            }
        }
        $config = $item->routeName === null ? null : ($this->cards[$item->routeName] ?? null);
        if ($config === null) {
            return new MenuHubCardExtras();
        }

        return new MenuHubCardExtras(
            intro: $this->nonEmpty($config['intro'] ?? null),
            testid: $this->nonEmpty($config['testid'] ?? null),
            tour: $this->nonEmpty($config['tour'] ?? null),
            badge: $this->nonEmpty($config['badge'] ?? null),
        );
    }

    private function nonEmpty(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return list<array<string, mixed>>
     */
    private function childNodes(array $node): array
    {
        $children = $node['children'] ?? [];

        return is_array($children) ? array_values(array_filter($children, is_array(...))) : [];
    }
}
