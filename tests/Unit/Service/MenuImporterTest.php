<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use PHPUnit\Framework\TestCase;

final class MenuImporterTest extends TestCase
{
    public function testImportInvalidFormatAddsError(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([]);

        self::assertSame(0, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('Invalid format', $result['errors'][0]);
    }

    public function testImportOneMenuMissingCodeAddsError(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import(['menu' => ['name' => 'Foo'], 'items' => []]);

        self::assertSame(0, $result['created']);
        self::assertCount(1, $result['errors']);
        self::assertStringContainsString('code', $result['errors'][0]);
    }

    public function testImportOneMenuCreatesWhenNotExisting(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->with(self::logicalOr(
            self::isInstanceOf(Menu::class),
            self::isInstanceOf(MenuItem::class),
        ));
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menu'  => ['code' => 'sidebar', 'name' => 'Sidebar'],
            'items' => [['label' => 'Home', 'position' => 0]],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);
    }

    public function testImportSkipsExistingWhenStrategySkip(): void
    {
        $existing = new Menu();
        $existing->setCode('sidebar');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn($existing);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menu'  => ['code' => 'sidebar', 'name' => 'Other'],
            'items' => [],
        ], MenuImporter::STRATEGY_SKIP_EXISTING);

        self::assertSame(0, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(1, $result['skipped']);
    }

    public function testImportReplaceRemovesExistingItemsAndReimports(): void
    {
        $existing = new Menu();
        $existing->setCode('nav');
        // Pre-populate items collection so remove() is called.
        $old = new MenuItem();
        $old->setLabel('Old');
        $old->setMenu($existing);
        $existing->addItem($old);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('remove')->with(self::isInstanceOf(MenuItem::class));
        $em->expects(self::atLeastOnce())->method('persist')->with(self::isInstanceOf(MenuItem::class));
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menu' => [
                'code'                => 'nav',
                'name'                => 'New name',
                'context'             => null,
                'icon'                => '',
                'classMenu'           => 'm',
                'depthLimit'          => '2',
                'collapsible'         => 1,
                'collapsibleExpanded' => 0,
                'nestedCollapsible'   => true,
            ],
            'items' => [[
                'label'         => 'Home',
                'translations'  => ['en' => 'Home', 'es' => 'Inicio'],
                'linkType'      => MenuItem::LINK_TYPE_ROUTE,
                'routeName'     => 'app_home',
                'routeParams'   => ['a' => 1],
                'externalUrl'   => null,
                'icon'          => 'i',
                'permissionKey' => 'p',
                'itemType'      => MenuItem::ITEM_TYPE_LINK,
                'targetBlank'   => true,
                'position'      => 0,
                'children'      => [[
                    'label'    => 'Child',
                    'position' => 0,
                ]],
            ]],
        ], MenuImporter::STRATEGY_REPLACE);

        self::assertSame(0, $result['created']);
        self::assertSame(1, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);
    }

    public function testImportMenusArrayInvalidEntryAddsError(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menus' => [
                ['menu' => ['code' => 'a'], 'items' => []],
                ['invalid' => true],
            ],
        ]);

        self::assertStringContainsString('Entry 1', $result['errors'][0] ?? '');
    }

    public function testImportHandlesNonArrayTranslationsAndRouteParams(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->with(self::logicalOr(
            self::isInstanceOf(Menu::class),
            self::isInstanceOf(MenuItem::class),
        ));
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menu'  => ['code' => 'x'],
            'items' => [[
                'label'        => 'A',
                'translations' => 'not-an-array',
                'routeParams'  => 'not-an-array',
            ]],
        ]);

        self::assertSame([], $result['errors']);
    }

    public function testImportItemWithoutRouteParamsLeavesRouteParamsNull(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menu'  => ['code' => 'no-params'],
            'items' => [['label' => 'Item without routeParams', 'position' => 0]],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame([], $result['errors']);
    }

    public function testImportMenuWithZeroDepthLimitAndFalseCollapsible(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->with(self::logicalOr(
            self::isInstanceOf(Menu::class),
            self::isInstanceOf(MenuItem::class),
        ));
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($menuRepo, $em);
        $result   = $importer->import([
            'menu' => [
                'code'                => 'flags',
                'name'                => 'Flags',
                'depthLimit'          => 0,
                'collapsible'         => false,
                'collapsibleExpanded' => false,
                'nestedCollapsible'   => false,
            ],
            'items' => [],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame([], $result['errors']);
    }
}
