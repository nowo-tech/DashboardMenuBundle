<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\PermissionKeyAwareMenuPermissionChecker;
use PHPUnit\Framework\TestCase;

final class PermissionKeyAwareMenuPermissionCheckerTest extends TestCase
{
    public function testCanViewReturnsTrueWhenPermissionKeyIsNull(): void
    {
        $checker = new PermissionKeyAwareMenuPermissionChecker();
        $item    = new MenuItem();
        $item->setPermissionKey(null);

        self::assertTrue($checker->canView($item));
    }

    public function testCanViewReturnsTrueWhenPermissionKeyIsEmpty(): void
    {
        $checker = new PermissionKeyAwareMenuPermissionChecker();
        $item    = new MenuItem();
        $item->setPermissionKey('');

        self::assertTrue($checker->canView($item));
    }

    public function testCanViewReturnsFalseWhenPermissionKeyIsSet(): void
    {
        $checker = new PermissionKeyAwareMenuPermissionChecker();
        $item    = new MenuItem();
        $item->setPermissionKey('ROLE_ADMIN');

        self::assertFalse($checker->canView($item));
    }
}
