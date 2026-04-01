<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Util;

use function array_key_exists;
use function array_slice;
use function in_array;

/**
 * Detects cycles in a parent map (item id → parent id or null for root).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class ParentRelationCycleDetector
{
    /**
     * @param array<int, int|null> $parentOf Item id => parent id, or null if root
     *
     * @return list<int>|null Ordered ids along one cycle, or null if the graph is acyclic
     */
    public static function findFirstCycle(array $parentOf): ?array
    {
        if ($parentOf === []) {
            return null;
        }

        foreach (array_keys($parentOf) as $startId) {
            /** @var list<int> $path */
            $path    = [];
            $current = $startId;
            while (true) {
                if (in_array($current, $path, true)) {
                    $idx = array_search($current, $path, true);
                    if ($idx === false) {
                        break;
                    }

                    return array_values(array_slice($path, (int) $idx));
                }
                if (!array_key_exists($current, $parentOf)) {
                    break;
                }
                $path[]  = $current;
                $current = $parentOf[$current];
            }
        }

        return null;
    }
}
