<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuHubCard;

/**
 * Resolves the number shown as a hub card's badge (2.22), e.g. open support threads. A card asks only when its extras
 * name a badge key (`hub.cards.<route>.badge`). Implementations are tagged `nowo_dashboard_menu.hub_badge_provider`
 * automatically (autoconfigure); the first one that returns a number wins; no number (or 0) shows no badge.
 *
 * Called while the hub renders: compute per call, never keep per-request state in properties (FrankenPHP worker mode).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuHubBadgeProviderInterface
{
    /**
     * @param string $badge the badge key of the card
     *
     * @return int|null the count, or null when this provider does not know the key
     */
    public function badgeCount(string $badge, MenuHubCard $card): ?int;
}
