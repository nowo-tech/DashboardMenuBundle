<?php

declare(strict_types=1);

namespace App\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Demo permission checker: uses logged-in user, current path and item permission key.
 *
 * The bundle passes the current Request as $context when rendering from Twig
 * (dashboard_menu_tree). In CLI or API without a request, $context may be null.
 *
 * Permission key semantics (for demo):
 * - empty: allow all
 * - "authenticated": allow only if user is logged in
 * - "admin": allow only if user has ROLE_ADMIN
 * - "path:/foo": allow only if current path starts with "/foo"
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DemoMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $request = $context instanceof Request ? $context : null;
        $path    = $request?->getPathInfo() ?? '';
        $user    = $this->security->getUser();
        $key     = $item->getPermissionKey();

        if ($key === null || $key === '') {
            return true;
        }

        if ($key === 'authenticated') {
            return $user !== null;
        }

        if ($key === 'admin') {
            return $user !== null && $this->security->isGranted('ROLE_ADMIN');
        }

        if (str_starts_with($key, 'path:')) {
            $prefix = trim(substr($key, 5));
            if ($prefix === '') {
                return false;
            }
            if ($prefix === '/') {
                return $path === '/' || $path === '';
            }

            return str_starts_with($path, $prefix);
        }

        return true;
    }
}
