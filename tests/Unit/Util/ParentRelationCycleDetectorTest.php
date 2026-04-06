<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Util;

use Nowo\DashboardMenuBundle\Util\ParentRelationCycleDetector;
use PHPUnit\Framework\TestCase;

final class ParentRelationCycleDetectorTest extends TestCase
{
    public function testEmptyMapHasNoCycle(): void
    {
        self::assertNull(ParentRelationCycleDetector::findFirstCycle([]));
    }

    public function testLinearChainHasNoCycle(): void
    {
        self::assertNull(ParentRelationCycleDetector::findFirstCycle([
            1 => null,
            2 => 1,
            3 => 2,
        ]));
    }

    public function testDetectsTriangleCycle(): void
    {
        $cycle = ParentRelationCycleDetector::findFirstCycle([
            1 => 2,
            2 => 3,
            3 => 1,
        ]);
        self::assertSame([1, 2, 3], $cycle);
    }

    public function testDetectsSelfLoop(): void
    {
        self::assertSame([7], ParentRelationCycleDetector::findFirstCycle([7 => 7]));
    }

    public function testBrokenParentReferenceDoesNotReportFalseCycle(): void
    {
        self::assertNull(ParentRelationCycleDetector::findFirstCycle([
            1 => null,
            2 => 99,
        ]));
    }

    public function testCycleBuriedInLargerDisconnectedGraph(): void
    {
        // Nodes 10 and 11 are an acyclic subtree; nodes 20 → 21 → 20 form a cycle.
        $cycle = ParentRelationCycleDetector::findFirstCycle([
            10 => null,
            11 => 10,
            20 => 21,
            21 => 20,
        ]);

        self::assertIsArray($cycle);
        self::assertContains(20, $cycle);
        self::assertContains(21, $cycle);
    }

    public function testLongerCycleIsDetected(): void
    {
        $cycle = ParentRelationCycleDetector::findFirstCycle([
            1 => 2,
            2 => 3,
            3 => 4,
            4 => 5,
            5 => 1,
        ]);

        self::assertIsArray($cycle);
        self::assertCount(5, $cycle);
    }

    public function testSingleNodeWithNullParentHasNoCycle(): void
    {
        self::assertNull(ParentRelationCycleDetector::findFirstCycle([42 => null]));
    }
}
