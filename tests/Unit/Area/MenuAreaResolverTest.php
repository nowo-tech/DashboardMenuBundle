<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Area;

use Nowo\DashboardMenuBundle\Model\MenuArea;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuAreaAccessCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuAreaResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_map;
use function in_array;

/**
 * The Beacon split (Development / Organization / Preferences / Dashboard) as configuration.
 */
final class MenuAreaResolverTest extends TestCase
{
    /** @return array<string, array<string, mixed>> */
    public static function beaconAreas(): array
    {
        return [
            'dashboard'   => ['menu' => 'dashboard', 'label' => 'nav.dashboard', 'home_route' => 'dashboard_home'],
            'preferences' => [
                'label'          => 'nav.preferences',
                'home_route'     => 'account_profile',
                'path_prefixes'  => ['/account', '/organizations'],
                'route_prefixes' => ['account_'],
            ],
            'organization' => [
                'roles'                  => ['ROLE_MANAGER'],
                'home_route'             => 'organization_hub',
                'path_prefixes'          => ['/organization'],
                'route_prefixes'         => ['organization_', 'admin_users', 'admin_project_'],
                'exclude_route_prefixes' => ['admin_project_roles'],
            ],
            'development' => [
                'menu'           => 'administration',
                'roles'          => ['ROLE_DEVELOPER'],
                'home_route'     => 'admin_hub',
                'path_prefixes'  => ['/developer'],
                'route_prefixes' => ['admin_', 'nowo_dashboard_menu_'],
                'icon'           => 7,
                'current_routes' => ['admin_storage' => ['admin_storage'], 'bad' => 'x', 4 => ['y']],
            ],
        ];
    }

    /** @return iterable<string, array{string|null, string, string|null}> */
    public static function requests(): iterable
    {
        yield 'no route' => [null, '/developer', 'dashboard'];
        yield 'empty route' => ['', '/developer', 'dashboard'];
        yield 'developer path' => ['admin_project_config', '/developer/project-config', 'development'];
        yield 'developer root' => ['admin_hub', '/developer', 'development'];
        yield 'locale segment' => ['organization_hub', '/es/organization', 'organization'];
        yield 'locale with region' => ['organization_hub', '/pt_BR/organization/', 'organization'];
        yield 'organizations is preferences' => ['organizations_members', '/organizations/abc/members', 'preferences'];
        yield 'encoded first letter' => ['x_page', '/%64eveloper/projects/1/config', 'development'];
        yield 'encoded slash does not hide the locale' => ['x_page', '/es/%64eveloper', 'development'];
        yield 'segment boundary' => ['x_page', '/developerish', 'dashboard'];
        yield 'route prefix, area order' => ['account_security', '/somewhere', 'preferences'];
        yield 'route prefix organization' => ['admin_users_show', '/kit/users/1', 'organization'];
        yield 'excluded falls through' => ['admin_project_roles_edit', '/kit/roles/1', 'development'];
        yield 'kit route' => ['nowo_dashboard_menu_dashboard_index', '/admin/menus', 'development'];
        yield 'nothing matches' => ['project_show', '/projects/1', 'dashboard'];
    }

    #[DataProvider('requests')]
    public function testResolvesTheArea(?string $route, string $path, ?string $expected): void
    {
        $resolver = new MenuAreaResolver(self::beaconAreas(), 'dashboard', '/^[a-z]{2}(?:[_-][A-Za-z]{2})?$/D');

        self::assertSame($expected, $resolver->resolveFor(new MenuRequestContext($route, [], $path, 'en'))?->name);
    }

    public function testLongestPathPrefixWinsAndRootPrefix(): void
    {
        $resolver = new MenuAreaResolver([
            'site'  => ['path_prefixes' => ['/']],
            'admin' => ['path_prefixes' => ['/admin']],
            'deep'  => ['path_prefixes' => ['/admin/deep']],
        ]);

        self::assertSame('deep', $resolver->resolveFor(new MenuRequestContext('r', [], '/admin/deep/x', 'en'))?->name);
        self::assertSame('admin', $resolver->resolveFor(new MenuRequestContext('r', [], '/admin', 'en'))?->name);
        self::assertSame('site', $resolver->resolveFor(new MenuRequestContext('r', [], '/', 'en'))?->name);
    }

    public function testWithoutLocalePatternOrDefault(): void
    {
        $resolver = new MenuAreaResolver(self::beaconAreas());

        self::assertSame('organization', $resolver->resolveFor(new MenuRequestContext('organization_x', [], '/es/organization', 'en'))?->name);
        self::assertNull($resolver->resolveFor(new MenuRequestContext('project_show', [], '/es/organization', 'en')));
        self::assertNull($resolver->resolveFor(new MenuRequestContext(null, [], '/', 'en')));
        self::assertNull($resolver->defaultArea());
        self::assertNull((new MenuAreaResolver([], 'missing'))->defaultArea());
    }

    public function testAreasFromConfiguration(): void
    {
        $resolver = new MenuAreaResolver(self::beaconAreas(), 'dashboard');

        self::assertSame(['dashboard', 'preferences', 'organization', 'development'], array_map(static fn (MenuArea $a): string => $a->name, $resolver->all()));
        $development = $resolver->get('development');
        self::assertInstanceOf(MenuArea::class, $development);
        self::assertSame('administration', $development->menu);
        self::assertNull($development->icon);
        self::assertSame(['admin_storage' => ['admin_storage']], $development->currentRoutes);
        self::assertSame('preferences', $resolver->get('preferences')?->menu);
        self::assertNull($resolver->get('nope'));
        self::assertSame('dashboard', $resolver->defaultArea()?->name);
    }

    public function testGrantedAreasGoThroughTheAccessChecker(): void
    {
        $checker = new class implements MenuAreaAccessCheckerInterface {
            public function canAccessArea(MenuArea $area): bool
            {
                return !in_array('ROLE_DEVELOPER', $area->roles, true);
            }
        };
        $resolver = new MenuAreaResolver(self::beaconAreas(), 'dashboard', null, $checker);

        self::assertSame(['dashboard', 'preferences', 'organization'], array_map(static fn (MenuArea $a): string => $a->name, $resolver->granted()));
        self::assertTrue($resolver->isGranted('organization'));
        self::assertFalse($resolver->isGranted('development'));
        self::assertFalse($resolver->isGranted('missing'));
        $dashboard = $resolver->get('dashboard');
        self::assertInstanceOf(MenuArea::class, $dashboard);
        self::assertTrue($resolver->isGranted($dashboard));
    }

    public function testDefaultCheckerWithoutSecurityOnlyGrantsOpenAreas(): void
    {
        $resolver = new MenuAreaResolver(self::beaconAreas());

        self::assertSame(['dashboard', 'preferences'], array_map(static fn (MenuArea $a): string => $a->name, $resolver->granted()));
    }

    public function testFallbackWhenDeniedIsOffByDefault(): void
    {
        $resolver = new MenuAreaResolver(self::beaconAreas(), 'dashboard', null, $this->denyDevelopers());

        self::assertSame('development', $resolver->resolveFor(new MenuRequestContext('admin_hub', [], '/developer', 'en'))?->name);
    }

    public function testFallbackWhenDeniedUsesTheDefaultAreaThenTheFirstGrantedOne(): void
    {
        $request = new MenuRequestContext('admin_hub', [], '/developer', 'en');

        $withDefault = new MenuAreaResolver(self::beaconAreas(), 'dashboard', null, $this->denyDevelopers(), true);
        self::assertSame('dashboard', $withDefault->resolveFor($request)?->name);
        // A granted area is returned as it is.
        self::assertSame('organization', $withDefault->resolveFor(new MenuRequestContext('x', [], '/organization', 'en'))?->name);

        // The default area is denied too: the first granted one.
        $denyDashboard = new class implements MenuAreaAccessCheckerInterface {
            public function canAccessArea(MenuArea $area): bool
            {
                return !in_array($area->name, ['development', 'dashboard'], true);
            }
        };
        self::assertSame('preferences', (new MenuAreaResolver(self::beaconAreas(), 'dashboard', null, $denyDashboard, true))->resolveFor($request)?->name);
        self::assertSame('preferences', (new MenuAreaResolver(self::beaconAreas(), null, null, $denyDashboard, true))->resolveFor($request)?->name);

        // Nothing is granted: null.
        $denyAll = new class implements MenuAreaAccessCheckerInterface {
            public function canAccessArea(MenuArea $area): bool
            {
                return false;
            }
        };
        self::assertNull((new MenuAreaResolver(self::beaconAreas(), 'dashboard', null, $denyAll, true))->resolveFor($request));
        // No area at all matches and there is no default: still null.
        self::assertNull((new MenuAreaResolver([], null, null, $denyAll, true))->resolveFor($request));
    }

    private function denyDevelopers(): MenuAreaAccessCheckerInterface
    {
        return new class implements MenuAreaAccessCheckerInterface {
            public function canAccessArea(MenuArea $area): bool
            {
                return !in_array('ROLE_DEVELOPER', $area->roles, true);
            }
        };
    }
}
