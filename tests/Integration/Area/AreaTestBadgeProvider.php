<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Area;

use Nowo\DashboardMenuBundle\Model\MenuHubCard;
use Nowo\DashboardMenuBundle\Service\MenuHubBadgeProviderInterface;

/**
 * Counts `mail_failed` as 3; knows no other badge.
 */
final class AreaTestBadgeProvider implements MenuHubBadgeProviderInterface
{
    public function badgeCount(string $badge, MenuHubCard $card): ?int
    {
        return $badge === 'mail_failed' ? 3 : null;
    }
}
