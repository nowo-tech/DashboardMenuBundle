<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_string;

/**
 * Legacy (deprecated port) adapter of {@see HostMenuPolicy}: entity + `mixed` context (the Request).
 */
final class LegacyHostChecker implements MenuPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $route = $context instanceof Request ? $context->attributes->get('_route') : null;

        return HostMenuPolicy::allows($item->getPermissionKeys() ?? [], $item->isUnanimous(), is_string($route) ? $route : null);
    }
}
