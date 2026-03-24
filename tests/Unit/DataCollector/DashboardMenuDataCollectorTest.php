<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

final class DashboardMenuDataCollectorTest extends TestCase
{
    public function testGetName(): void
    {
        $collector = new DashboardMenuDataCollector();
        self::assertSame('nowo_dashboard_menu', $collector->getName());
    }

    public function testAddMenuLoadAndGetMenus(): void
    {
        $collector = new DashboardMenuDataCollector();
        $item      = new MenuItem();
        $item->setLabel('Home');
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $tree = [['item' => $item, 'children' => []]];

        $collector->addMenuLoad('sidebar', [null, []], $tree);

        $collector->collect(new Request(), new Response());

        $menus = $collector->getMenus();
        self::assertCount(1, $menus);
        self::assertSame('sidebar', $menus[0]['code']);
        self::assertSame([null, []], $menus[0]['context_sets']);
        self::assertNull($menus[0]['resolved_context']);
        self::assertSame(1, $menus[0]['root_count']);
        self::assertSame('Home', $menus[0]['items_summary'][0]['label']);
        self::assertSame('link', $menus[0]['items_summary'][0]['type']);
        self::assertSame(0, $menus[0]['items_summary'][0]['children_count']);
    }

    public function testAddMenuLoadSummarizesNestedChildren(): void
    {
        $collector = new DashboardMenuDataCollector();
        $root      = new MenuItem();
        $root->setLabel('Root');
        $child = new MenuItem();
        $child->setLabel('Child');
        $tree = [
            ['item' => $root, 'children' => [
                ['item' => $child, 'children' => []],
            ]],
        ];

        $collector->addMenuLoad('nav', null, $tree);
        $collector->collect(new Request(), new Response());

        $menus = $collector->getMenus();
        self::assertSame(1, $menus[0]['root_count']);
        self::assertSame(1, $menus[0]['items_summary'][0]['children_count']);
        self::assertSame('Child', $menus[0]['items_summary'][0]['children'][0]['label']);
    }

    public function testCollectStoresMenuLoadsAndQueryCount(): void
    {
        $collector = new DashboardMenuDataCollector();
        $collector->addMenuLoad('sidebar', [], []);
        $collector->collect(new Request(), new Response());

        self::assertCount(1, $collector->getMenus());
        self::assertNull($collector->getMenuQueryCount());
    }

    public function testResetClearsData(): void
    {
        $collector = new DashboardMenuDataCollector();
        $collector->addMenuLoad('sidebar', [], []);
        $collector->collect(new Request(), new Response());
        $collector->reset();

        self::assertSame([], $collector->getMenus());
        self::assertNull($collector->getMenuQueryCount());
    }

    public function testLateCollectWithNullProfilerDoesNothing(): void
    {
        $collector = new DashboardMenuDataCollector();
        $collector->addMenuLoad('sidebar', [], []);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertNull($collector->getMenuQueryCount());
    }

    public function testLateCollectWithProfilerThrowingResetsNothing(): void
    {
        $profiler = $this->createMock(\Symfony\Component\HttpKernel\Profiler\Profiler::class);
        $profiler->method('get')->with('db')->willThrowException(new RuntimeException('No db'));
        $collector = new DashboardMenuDataCollector($profiler);
        $collector->addMenuLoad('sidebar', [], []);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertNull($collector->getMenuQueryCount());
    }

    public function testLateCollectCountsQueriesContainingMenuTablePrefix(): void
    {
        $dbCollector = new class implements DataCollectorInterface, ResetInterface {
            public function collect(Request $request, Response $response, ?Throwable $exception = null): void
            {
            }

            public function getName(): string
            {
                return 'db';
            }

            public function reset(): void
            {
            }

            /** @return array<string, mixed> */
            public function getData(): array
            {
                return [
                    'queries' => [
                        'default' => [
                            ['sql' => 'SELECT * FROM dashboard_menu WHERE id = 1'],
                            ['sql' => 'SELECT * FROM users'],
                            ['sql' => 'SELECT * FROM dashboard_menu_item'],
                        ],
                    ],
                ];
            }
        };
        $profiler = $this->createMock(\Symfony\Component\HttpKernel\Profiler\Profiler::class);
        $profiler->method('get')->with('db')->willReturn($dbCollector);
        $collector = new DashboardMenuDataCollector($profiler);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame(2, $collector->getMenuQueryCount());
    }

    public function testLateCollectCountsQueryWhenStoredAsString(): void
    {
        $dbCollector = new class implements DataCollectorInterface, ResetInterface {
            public function collect(Request $request, Response $response, ?Throwable $exception = null): void
            {
            }

            public function getName(): string
            {
                return 'db';
            }

            public function reset(): void
            {
            }

            /** @return array<string, mixed> */
            public function getData(): array
            {
                return [
                    'queries' => [
                        'default' => [
                            'SELECT * FROM dashboard_menu',
                        ],
                    ],
                ];
            }
        };
        $profiler = $this->createMock(\Symfony\Component\HttpKernel\Profiler\Profiler::class);
        $profiler->method('get')->with('db')->willReturn($dbCollector);
        $collector = new DashboardMenuDataCollector($profiler);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame(1, $collector->getMenuQueryCount());
    }

    public function testLateCollectSkipsNonArrayConnectionQueries(): void
    {
        $dbCollector = new class implements DataCollectorInterface, ResetInterface {
            public function collect(Request $request, Response $response, ?Throwable $exception = null): void
            {
            }

            public function getName(): string
            {
                return 'db';
            }

            public function reset(): void
            {
            }

            /** @return array<string, mixed> */
            public function getData(): array
            {
                return [
                    'queries' => [
                        'default' => [['sql' => 'SELECT * FROM dashboard_menu']],
                        'other'   => 'invalid',
                    ],
                ];
            }
        };
        $profiler = $this->createMock(\Symfony\Component\HttpKernel\Profiler\Profiler::class);
        $profiler->method('get')->with('db')->willReturn($dbCollector);
        $collector = new DashboardMenuDataCollector($profiler);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame(1, $collector->getMenuQueryCount());
    }

    public function testLateCollectWhenDbCollectorHasNoGetDataReturnsEarly(): void
    {
        $dbCollector = new class implements DataCollectorInterface, ResetInterface {
            public function collect(Request $request, Response $response, ?Throwable $exception = null): void
            {
            }

            public function getName(): string
            {
                return 'db';
            }

            public function reset(): void
            {
            }
        };
        $profiler = $this->createMock(\Symfony\Component\HttpKernel\Profiler\Profiler::class);
        $profiler->method('get')->with('db')->willReturn($dbCollector);
        $collector = new DashboardMenuDataCollector($profiler);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertNull($collector->getMenuQueryCount());
    }

    public function testLateCollectWithGroupedQueries(): void
    {
        $dbCollector = new class implements DataCollectorInterface, ResetInterface {
            public function collect(Request $request, Response $response, ?Throwable $exception = null): void
            {
            }

            public function getName(): string
            {
                return 'db';
            }

            public function reset(): void
            {
            }

            /** @return array<string, mixed> */
            public function getData(): array
            {
                return [
                    'grouped_queries' => [
                        'default' => [
                            ['sql' => 'SELECT * FROM dashboard_menu'],
                        ],
                    ],
                ];
            }
        };
        $profiler = $this->createMock(\Symfony\Component\HttpKernel\Profiler\Profiler::class);
        $profiler->method('get')->with('db')->willReturn($dbCollector);
        $collector = new DashboardMenuDataCollector($profiler);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertSame(1, $collector->getMenuQueryCount());
    }

    public function testGettersReturnConfigWhenSetInConstructor(): void
    {
        $collector = new DashboardMenuDataCollector(
            null,
            null,
            ['project'   => 'test'],
            ['allow_all' => 'Allow all'],
            'default',
            'app_',
            120,
            'cache.app',
            ['en', 'es'],
            'en',
            ['bootstrap-icons' => 'bi'],
        );
        $collector->collect(new Request(), new Response());

        self::assertSame(['project' => 'test'], $collector->getBundleConfig());
        self::assertSame(['allow_all' => 'Allow all'], $collector->getPermissionCheckerChoices());
        self::assertSame('default', $collector->getConnectionName());
        self::assertSame('app_', $collector->getTablePrefix());
        self::assertSame(['ttl' => 120, 'pool' => 'cache.app'], $collector->getCacheConfig());
        self::assertSame(['en', 'es'], $collector->getLocales());
        self::assertSame('en', $collector->getDefaultLocale());
        self::assertSame(['bootstrap-icons' => 'bi'], $collector->getIconLibraryPrefixMap());
    }

    public function testSummarizeTreeResolvesIconWhenMenuIconNameResolverSet(): void
    {
        $iconResolver = new \Nowo\DashboardMenuBundle\Service\MenuIconNameResolver(['bootstrap-icons' => 'bi']);
        $collector    = new DashboardMenuDataCollector(null, $iconResolver);
        $item         = new MenuItem();
        $item->setLabel('Home');
        $item->setIcon('bootstrap-icons:house');
        $tree = [['item' => $item, 'children' => []]];

        $collector->addMenuLoad('sidebar', [], $tree);
        $collector->collect(new Request(), new Response());

        $menus = $collector->getMenus();
        self::assertSame('bi:house', $menus[0]['items_summary'][0]['icon']);
    }

    public function testAddPermissionCheckIsExposedAfterCollectAndResetClearsIt(): void
    {
        $collector = new DashboardMenuDataCollector();
        $item      = new MenuItem();
        $item->setLabel('Settings');
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setPermissionKey('menu.settings');
        $item->setRouteName('app_settings');

        $collector->addPermissionCheck(
            'sidebar',
            'custom_checker',
            'App\\Security\\CustomChecker',
            'custom_checker',
            false,
            $item,
            true,
        );
        $collector->collect(new Request(), new Response());

        $checks = $collector->getPermissionChecks();
        self::assertCount(1, $checks);
        self::assertSame('sidebar', $checks[0]['menu_code']);
        self::assertSame(['menu.settings'], $checks[0]['permission_keys']);
        self::assertTrue($checks[0]['is_unanimous']);
        self::assertSame('custom_checker', $checks[0]['checker_selected']);
        self::assertTrue($checks[0]['result']);

        $collector->reset();
        self::assertSame([], $collector->getPermissionChecks());
    }
}
