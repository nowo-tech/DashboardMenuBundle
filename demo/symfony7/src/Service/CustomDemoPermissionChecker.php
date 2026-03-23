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
 * Custom checker for demos:
 * - authenticated
 * - admin
 * - path:/prefix
 * - never (always deny)
 * - supports OR/AND and parentheses.
 */
#[PermissionCheckerLabel('Custom demo checker (includes DENY cases)')]
final class CustomDemoPermissionChecker implements MenuPermissionCheckerInterface
{
    public function __construct(
        #[Autowire(service: 'security.helper')]
        private readonly Security $security,
    ) {
    }

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        $request = $context instanceof Request ? $context : null;
        $path    = $request?->getPathInfo() ?? '';
        $key     = (string) ($item->getPermissionKey() ?? '');

        if ($key === '') {
            return true;
        }

        return $this->evaluateExpression($key, $path, $this->security->getUser() !== null);
    }

    private function evaluateExpression(string $expression, string $path, bool $authenticated): bool
    {
        $expression = trim($expression);
        if ($expression === '') {
            return true;
        }

        while (preg_match('/\(([^()]+)\)/', $expression, $match) === 1) {
            $groupResult = $this->evaluateExpression($match[1], $path, $authenticated);
            $expression  = preg_replace('/\(' . preg_quote($match[1], '/') . '\)/', $groupResult ? '1' : '0', $expression, 1) ?? $expression;
        }

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
                if (!$this->evaluateToken($token, $path, $authenticated)) {
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

    private function evaluateToken(string $token, string $path, bool $authenticated): bool
    {
        return match (true) {
            $token === '1' => true,
            $token === '0' => false,
            $token === 'never' => false,
            $token === 'authenticated' => $authenticated,
            $token === 'admin' => $authenticated && $this->security->isGranted('ROLE_ADMIN'),
            str_starts_with($token, 'path:') => $this->matchesPath($path, trim(substr($token, 5))),
            default => false,
        };
    }

    private function matchesPath(string $path, string $prefix): bool
    {
        if ($prefix === '') {
            return false;
        }
        if ($prefix === '/') {
            return $path === '/' || $path === '';
        }

        return str_starts_with($path, $prefix);
    }
}
