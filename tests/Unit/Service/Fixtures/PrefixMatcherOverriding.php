<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;

/**
 * Route-prefix matcher that overrides the new method: it is never called while the deprecated one exists.
 */
final class PrefixMatcherOverriding extends AbstractRoutePrefixMenuCurrentMatcher
{
    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool
    {
        return false;
    }

    protected function routePrefixes(): array
    {
        return [];
    }
}
