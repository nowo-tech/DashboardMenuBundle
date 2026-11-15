<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

/**
 * One card of a hub page (2.22): a link of the area's menu tree, with its hub-only extras.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuHubCard
{
    /**
     * @param MenuItemView $item the menu item (label, icon, route, permission keys)
     * @param string $href resolved link (absolute path)
     * @param string $testid `data-testid` of the card
     * @param string|null $intro intro text or translation key
     * @param string|null $tour `data-tour` of the card
     * @param string|null $badge badge key (see `dashboard_menu_hub_badge()`)
     * @param bool $current whether the item is the current page (or holds it)
     */
    public function __construct(
        public MenuItemView $item,
        public string $href,
        public string $testid,
        public ?string $intro = null,
        public ?string $tour = null,
        public ?string $badge = null,
        public bool $current = false,
    ) {
    }

    /** The card title: the menu item's label for the rendered locale. */
    public function label(): string
    {
        return $this->item->label;
    }

    /** The menu item's route, or null for a URL / service link. */
    public function route(): ?string
    {
        return $this->item->routeName;
    }
}
