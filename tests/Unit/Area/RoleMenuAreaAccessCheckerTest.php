<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Area;

use Nowo\DashboardMenuBundle\Model\MenuArea;
use Nowo\DashboardMenuBundle\Service\RoleMenuAreaAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class RoleMenuAreaAccessCheckerTest extends TestCase
{
    public function testAreaWithoutRolesIsOpen(): void
    {
        self::assertTrue((new RoleMenuAreaAccessChecker())->canAccessArea(new MenuArea('dashboard', 'dashboard')));
    }

    public function testWithoutSecurityAnAreaWithRolesIsClosed(): void
    {
        self::assertFalse((new RoleMenuAreaAccessChecker())->canAccessArea(new MenuArea('dev', 'dev', roles: ['ROLE_DEVELOPER'])));
    }

    public function testAnyRoleGrantsWithTheAreaAsSubject(): void
    {
        $area    = new MenuArea('org', 'organization', roles: ['ROLE_MANAGER', 'AREA_ORGANIZATION']);
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::exactly(2))->method('isGranted')
            ->willReturnCallback(static fn (mixed $attribute, mixed $subject): bool => $subject === $area && $attribute === 'AREA_ORGANIZATION');

        self::assertTrue((new RoleMenuAreaAccessChecker($checker))->canAccessArea($area));
    }

    public function testNoRoleGranted(): void
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        self::assertFalse((new RoleMenuAreaAccessChecker($checker))->canAccessArea(new MenuArea('dev', 'dev', roles: ['ROLE_DEVELOPER'])));
    }
}
