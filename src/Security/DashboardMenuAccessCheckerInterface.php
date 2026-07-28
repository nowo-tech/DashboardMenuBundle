<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Security;

/**
 * Decides whether the current request may access dashboard admin CRUD (REQ-UI-002).
 */
interface DashboardMenuAccessCheckerInterface
{
    public function canAccess(): bool;
}
