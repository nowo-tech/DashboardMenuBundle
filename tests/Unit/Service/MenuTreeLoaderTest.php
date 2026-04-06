<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;

class MenuTreeLoaderTest extends TestCase
{
    public function testLoadTreeRecordsPermissionChecksAndFallbackWhenCheckerServiceMissing(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker('missing_checker');

        $root = new MenuItem();
        $this->setMenuItemId($root, 1);
        $root->setMenu($menu);
        $root->setItemType(MenuItem::ITEM_TYPE_LINK);
        $root->setLabel('Root');
        $root->setPermissionKey('menu.root');
        $root->setRouteName('app_root');
        $root->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->with($menu, 'en')->willReturn([$root]);

        $resolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('missing_checker')->willReturn(false);
        $container->expects(self::never())->method('get');

        $collector    = new DashboardMenuDataCollector();
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
            $collector,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);

        $collector->collect(new \Symfony\Component\HttpFoundation\Request(), new \Symfony\Component\HttpFoundation\Response());
        $checks = $collector->getPermissionChecks();
        self::assertCount(1, $checks);
        self::assertSame('main', $checks[0]['menu_code']);
        self::assertSame('missing_checker', $checks[0]['checker_selected']);
        self::assertNull($checks[0]['checker_service_id']);
        self::assertTrue($checks[0]['checker_fallback']);
        self::assertSame(AllowAllMenuPermissionChecker::class, $checks[0]['checker_resolved']);
        self::assertSame(['menu.root'], $checks[0]['permission_keys']);
        self::assertTrue($checks[0]['is_unanimous']);
    }

    public function testLoadTreeReturnsEmptyArrayWhenMenuNotFound(): void
    {
        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('unknown', [null, []])->willReturn(null);
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $config   = [
            'project'  => null,
            'defaults' => [
                'connection'         => 'default',
                'table_prefix'       => '',
                'permission_checker' => null,
                'cache_pool'         => null,
                'cache_ttl'          => 300,
            ],
            'menus' => [],
        ];
        $resolver     = new MenuConfigResolver($config, $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);

        $loader = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );
        $tree = $loader->loadTree('unknown', 'en');

        self::assertSame([], $tree);
    }

    public function testLoadTreeBuildsNestedTreeAndAppliesPermissionCheckerFromContainer(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker('test_permission_checker');

        $root = new MenuItem();
        $this->setMenuItemId($root, 1);
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(0);

        $child1 = new MenuItem();
        $this->setMenuItemId($child1, 2);
        $child1->setMenu($menu);
        $child1->setLabel('Child 1');
        $child1->setParent($root);
        $child1->setPosition(0);

        $child2 = new MenuItem();
        $this->setMenuItemId($child2, 3);
        $child2->setMenu($menu);
        $child2->setLabel('Child 2');
        $child2->setParent($root);
        $child2->setPosition(1);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo
            ->method('findForCodeWithContextSets')
            ->with('main', [null, []])
            ->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo
            ->method('findAllForMenuOrderedByTree')
            ->with($menu, 'en')
            ->willReturn([$root, $child1, $child2]);

        $resolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $permissionChecker = new class implements MenuPermissionCheckerInterface {
            public function canView(MenuItem $item, mixed $context = null): bool
            {
                return !($context === 'deny-second' && $item->getLabel() === 'Child 2')

                ;
            }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('has')
            ->with('test_permission_checker')
            ->willReturn(true);
        $container
            ->method('get')
            ->with('test_permission_checker')
            ->willReturn($permissionChecker);

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $loader->loadTree('main', 'en');

        $loader->loadTree('main', 'en', 'deny-second');
    }

    public function testLoadTreeUsesDefaultPermissionCheckerWhenContainerServiceIsNotMenuPermissionCheckerInterface(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker('invalid_checker');

        $root = new MenuItem();
        $this->setMenuItemId($root, 1);
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo
            ->method('findForCodeWithContextSets')
            ->with('main', [null, []])
            ->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo
            ->method('findAllForMenuOrderedByTree')
            ->with($menu, 'en')
            ->willReturn([$root]);

        $resolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('invalid_checker')->willReturn(true);
        $container->method('get')->with('invalid_checker')->willReturn(new stdClass());

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        self::assertSame($root, $tree[0]['item']);
    }

    public function testLoadTreeUsesDefaultPermissionCheckerWhenContainerDoesNotHaveService(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker('missing_checker');

        $root = new MenuItem();
        $this->setMenuItemId($root, 1);
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->with($menu, 'en')->willReturn([$root]);

        $resolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('missing_checker')->willReturn(false);
        $container->expects(self::never())->method('get');

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        self::assertSame($root, $tree[0]['item']);
    }

    public function testHydrateItemsFromRowsSetsLinkTypeNullWhenRowHasNullLinkType(): void
    {
        $menu = new Menu();

        $menuRepo = $this->createStub(MenuRepository::class);
        $itemRepo = $this->createStub(MenuItemRepository::class);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);

        $loader = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $rows = [
            [
                'position'  => 0,
                'label'     => 'Root',
                'link_type' => null,
            ],
        ];

        $ref = new ReflectionClass(MenuTreeLoader::class);
        $m   = $ref->getMethod('hydrateItemsFromRows');

        /** @var list<MenuItem> $items */
        $items = $m->invoke($loader, $rows, $menu, 'en');

        self::assertCount(1, $items);
        self::assertNull($items[0]->getLinkType());
    }

    public function testLoadTreePromotesToRootWhenParentIsNotInMap(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker('filter_parent');

        $parent = new MenuItem();
        $this->setMenuItemId($parent, 1);
        $parent->setMenu($menu);
        $parent->setLabel('Parent');
        $parent->setPosition(0);

        $child = new MenuItem();
        $this->setMenuItemId($child, 2);
        $child->setMenu($menu);
        $child->setLabel('Child');
        $child->setParent($parent);
        $child->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo
            ->method('findForCodeWithContextSets')
            ->with('main', [null, []])
            ->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo
            ->method('findAllForMenuOrderedByTree')
            ->with($menu, 'en')
            ->willReturn([$parent, $child]);

        $resolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $checker = new class implements MenuPermissionCheckerInterface {
            public function canView(MenuItem $item, mixed $context = null): bool
            {
                return $item->getId() !== 1;
            }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('filter_parent')->willReturn(true);
        $container->method('get')->with('filter_parent')->willReturn($checker);

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        self::assertSame($child, $tree[0]['item']);
        self::assertSame([], $tree[0]['children']);
    }

    public function testBuildTreeSkipsItemWithNullIdInSecondPass(): void
    {
        $menu = new Menu();
        $menu->setCode('main');

        $root = new MenuItem();
        $this->setMenuItemId($root, 1);
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(0);

        $childWithNullId = new MenuItem();
        $this->setMenuItemId($childWithNullId, null);
        $childWithNullId->setMenu($menu);
        $childWithNullId->setLabel('Orphan');
        $childWithNullId->setParent($root);
        $childWithNullId->setPosition(0);

        $child2 = new MenuItem();
        $this->setMenuItemId($child2, 3);
        $child2->setMenu($menu);
        $child2->setLabel('Child');
        $child2->setParent($root);
        $child2->setPosition(1);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo
            ->method('findAllForMenuOrderedByTree')
            ->with($menu, 'en')
            ->willReturn([$root, $childWithNullId, $child2]);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        self::assertSame($root, $tree[0]['item']);
        self::assertCount(1, $tree[0]['children']);
        self::assertSame($child2, $tree[0]['children'][0]['item']);
    }

    public function testLoadTreeUsesFindMenuAndItemsRawWhenAvailable(): void
    {
        $raw = [
            'menu' => [
                'id'                    => 1,
                'code'                  => 'main',
                'attributes_key'        => '',
                'name'                  => 'Main menu',
                'icon'                  => null,
                'class_menu'            => null,
                'class_item'            => null,
                'class_link'            => null,
                'class_children'        => null,
                'class_current'         => null,
                'class_branch_expanded' => null,
                'class_has_children'    => null,
                'class_expanded'        => null,
                'class_collapsed'       => null,
                'permission_checker'    => null,
                'depth_limit'           => null,
                'collapsible'           => null,
                'collapsible_expanded'  => null,
                'nested_collapsible'    => null,
                'attributes'            => null,
                'base'                  => false,
            ],
            'items' => [
                [
                    'id'             => 10,
                    'menu_id'        => 1,
                    'parent_id'      => null,
                    'position'       => 0,
                    'label'          => 'Root',
                    'translations'   => null,
                    'link_type'      => 'route',
                    'route_name'     => 'app_home',
                    'route_params'   => null,
                    'external_url'   => null,
                    'permission_key' => null,
                    'icon'           => null,
                    'item_type'      => 'link',
                    'target_blank'   => false,
                ],
                [
                    'id'             => 11,
                    'menu_id'        => 1,
                    'parent_id'      => 10,
                    'position'       => 0,
                    'label'          => 'Child',
                    'translations'   => null,
                    'link_type'      => 'route',
                    'route_name'     => null,
                    'route_params'   => null,
                    'external_url'   => null,
                    'permission_key' => null,
                    'icon'           => null,
                    'item_type'      => 'link',
                    'target_blank'   => false,
                ],
            ],
        ];

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('main', [null, []])->willReturn($raw);
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->expects(self::never())->method('findAllForMenuOrderedByTree');

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        self::assertSame('Root', $tree[0]['item']->getLabel());
        self::assertCount(1, $tree[0]['children']);
        self::assertSame('Child', $tree[0]['children'][0]['item']->getLabel());
    }

    public function testLoadTreeFallsBackToLegacyWhenFindMenuAndItemsRawReturnsNull(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $item = new MenuItem();
        $this->setMenuItemId($item, 1);
        $item->setMenu($menu);
        $item->setLabel('Only');
        $item->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('main', [null, []])->willReturn(null);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->with($menu, 'en')->willReturn([$item]);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        self::assertSame('Only', $tree[0]['item']->getLabel());
    }

    public function testLoadTreeUsesCacheHitWhenAvailable(): void
    {
        $raw = [
            'menu' => [
                'id'                    => 1,
                'code'                  => 'cached',
                'attributes_key'        => '',
                'name'                  => null,
                'icon'                  => null,
                'class_menu'            => null,
                'class_item'            => null,
                'class_link'            => null,
                'class_children'        => null,
                'class_current'         => null,
                'class_branch_expanded' => null,
                'class_has_children'    => null,
                'class_expanded'        => null,
                'class_collapsed'       => null,
                'permission_checker'    => null,
                'depth_limit'           => null,
                'collapsible'           => null,
                'collapsible_expanded'  => null,
                'nested_collapsible'    => null,
                'attributes'            => null,
                'base'                  => false,
            ],
            'items' => [
                [
                    'id'             => 1, 'menu_id' => 1, 'parent_id' => null, 'position' => 0,
                    'label'          => 'Cached', 'translations' => null, 'link_type' => 'route',
                    'route_name'     => null, 'route_params' => null, 'external_url' => null,
                    'permission_key' => null, 'icon' => null, 'item_type' => 'link', 'target_blank' => false,
                ],
            ],
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(serialize($raw));

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->expects(self::never())->method('findMenuAndItemsRaw');
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->expects(self::never())->method('findAllForMenuOrderedByTree');

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            $cachePool,
            60,
        );

        $tree = $loader->loadTree('cached', 'en');
        self::assertCount(1, $tree);
        self::assertSame('Cached', $tree[0]['item']->getLabel());
    }

    public function testLoadTreeSavesToCacheWhenPoolSetAndFindMenuAndItemsRawReturnsData(): void
    {
        $raw = [
            'menu' => [
                'id'             => 1, 'code' => 'save', 'attributes_key' => '', 'name' => null, 'icon' => null,
                'class_menu'     => null, 'class_item' => null, 'class_link' => null, 'class_children' => null,
                'class_current'  => null, 'class_branch_expanded' => null, 'class_has_children' => null,
                'class_expanded' => null, 'class_collapsed' => null, 'permission_checker' => null,
                'depth_limit'    => null, 'collapsible' => null, 'collapsible_expanded' => null, 'nested_collapsible' => null,
                'attributes'     => null, 'base' => false,
            ],
            'items' => [
                [
                    'id'           => 1, 'menu_id' => 1, 'parent_id' => null, 'position' => 0,
                    'label'        => 'One', 'translations' => null, 'link_type' => 'route', 'route_name' => null,
                    'route_params' => null, 'external_url' => null, 'permission_key' => null,
                    'icon'         => null, 'item_type' => 'link', 'target_blank' => false,
                ],
            ],
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturnSelf();
        $cacheItem->method('expiresAfter')->willReturnSelf();

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);
        $cachePool->expects(self::once())->method('save')->with($cacheItem);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('save', [null, []])->willReturn($raw);
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->expects(self::never())->method('findAllForMenuOrderedByTree');

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            $cachePool,
            60,
        );

        $tree = $loader->loadTree('save', 'en');
        self::assertCount(1, $tree);
        self::assertSame('One', $tree[0]['item']->getLabel());
    }

    public function testLoadTreeNormalizesLegacyCheckerLabelToConfiguredServiceId(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        // Legacy value stored as label, not service id.
        $menu->setPermissionChecker('Legacy checker label');

        $root = new MenuItem();
        $this->setMenuItemId($root, 1);
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->with($menu, 'en')->willReturn([$root]);

        $resolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $checker = new class implements MenuPermissionCheckerInterface {
            public function canView(MenuItem $item, mixed $context = null): bool
            {
                return $context !== 'deny';
            }
        };

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.checker.legacy');
        $container->expects(self::once())->method('get')->with('app.checker.legacy')->willReturn($checker);

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
            null,
            ['app.checker.legacy' => 'Legacy checker label'],
        );

        $tree = $loader->loadTree('main', 'en', 'deny');
        self::assertSame([], $tree);
    }

    /**
     * When cache hits but stored value is invalid (not array or missing keys), loader falls back to findMenuAndItemsRaw.
     */
    public function testLoadTreeFallsBackToRepositoryWhenCacheHitContainsInvalidData(): void
    {
        $raw = [
            'menu' => [
                'id'             => 1, 'code' => 'invalid-cache', 'attributes_key' => '', 'name' => null, 'icon' => null,
                'class_menu'     => null, 'class_item' => null, 'class_link' => null, 'class_children' => null,
                'class_current'  => null, 'class_branch_expanded' => null, 'class_has_children' => null,
                'class_expanded' => null, 'class_collapsed' => null, 'permission_checker' => null,
                'depth_limit'    => null, 'collapsible' => null, 'collapsible_expanded' => null, 'nested_collapsible' => null,
                'attributes'     => null, 'base' => false,
            ],
            'items' => [
                [
                    'id'           => 1, 'menu_id' => 1, 'parent_id' => null, 'position' => 0,
                    'label'        => 'From repo', 'translations' => null, 'link_type' => 'route', 'route_name' => null,
                    'route_params' => null, 'external_url' => null, 'permission_key' => null,
                    'icon'         => null, 'item_type' => 'link', 'target_blank' => false,
                ],
            ],
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn(serialize('invalid'));

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->method('getItem')->willReturn($cacheItem);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('invalid-cache', [null, []])->willReturn($raw);
        $itemRepo = $this->createMock(MenuItemRepository::class);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            $cachePool,
            60,
        );

        $tree = $loader->loadTree('invalid-cache', 'en');
        self::assertCount(1, $tree);
        self::assertSame('From repo', $tree[0]['item']->getLabel());
    }

    /**
     * Raw menu row with class_* and attributes (JSON string) to cover hydrateMenuFromRow and setMenuString.
     */
    public function testLoadTreeHydratesMenuWithClassOverridesAndJsonAttributes(): void
    {
        $raw = [
            'menu' => [
                'id'                    => 1,
                'code'                  => 'styled',
                'attributes_key'        => '',
                'name'                  => 'Styled',
                'icon'                  => 'bi:list',
                'class_menu'            => 'nav flex-column',
                'class_item'            => 'nav-item',
                'class_link'            => 'nav-link',
                'class_children'        => 'nav flex-column ms-2',
                'class_current'         => 'active',
                'class_branch_expanded' => 'open',
                'class_has_children'    => 'has-children',
                'class_expanded'        => 'expanded',
                'class_collapsed'       => 'collapsed',
                'permission_checker'    => null,
                'depth_limit'           => 2,
                'collapsible'           => true,
                'collapsible_expanded'  => true,
                'nested_collapsible'    => true,
                'attributes'            => '{"locale":"en"}',
                'base'                  => true,
            ],
            'items' => [
                [
                    'id'           => 1, 'menu_id' => 1, 'parent_id' => null, 'position' => 0,
                    'label'        => 'Root', 'translations' => null, 'link_type' => 'route', 'route_name' => null,
                    'route_params' => null, 'external_url' => null, 'permission_key' => null,
                    'icon'         => null, 'item_type' => 'link', 'target_blank' => false,
                ],
            ],
        ];

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('styled', [null, []])->willReturn($raw);
        $itemRepo = $this->createMock(MenuItemRepository::class);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('styled', 'en');
        self::assertCount(1, $tree);
        $item = $tree[0]['item'];
        self::assertSame('Root', $item->getLabel());
        $menu = $item->getMenu();
        self::assertNotNull($menu);
        self::assertSame('nav flex-column', $menu->getClassMenu());
        self::assertSame('nav-item', $menu->getClassItem());
        self::assertSame(2, $menu->getDepthLimit());
        self::assertTrue($menu->getCollapsible());
        self::assertTrue($menu->isBase());
        self::assertSame(['locale' => 'en'], $menu->getContext());
    }

    /**
     * Raw item rows with translations and route_params as JSON strings (not arrays) to cover json_decode path in hydrateItemsFromRows.
     */
    public function testLoadTreeHydratesItemsWithJsonTranslationsAndRouteParams(): void
    {
        $raw = [
            'menu' => [
                'id'             => 1, 'code' => 'json-items', 'attributes_key' => '', 'name' => null, 'icon' => null,
                'class_menu'     => null, 'class_item' => null, 'class_link' => null, 'class_children' => null,
                'class_current'  => null, 'class_branch_expanded' => null, 'class_has_children' => null,
                'class_expanded' => null, 'class_collapsed' => null, 'permission_checker' => null,
                'depth_limit'    => null, 'collapsible' => null, 'collapsible_expanded' => null, 'nested_collapsible' => null,
                'attributes'     => null, 'base' => false,
            ],
            'items' => [
                [
                    'id'             => 10,
                    'menu_id'        => 1,
                    'parent_id'      => null,
                    'position'       => 0,
                    'label'          => 'Home',
                    'translations'   => '{"es":"Inicio","fr":"Accueil"}',
                    'link_type'      => 'route',
                    'route_name'     => 'app_home',
                    'route_params'   => '{"id":1,"slug":"foo"}',
                    'external_url'   => null,
                    'permission_key' => null,
                    'icon'           => null,
                    'item_type'      => 'link',
                    'target_blank'   => false,
                ],
            ],
        ];

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('json-items', [null, []])->willReturn($raw);
        $itemRepo = $this->createMock(MenuItemRepository::class);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('json-items', 'en');
        self::assertCount(1, $tree);
        $item = $tree[0]['item'];
        self::assertSame('Home', $item->getLabel());
        self::assertSame(['es' => 'Inicio', 'fr' => 'Accueil'], $item->getTranslations());
        self::assertSame(['id' => 1, 'slug' => 'foo'], $item->getRouteParams());
    }

    public function testNodeKeyUsesObjectIdWhenEntityIdIsNull(): void
    {
        $menuRepo     = $this->createStub(MenuRepository::class);
        $itemRepo     = $this->createStub(MenuItemRepository::class);
        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $item = new MenuItem();
        $ref  = new ReflectionClass(MenuTreeLoader::class);
        $m    = $ref->getMethod('nodeKey');
        $key  = $m->invoke($loader, $item);

        self::assertStringStartsWith('obj:', $key);
    }

    public function testLoadTreeHydratesPermissionKeysArrayAndFiltersInvalidValues(): void
    {
        $raw = [
            'menu' => [
                'id'             => 1, 'code' => 'perm-keys', 'attributes_key' => '', 'name' => null, 'icon' => null,
                'class_menu'     => null, 'class_item' => null, 'class_link' => null, 'class_children' => null,
                'class_current'  => null, 'class_branch_expanded' => null, 'class_has_children' => null,
                'class_expanded' => null, 'class_collapsed' => null, 'permission_checker' => null,
                'depth_limit'    => null, 'collapsible' => null, 'collapsible_expanded' => null, 'nested_collapsible' => null,
                'attributes'     => null, 'base' => false,
            ],
            'items' => [
                [
                    'id'              => 10,
                    'menu_id'         => 1,
                    'parent_id'       => null,
                    'position'        => 0,
                    'label'           => 'Secured',
                    'translations'    => null,
                    'link_type'       => 'route',
                    'route_name'      => 'app_home',
                    'route_params'    => null,
                    'external_url'    => null,
                    'permission_key'  => 'legacy.fallback',
                    'permission_keys' => [' authenticated ', '', 'admin', 123, 'admin'],
                    'is_unanimous'    => false,
                    'icon'            => null,
                    'item_type'       => 'link',
                    'target_blank'    => false,
                ],
            ],
        ];

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->with('perm-keys', [null, []])->willReturn($raw);
        $itemRepo = $this->createMock(MenuItemRepository::class);

        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('perm-keys', 'en');
        self::assertCount(1, $tree);
        $item = $tree[0]['item'];
        self::assertSame(['authenticated', 'admin'], $item->getPermissionKeys());
        self::assertFalse($item->isUnanimous());
    }

    public function testPruneEmptySectionsKeepsSectionWithNoChildrenInDb(): void
    {
        $menuRepo     = $this->createStub(MenuRepository::class);
        $itemRepo     = $this->createStub(MenuItemRepository::class);
        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        // A section that never had children in the DB (had_children=false) is kept:
        // it may be an intentional standalone heading.
        $section = new MenuItem();
        $section->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $section->setLabel('Standalone section');

        $nodes = [
            ['item' => $section, 'children' => [], 'had_children' => false],
        ];

        $m = new ReflectionMethod(MenuTreeLoader::class, 'pruneEmptySections');
        $m->setAccessible(true);
        $out = $m->invoke($loader, $nodes);
        self::assertCount(1, $out);
        self::assertSame($section, $out[0]['item']);
        self::assertSame([], $out[0]['children']);
    }

    public function testPruneEmptySectionsRemovesSectionWhenAllChildrenHiddenByPermissions(): void
    {
        $menuRepo     = $this->createStub(MenuRepository::class);
        $itemRepo     = $this->createStub(MenuItemRepository::class);
        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        // A section that had children in DB (had_children=true) but all were filtered
        // by the permission checker (children=[]) must be pruned.
        $section = new MenuItem();
        $section->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $section->setLabel('Admin section');

        $nodes = [
            ['item' => $section, 'children' => [], 'had_children' => true],
        ];

        $m = new ReflectionMethod(MenuTreeLoader::class, 'pruneEmptySections');
        $m->setAccessible(true);
        $out = $m->invoke($loader, $nodes);
        self::assertCount(0, $out, 'Section with all children hidden by permissions must be pruned.');
    }

    public function testPruneEmptySectionsKeepsLeafLinkWithoutChildrenInDb(): void
    {
        $menuRepo     = $this->createStub(MenuRepository::class);
        $itemRepo     = $this->createStub(MenuItemRepository::class);
        $resolver     = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $resolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $link = new MenuItem();
        $link->setItemType(MenuItem::ITEM_TYPE_LINK);
        $link->setLabel('Leaf');

        $nodes = [
            ['item' => $link, 'children' => [], 'had_children' => false],
        ];

        $m = new ReflectionMethod(MenuTreeLoader::class, 'pruneEmptySections');
        $m->setAccessible(true);
        $out = $m->invoke($loader, $nodes);
        self::assertCount(1, $out);
        self::assertSame($link, $out[0]['item']);
    }

    public function testLoadTreeMergesDynamicServiceChildrenWithPersistedByPosition(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker(null);

        $service = new MenuItem();
        $this->setMenuItemId($service, 1);
        $service->setMenu($menu);
        $service->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $service->setLabel('Svc');
        $service->setLinkResolver('test.dynamic_children');
        $service->setPosition(0);

        $dbChild = new MenuItem();
        $this->setMenuItemId($dbChild, 2);
        $dbChild->setMenu($menu);
        $dbChild->setParent($service);
        $dbChild->setItemType(MenuItem::ITEM_TYPE_LINK);
        $dbChild->setLabel('DB B');
        $dbChild->setPosition(20);
        $dbChild->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $dbChild->setExternalUrl('/b');

        $resolverImpl = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
            {
                return [
                    ['label' => 'Dyn A', 'href' => '/a', 'position' => 10],
                ];
            }
        };

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->with($menu, 'en')->willReturn([$service, $dbChild]);

        $configResolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'test.dynamic_children');
        $container->method('get')->with('test.dynamic_children')->willReturn($resolverImpl);

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $configResolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree = $loader->loadTree('main', 'en');
        self::assertCount(1, $tree);
        $children = $tree[0]['children'];
        self::assertCount(2, $children);
        self::assertSame('Dyn A', $children[0]['item']->getLabel());
        self::assertSame('/a', $children[0]['item']->getRuntimeHref());
        self::assertSame('DB B', $children[1]['item']->getLabel());
    }

    public function testLoadTreeMergedChildrenOrderUsesLowerPositionFirst(): void
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker(null);

        $service = new MenuItem();
        $this->setMenuItemId($service, 1);
        $service->setMenu($menu);
        $service->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $service->setLabel('Svc');
        $service->setLinkResolver('test.dynamic_children');
        $service->setPosition(0);

        $dbChild = new MenuItem();
        $this->setMenuItemId($dbChild, 2);
        $dbChild->setMenu($menu);
        $dbChild->setParent($service);
        $dbChild->setItemType(MenuItem::ITEM_TYPE_LINK);
        $dbChild->setLabel('DB first');
        $dbChild->setPosition(5);
        $dbChild->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $dbChild->setExternalUrl('/b');

        $resolverImpl = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
            {
                return [
                    ['label' => 'Dyn later', 'href' => '/a', 'position' => 40],
                ];
            }
        };

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->with('main', [null, []])->willReturn($menu);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->with($menu, 'en')->willReturn([$service, $dbChild]);

        $configResolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'test.dynamic_children');
        $container->method('get')->with('test.dynamic_children')->willReturn($resolverImpl);

        $iconResolver = new MenuIconNameResolver([]);
        $loader       = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $configResolver,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            $container,
            [],
            null,
            null,
            60,
        );

        $tree     = $loader->loadTree('main', 'en');
        $children = $tree[0]['children'];
        self::assertCount(2, $children);
        self::assertSame('DB first', $children[0]['item']->getLabel());
        self::assertSame('Dyn later', $children[1]['item']->getLabel());
    }

    private function setMenuItemId(MenuItem $item, ?int $id): void
    {
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($item, $id);
    }
}
