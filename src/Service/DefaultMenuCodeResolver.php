<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Default resolver: returns the hint as the menu code (no criteria-based resolution).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DefaultMenuCodeResolver implements MenuCodeResolverInterface
{
    public function resolveMenuCode(Request $request, string $hint): string
    {
        return $hint;
    }
}
