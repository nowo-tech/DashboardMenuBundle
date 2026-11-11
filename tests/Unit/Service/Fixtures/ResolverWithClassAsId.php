<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;

/**
 * Link resolver registered under its class name (resource loading).
 */
final class ResolverWithClassAsId implements MenuItemContextLinkResolverInterface
{
    public function resolveItemHrefFor(MenuItemView $item, ?MenuRequestContext $request, MenuPermissionContext $context): string
    {
        return '/x';
    }
}
