<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the effective menu code from the request and a hint (e.g. menu name).
 *
 * Implement this to choose the menu by customizable criteria (e.g. operatorId, partnerId, menu name).
 * Return the first matching menu code: e.g. try (operatorId + partnerId + name), then (partnerId + name), then (name).
 * The bundle will only render links that pass the permission checker; parents with no visible children are pruned.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
interface MenuCodeResolverInterface
{
    /**
     * Resolve the menu code to use for loading the tree.
     *
     * @param string $hint The requested menu name/code (e.g. "sidebar") from the template or API
     */
    public function resolveMenuCode(Request $request, string $hint): string;
}
