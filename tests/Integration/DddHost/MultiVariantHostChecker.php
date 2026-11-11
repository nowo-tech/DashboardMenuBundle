<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;

/**
 * A host checker in the middle of its migration: the deprecated method applies {@see HostMenuPolicy}, the new one
 * refuses everything. The bundle calls the oldest variant, so the rendered tree is the legacy one.
 */
final class MultiVariantHostChecker implements MenuPermissionCheckerInterface, MenuItemContextPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return (new LegacyHostChecker())->canView($item, $context);
    }

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return false;
    }
}
