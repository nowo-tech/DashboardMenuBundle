<?php

declare(strict_types=1);

namespace App\Service;

use Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
 * - OR: "keyA|keyB" (at least one token must pass)
 * - AND: "keyA&keyB" (all tokens must pass)
 * - Grouping: "(keyA|keyB)&keyC" (parentheses supported)
 *
 * Examples:
 * - "authenticated|admin"
 * - "path:/admin&(authenticated|admin)"
 * - "(path:/operator|path:/admin)&authenticated"
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
#[PermissionCheckerLabel('By user/role and path (operator)')]
final class DemoMenuPermissionChecker implements MenuPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'Demo (user, path, permission key)';

    public function __construct(
        #[Autowire(service: 'security.helper')]
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

        return $this->evaluateExpression($key, $path, $user !== null);
    }

    private function evaluateExpression(string $expression, string $path, bool $isAuthenticated): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            return true;
        }

        // Parentheses: evaluate innermost groups first.
        while (preg_match('/\(([^()]+)\)/', $expression, $match) === 1) {
            $groupResult = $this->evaluateExpression($match[1], $path, $isAuthenticated);
            $expression  = preg_replace('/\(' . preg_quote($match[1], '/') . '\)/', $groupResult ? '1' : '0', $expression, 1) ?? $expression;
        }

        // AND has higher precedence than OR.
        $orParts = array_filter(array_map('trim', explode('|', $expression)), static fn (string $part): bool => $part !== '');
        if ($orParts === []) {
            return false;
        }
        foreach ($orParts as $orPart) {
            $andParts = array_filter(array_map('trim', explode('&', $orPart)), static fn (string $part): bool => $part !== '');
            if ($andParts === []) {
                continue;
            }

            $allAnd = true;
            foreach ($andParts as $token) {
                if (!$this->evaluateToken($token, $path, $isAuthenticated)) {
                    $allAnd = false;
                    break;
                }
            }
            if ($allAnd) {
                return true;
            }
        }

        return false;
    }

    private function evaluateToken(string $token, string $path, bool $isAuthenticated): bool
    {
        if ($token === '1') {
            return true;
        }
        if ($token === '0') {
            return false;
        }
        if ($token === 'authenticated') {
            return $isAuthenticated;
        }
        if ($token === 'admin') {
            return $isAuthenticated && $this->security->isGranted('ROLE_ADMIN');
        }
        if (str_starts_with($token, 'path:')) {
            $prefix = trim(substr($token, 5));
            if ($prefix === '') {
                return false;
            }
            if ($prefix === '/') {
                return $path === '/' || $path === '';
            }

            return str_starts_with($path, $prefix);
        }

        // Unknown token: deny by default in demo checker.
        return false;
    }
}
