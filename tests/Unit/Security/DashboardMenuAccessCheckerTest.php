<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Security;

use Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\ConfigurableDashboardMenuAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class DashboardMenuAccessCheckerTest extends TestCase
{
    public function testAllowAllAlwaysGrants(): void
    {
        self::assertTrue((new AllowAllDashboardMenuAccessChecker())->canAccess());
    }

    public function testConfigurableEmptyRolesAlwaysGrants(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects(self::never())->method('isGranted');

        self::assertTrue((new ConfigurableDashboardMenuAccessChecker($auth, []))->canAccess());
    }

    public function testConfigurableGrantsWhenAnyRoleMatches(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturnCallback(
            static fn (mixed $attribute): bool => $attribute === 'ROLE_ADMIN',
        );

        self::assertTrue((new ConfigurableDashboardMenuAccessChecker($auth, ['ROLE_USER', 'ROLE_ADMIN']))->canAccess());
    }

    public function testConfigurableDeniesWhenNoRoleMatches(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        self::assertFalse((new ConfigurableDashboardMenuAccessChecker($auth, ['ROLE_ADMIN']))->canAccess());
    }
}
