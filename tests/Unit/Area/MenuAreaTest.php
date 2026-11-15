<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Area;

use Nowo\DashboardMenuBundle\Model\MenuArea;
use Nowo\DashboardMenuBundle\Model\MenuHubBlock;
use Nowo\DashboardMenuBundle\Model\MenuHubCard;
use Nowo\DashboardMenuBundle\Model\MenuHubCardExtras;
use Nowo\DashboardMenuBundle\Model\MenuHubSection;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use PHPUnit\Framework\TestCase;

final class MenuAreaTest extends TestCase
{
    public function testNormalisesListsPathsAndRules(): void
    {
        $area = new MenuArea(
            name: 'development',
            menu: 'administration',
            label: 'nav.admin',
            icon: 'tabler:shield-cog',
            homeRoute: 'admin_hub',
            roles: ['ROLE_DEVELOPER', '', 3],
            pathPrefixes: ['developer/', '/tools', ''],
            routePrefixes: ['admin_', null],
            excludeRoutePrefixes: ['admin_project_roles', ''],
            currentRoutes: ['admin_storage' => ['admin_storage', ''], '' => ['x'], 3 => ['y']],
        );

        self::assertSame('development', $area->name);
        self::assertSame('administration', $area->menu);
        self::assertSame('nav.admin', $area->label);
        self::assertSame('tabler:shield-cog', $area->icon);
        self::assertSame('admin_hub', $area->homeRoute);
        self::assertSame(['ROLE_DEVELOPER'], $area->roles);
        self::assertSame(['/developer', '/tools'], $area->pathPrefixes);
        self::assertSame(['admin_'], $area->routePrefixes);
        self::assertSame(['admin_project_roles'], $area->excludeRoutePrefixes);
        self::assertSame(['admin_storage' => ['admin_storage']], $area->currentRoutes);
    }

    public function testDefaults(): void
    {
        $area = new MenuArea('dashboard', 'dashboard');

        self::assertNull($area->label);
        self::assertNull($area->icon);
        self::assertNull($area->homeRoute);
        self::assertSame([], $area->roles);
        self::assertSame([], $area->pathPrefixes);
        self::assertSame([], $area->routePrefixes);
        self::assertSame([], $area->excludeRoutePrefixes);
        self::assertSame([], $area->currentRoutes);
    }

    public function testHubValueObjects(): void
    {
        $view  = new MenuItemView(1, 2, 'administration', null, 'link', 'Users', [], 0, 'route', 'admin_users', null, null, null, null, [], false, null, false);
        $card  = new MenuHubCard($view, '/developer/users', 'admin-users', 'hub.users', 'tour-users', 'support', true);
        $block = new MenuHubBlock('People', [$card]);
        $hub   = new MenuHubSection('Organization', [$block]);

        self::assertSame('Users', $card->label());
        self::assertSame('admin_users', $card->route());
        self::assertSame('/developer/users', $card->href);
        self::assertSame('admin-users', $card->testid);
        self::assertSame('hub.users', $card->intro);
        self::assertSame('tour-users', $card->tour);
        self::assertSame('support', $card->badge);
        self::assertTrue($card->current);
        self::assertSame('People', $block->group);
        self::assertSame([$card], $block->cards);
        self::assertSame('Organization', $hub->label);
        self::assertSame([$block], $hub->blocks);

        $extras = new MenuHubCardExtras('intro', 'testid', 'tour', 'badge');
        self::assertSame(['intro', 'testid', 'tour', 'badge'], [$extras->intro, $extras->testid, $extras->tour, $extras->badge]);
        $empty = new MenuHubCardExtras();
        self::assertSame([null, null, null, null], [$empty->intro, $empty->testid, $empty->tour, $empty->badge]);
    }
}
