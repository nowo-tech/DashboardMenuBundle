<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Model;

use Nowo\DashboardMenuBundle\Service\MenuHubBadgeProviderInterface;
use Nowo\DashboardMenuBundle\Service\MenuHubCardExtrasProviderInterface;

/**
 * Hub-only extras of one card (2.22): what the menu item does not hold. The card title, link and order always come
 * from the menu item. Returned by {@see MenuHubCardExtrasProviderInterface} or built from `nowo_dashboard_menu.hub.cards`.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class MenuHubCardExtras
{
    /**
     * @param string|null $intro intro text or translation key shown under the title
     * @param string|null $testid `data-testid` of the card (null = `hub.testid_prefix` + route with dashes)
     * @param string|null $tour `data-tour` of the card (product tours)
     * @param string|null $badge badge key resolved by {@see MenuHubBadgeProviderInterface}
     */
    public function __construct(
        public ?string $intro = null,
        public ?string $testid = null,
        public ?string $tour = null,
        public ?string $badge = null,
    ) {
    }
}
