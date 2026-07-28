<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Security;

/**
 * Permissive checker used only when security.allow_unauthenticated is true (demo/dev).
 */
final class AllowAllDashboardMenuAccessChecker implements DashboardMenuAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
