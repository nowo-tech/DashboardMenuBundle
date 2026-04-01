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
}
