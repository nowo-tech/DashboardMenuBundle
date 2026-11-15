<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Area;

use Nowo\DashboardMenuBundle\Model\MenuHubCardExtras;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Service\MenuHubCardExtrasProviderInterface;

/**
 * Gives Appearance an unknown badge key (no provider counts it: no badge).
 */
final class AreaTestExtrasProvider implements MenuHubCardExtrasProviderInterface
{
    public function extrasFor(MenuItemView $item): ?MenuHubCardExtras
    {
        return $item->routeName === 'admin_appearance' ? new MenuHubCardExtras(intro: 'Brand and colours', badge: 'unknown') : null;
    }
}
