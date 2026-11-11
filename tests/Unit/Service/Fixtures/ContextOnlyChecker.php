<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;

/**
 * Value-object checker: hides keyed items unless the context names a tenant.
 */
final class ContextOnlyChecker implements MenuItemContextPermissionCheckerInterface
{
    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return $item->permissionKeys === [] || $context->hasAttribute('tenant');
    }
}
