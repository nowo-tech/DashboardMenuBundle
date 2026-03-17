<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Permission checker that uses the item's permission_key.
 *
 * Structure/example: items with an empty permission_key are shown; items with a non-empty
 * permission_key are hidden unless you extend this class or provide a custom checker that
 * resolves the key (e.g. against roles or feature flags). Use this as a base or copy the
 * structure to implement your own logic (e.g. inject Security and use isGranted($item->getPermissionKey())).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PermissionKeyAwareMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    /**
     * Items without a permission_key are visible; items with a key are hidden by default.
     * Override or replace with a custom checker that resolves the key (e.g. via Security).
     */
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $key = $item->getPermissionKey();

        return $key === null || $key === ''

        // Structure: resolve key against $context (e.g. current user/request) in your app.
        ;
    }
}
