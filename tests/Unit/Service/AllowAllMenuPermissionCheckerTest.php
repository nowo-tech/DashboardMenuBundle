<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use PHPUnit\Framework\TestCase;

final class AllowAllMenuPermissionCheckerTest extends TestCase
{
    public function testCanViewAlwaysReturnsTrueRegardlessOfContext(): void
    {
        $checker = new AllowAllMenuPermissionChecker();
        $item    = new MenuItem();

        self::assertTrue($checker->canView($item));
        self::assertTrue($checker->canView($item, 'any-context'));
        self::assertTrue($checker->canView($item, ['role' => 'admin']));
    }
}
