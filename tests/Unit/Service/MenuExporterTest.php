<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuExporter;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MenuExporterTest extends TestCase
{
    public function testExportMenuReturnsMenuConfigAndEmptyItemsWhenNoItems(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setName('Main sidebar');
        $menu->setClassSectionLabel('section-label');
        $menu->setNestedCollapsibleSections(false);

        $menuRepo = $this->createStub(MenuRepository::class);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([]);

        $exporter = new MenuExporter($menuRepo, $itemRepo);
        $data     = $exporter->exportMenu($menu);

        self::assertArrayHasKey('menu', $data);
        self::assertArrayHasKey('items', $data);
        self::assertSame('sidebar', $data['menu']['code']);
        self::assertSame('Main sidebar', $data['menu']['name']);
        self::assertSame('section-label', $data['menu']['classSectionLabel']);
        self::assertFalse($data['menu']['nestedCollapsibleSections']);
        self::assertFalse($data['menu']['base']);
        self::assertSame([], $data['items']);
    }

    public function testExportMenuIncludesBaseWhenTrue(): void
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $menu->setBase(true);

        $menuRepo = $this->createStub(MenuRepository::class);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([]);

        $exporter = new MenuExporter($menuRepo, $itemRepo);
        $data     = $exporter->exportMenu($menu);

        self::assertTrue($data['menu']['base']);
    }

    public function testExportMenuIncludesItemTree(): void
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $menu->setPermissionChecker('app.permission.checker');
        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('Home');
        $item->setRouteName('app_home');
        $item->setPermissionKeys(['app.home.view', 'authenticated']);
        $item->setIsUnanimous(false);
        $item->setPosition(0);

        $menuRepo = $this->createStub(MenuRepository::class);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([$item]);

        $exporter = new MenuExporter($menuRepo, $itemRepo);
        $data     = $exporter->exportMenu($menu);

        self::assertCount(1, $data['items']);
        self::assertArrayHasKey('permissionChecker', $data['menu']);
        self::assertSame('app.permission.checker', $data['menu']['permissionChecker']);
        self::assertSame('Home', $data['items'][0]['label']);
        self::assertSame('app_home', $data['items'][0]['routeName']);
        self::assertArrayHasKey('permissionKey', $data['items'][0]);
        self::assertArrayHasKey('permissionKeys', $data['items'][0]);
        self::assertArrayHasKey('isUnanimous', $data['items'][0]);
        self::assertSame('app.home.view', $data['items'][0]['permissionKey']);
        self::assertSame(['app.home.view', 'authenticated'], $data['items'][0]['permissionKeys']);
        self::assertFalse($data['items'][0]['isUnanimous']);
        self::assertSame(0, $data['items'][0]['position']);
    }

    public function testExportMenuKeepsPermissionKeysEvenWhenNull(): void
    {
        $menu = new Menu();
        $menu->setCode('permissions-null');

        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('No permission key');
        $item->setPosition(0);

        $menuRepo = $this->createStub(MenuRepository::class);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([$item]);

        $exporter = new MenuExporter($menuRepo, $itemRepo);
        $data     = $exporter->exportMenu($menu);

        self::assertArrayHasKey('permissionChecker', $data['menu']);
        self::assertNull($data['menu']['permissionChecker']);
        self::assertArrayHasKey('permissionKey', $data['items'][0]);
        self::assertArrayHasKey('permissionKeys', $data['items'][0]);
        self::assertArrayHasKey('isUnanimous', $data['items'][0]);
        self::assertNull($data['items'][0]['permissionKey']);
        self::assertNull($data['items'][0]['permissionKeys']);
        self::assertTrue($data['items'][0]['isUnanimous']);
    }

    public function testExportMenuIncludesChildrenKeyWhenChildrenNotEmpty(): void
    {
        $menu = new Menu();
        $menu->setCode('tree');

        $root = new MenuItem();
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(0);

        $child = new MenuItem();
        $child->setMenu($menu);
        $child->setParent($root);
        $child->setLabel('Child');
        $child->setPosition(0);

        // Ensure parent has an id so exporter can link children by parent id.
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($root, 10);
        $ref->setValue($child, 11);

        $menuRepo = $this->createStub(MenuRepository::class);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([$root, $child]);

        $exporter = new MenuExporter($menuRepo, $itemRepo);
        $data     = $exporter->exportMenu($menu);

        self::assertCount(1, $data['items']);
        self::assertArrayHasKey('children', $data['items'][0]);
        self::assertCount(1, $data['items'][0]['children']);
        self::assertSame('Child', $data['items'][0]['children'][0]['label']);
    }

    public function testExportAllReturnsMenusKeyWithArray(): void
    {
        $menu = new Menu();
        $menu->setCode('foo');

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findAll')->willReturn([$menu]);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenusOrderedByTreeForExport')->willReturn([]);

        $exporter = new MenuExporter($menuRepo, $itemRepo);
        $data     = $exporter->exportAll();

        self::assertArrayHasKey('menus', $data);
        self::assertIsArray($data['menus']);
        self::assertCount(1, $data['menus']);
        self::assertSame('foo', $data['menus'][0]['menu']['code']);
    }
}
