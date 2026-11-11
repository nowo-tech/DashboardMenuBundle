<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface;

/**
 * DDD adapter of {@see HostMenuPolicy::menuCode()}.
 */
final class ContextHostCodeResolver implements MenuCodeContextResolverInterface
{
    public function resolveMenuCodeFor(MenuRequestContext $request, string $hint): string
    {
        return HostMenuPolicy::menuCode($hint, $request->route);
    }
}
