<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Database;

use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;

/** Grants the dashboard in the real-database tests. */
final class DatabaseTestAccessChecker implements DashboardMenuAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true;
    }
}
