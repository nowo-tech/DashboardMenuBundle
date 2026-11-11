<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default resolver: returns the hint as the menu code (no criteria-based resolution).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DefaultMenuCodeResolver implements MenuCodeContextResolverInterface, MenuCodeResolverInterface
{
    public function resolveMenuCodeFor(MenuRequestContext $request, string $hint): string
    {
        return $hint;
    }

    /**
     * @deprecated since 2.19.0, use {@see resolveMenuCodeFor()}; removed in 3.0.0
     */
    public function resolveMenuCode(Request $request, string $hint): string
    {
        return $hint;
    }
}
