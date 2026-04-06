<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;

/**
 * Base/example permission checker that uses the item's permission_key.
 *
 * IMPORTANT — This is an intentionally incomplete base implementation:
 * - Items with NO permission keys are always visible.
 * - Items WITH permission keys are always hidden.
 *
 * This behavior is by design: the checker signals that keys are present but does not resolve them.
 * Selecting it in the dashboard without extending it will hide all keyed items in production.
 *
 * To implement real access control, either:
 *  a) Create a custom class implementing {@see MenuPermissionCheckerInterface} and inject Security.
 *  b) Copy this class and fill in the "resolve key against $context" section.
 *
 * See demo/symfony7 and demo/symfony8 for working examples with Symfony Security.
 *
 * Usage note: if you want expression support (OR/AND/parentheses), parse strings like
 * "authenticated|admin" or "(path:/admin|path:/operator)&authenticated" in your custom checker.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class PermissionKeyAwareMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    /** Label shown in the dashboard dropdown — clarifies this is a base/example class. */
    public const DASHBOARD_LABEL = 'form.menu_type.permission_checker.permission_key_aware';

    /**
     * Items without permission keys are visible; items with keys are hidden.
     *
     * Override or replace with a custom checker that resolves the key against $context
     * (e.g. current user roles via Symfony Security's isGranted()).
     */
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $keys = $item->getPermissionKeys() ?? [];

        // Base behaviour: see class docblock — replace with a real checker or subclass to evaluate $keys vs $context.
        return $keys === [];
    }
}
