<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuHubCardExtras;
use Nowo\DashboardMenuBundle\Model\MenuItemView;

/**
 * Hub-only extras of a card (intro, test id, tour id, badge key) computed by the host (2.22), for extras that a static
 * `nowo_dashboard_menu.hub.cards` entry cannot express. Implementations are tagged
 * `nowo_dashboard_menu.hub_card_extras_provider` automatically (autoconfigure); the first one that returns extras
 * wins, then the configured `hub.cards` entry of the item's route applies.
 *
 * Implementations must not keep per-request state in properties (FrankenPHP worker mode).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuHubCardExtrasProviderInterface
{
    /** Extras for the card of this menu item, or null to let the next provider (then the configuration) decide. */
    public function extrasFor(MenuItemView $item): ?MenuHubCardExtras;
}
