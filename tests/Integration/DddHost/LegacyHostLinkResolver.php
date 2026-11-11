<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

/**
 * Legacy (deprecated port) adapter of {@see HostMenuPolicy::projects()}.
 */
final class LegacyHostLinkResolver implements MenuLinkResolverInterface
{
    /**
     * @return list<array{label: string, href: string, position: int}>
     */
    public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): array
    {
        $params = $request?->attributes->get('_route_params');
        $tenant = is_array($params) ? ($params['tenant'] ?? null) : null;

        return HostMenuPolicy::projects(is_string($tenant) ? $tenant : null);
    }
}
