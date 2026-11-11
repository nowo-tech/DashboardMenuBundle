<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;

/**
 * Host checker implementing the deprecated and the new port.
 */
final class DualHostChecker implements MenuPermissionCheckerInterface, MenuItemContextPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return false;
    }
}
