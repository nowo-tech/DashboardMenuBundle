<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Permission checker that uses the item's permission_key.
 *
 * Structure/example: items with empty permission_keys are shown; items with non-empty
 * permission_keys are hidden unless you extend this class or provide a custom checker that
 * resolves the key (e.g. against roles or feature flags). Use this as a base or copy the
 * structure to implement your own logic (e.g. inject Security and use isGranted() over $item->getPermissionKeys()).
 *
 * Usage note: if you want expression support (OR/AND/parentheses), parse strings like
 * "authenticated|admin" or "(path:/admin|path:/operator)&authenticated" in your custom checker.
 * See demo checkers in demo/symfony7 and demo/symfony8 for a working example.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PermissionKeyAwareMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'form.menu_type.permission_checker.permission_key_aware';

    /**
     * Items without permission keys are visible; items with keys are hidden by default.
     * Override or replace with a custom checker that resolves the key (e.g. via Security).
     */
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $keys = $item->getPermissionKeys() ?? [];

        return $keys === []

        // Structure: resolve key against $context (e.g. current user/request) in your app.
        ;
    }
}
