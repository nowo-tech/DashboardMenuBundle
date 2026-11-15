<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Area;

use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\ConfiguredRouteMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Service\MenuAreaResolver;
use PHPUnit\Framework\TestCase;

final class ConfiguredRouteMenuCurrentMatcherTest extends TestCase
{
    private ConfiguredRouteMenuCurrentMatcher $matcher;

    protected function setUp(): void
    {
        $areas = new MenuAreaResolver([
            'development'  => ['menu' => 'administration', 'current_routes' => ['admin_users' => ['admin_users_dev_'], 'admin_storage' => ['admin_storage']]],
            'organization' => ['current_routes' => ['admin_users' => ['admin_users_', 'admin_invitations']]],
        ]);
        $this->matcher = new ConfiguredRouteMenuCurrentMatcher($areas, ['nowo_manual_index' => ['nowo_manual_', '']]);
    }

    public function testAreaRulesApplyToTheirMenuOnly(): void
    {
        self::assertTrue($this->current('organization', 'admin_users', 'admin_invitations'));
        self::assertTrue($this->current('organization', 'admin_users', 'admin_users_show'));
        self::assertFalse($this->current('administration', 'admin_users', 'admin_invitations'));
        self::assertTrue($this->current('administration', 'admin_users', 'admin_users_dev_x'));
        self::assertTrue($this->current('administration', 'admin_storage', 'admin_storage_test'));
        self::assertFalse($this->current('organization', 'admin_storage', 'admin_storage_test'));
        self::assertFalse($this->current(null, 'admin_storage', 'admin_storage_test'));
    }

    public function testGlobalRulesApplyToEveryMenu(): void
    {
        self::assertTrue($this->current('dashboard', 'nowo_manual_index', 'nowo_manual_page'));
        self::assertTrue($this->current(null, 'nowo_manual_index', 'nowo_manual_page'));
        // An empty prefix never matches everything.
        self::assertFalse($this->current('dashboard', 'nowo_manual_index', 'other'));
    }

    public function testNoRouteNeverMatches(): void
    {
        self::assertFalse($this->current('organization', null, 'admin_users_show'));
        self::assertFalse($this->current('organization', '', 'admin_users_show'));
        self::assertFalse($this->current('organization', 'admin_users', null));
        self::assertFalse($this->current('organization', 'admin_users', ''));
        self::assertFalse($this->current('organization', 'unknown', 'admin_users_show'));
    }

    private function current(?string $menuCode, ?string $itemRoute, ?string $requestRoute): bool
    {
        $item = new MenuItemView(1, 1, $menuCode, null, 'link', 'L', [], 0, 'route', $itemRoute, null, null, null, null, [], false, null, false);

        return $this->matcher->isCurrentItemFor($item, new MenuRequestContext($requestRoute, [], '/x', 'en'), '/x');
    }
}
