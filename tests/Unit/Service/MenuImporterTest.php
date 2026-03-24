<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class MenuImporterTest extends TestCase
{
    public function testStringOrNullCastsNonEmptyScalarToString(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);

        $ref = new ReflectionClass($importer);
        $m   = $ref->getMethod('stringOrNull');

        self::assertSame('42', $m->invoke($importer, 42));
        self::assertNull($m->invoke($importer, ''));
        self::assertNull($m->invoke($importer, null));
    }

    public function testImportInvalidFormatAddsError(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
        $result   = $importer->import([
            'menu'  => ['code' => 'sidebar', 'name' => 'Sidebar'],
            'items' => [['label' => 'Home', 'position' => 0]],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(0, $result['updated']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([], $result['errors']);
    }

    public function testImportMenusArraySkipsDuplicateBlocksWithSameCodeAndContext(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);

        $menuPersistCount = 0;
        $em               = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$menuPersistCount): void {
            if ($entity instanceof Menu) {
                ++$menuPersistCount;
            }
        });
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
        $result   = $importer->import([
            'menus' => [
                ['menu' => ['code' => 'once'], 'items' => [['label' => 'First']]],
                ['menu' => ['code' => 'once'], 'items' => [['label' => 'Second']]],
            ],
        ]);

        self::assertSame(1, $result['created']);
        self::assertSame(1, $menuPersistCount);
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
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
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->expects(self::atLeastOnce())
            ->method('findAllForMenuOrderedByTreeForExport')
            ->willReturn([$old]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('remove')->with(self::isInstanceOf(MenuItem::class));
        $em->expects(self::atLeastOnce())->method('persist')->with(self::isInstanceOf(MenuItem::class));
        $em->expects(self::atLeastOnce())->method('flush');

        $importer = new MenuImporter($itemRepo, $menuRepo, $em);
        $result   = $importer->import([
            'menu' => [
                'code'                      => 'nav',
                'name'                      => 'New name',
                'context'                   => null,
                'icon'                      => '',
                'classMenu'                 => 'm',
                'classSectionLabel'         => 'section-label',
                'depthLimit'                => '2',
                'collapsible'               => 1,
                'collapsibleExpanded'       => 0,
                'nestedCollapsible'         => true,
                'nestedCollapsibleSections' => false,
                'base'                      => false,
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
        $result   = $importer->import([
            'menus' => [
                ['menu' => ['code' => 'a'], 'items' => []],
                ['invalid' => true],
            ],
        ]);

        self::assertStringContainsString('Entry 1', $result['errors'][0] ?? '');
    }

    public function testImportMenusArrayEntryWithNonArrayMenuAddsError(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
        $result   = $importer->import([
            'menus' => [
                ['menu' => 'not-array', 'items' => []],
            ],
        ]);

        self::assertCount(1, $result['errors']);
        self::assertStringContainsString("'menu' must be an array", $result['errors'][0]);
    }

    public function testClearLinkDataForLinkItemsWithChildrenResetsRouteFields(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $em       = $this->createStub(EntityManagerInterface::class);

        $menu = new Menu();
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_home');
        $item->setRouteParams(['tab' => 'x']);
        $item->setExternalUrl('https://example.com');

        // Ensure children count > 0 so the branch is executed.
        $child = new MenuItem();
        $item->getChildren()->add($child);

        $menu->addItem($item);

        $idRef = new ReflectionProperty(MenuItem::class, 'id');
        $idRef->setValue($item, 100);
        $idRef->setValue($child, 101);
        $child->setParent($item);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([$item, $child]);
        $importer = new MenuImporter($itemRepo, $menuRepo, $em);

        $ref = new ReflectionClass($importer);
        $m   = $ref->getMethod('clearLinkDataForLinkItemsWithChildren');
        $m->invoke($importer, $menu);

        self::assertNull($item->getLinkType());
        self::assertNull($item->getRouteName());
        self::assertNull($item->getRouteParams());
        self::assertNull($item->getExternalUrl());
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
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

        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $menuRepo, $em);
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

    public function testPermissionKeysFromRowPrefersArrayAndNormalizes(): void
    {
        $importer = new MenuImporter(
            $this->createStub(MenuItemRepository::class),
            $this->createStub(MenuRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $ref = new ReflectionClass($importer);
        $m   = $ref->getMethod('permissionKeysFromRow');

        $keys = $m->invoke($importer, [
            'permissionKeys' => [' authenticated ', '', 'admin', 'admin', 1],
            'permissionKey'  => 'fallback',
        ]);

        self::assertSame(['authenticated', 'admin'], $keys);
    }

    public function testPermissionKeysFromRowFallsBackToSinglePermissionKey(): void
    {
        $importer = new MenuImporter(
            $this->createStub(MenuItemRepository::class),
            $this->createStub(MenuRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $ref = new ReflectionClass($importer);
        $m   = $ref->getMethod('permissionKeysFromRow');

        $keys = $m->invoke($importer, ['permissionKey' => 'admin']);

        self::assertSame(['admin'], $keys);
    }

    public function testScalarHelpersHandleNullAndDefaultValues(): void
    {
        $importer = new MenuImporter(
            $this->createStub(MenuItemRepository::class),
            $this->createStub(MenuRepository::class),
            $this->createStub(EntityManagerInterface::class),
        );
        $ref               = new ReflectionClass($importer);
        $stringOrDefault   = $ref->getMethod('stringOrDefault');
        $intOrNull         = $ref->getMethod('intOrNull');
        $boolOrNull        = $ref->getMethod('boolOrNull');
        $boolOrDefault     = $ref->getMethod('boolOrDefault');

        self::assertSame('fallback', $stringOrDefault->invoke($importer, null, 'fallback'));
        self::assertSame('value', $stringOrDefault->invoke($importer, 'value', 'fallback'));

        self::assertNull($intOrNull->invoke($importer, ''));
        self::assertSame(12, $intOrNull->invoke($importer, '12'));

        self::assertNull($boolOrNull->invoke($importer, null));
        self::assertFalse($boolOrNull->invoke($importer, 0));
        self::assertTrue($boolOrNull->invoke($importer, 1));

        self::assertTrue($boolOrDefault->invoke($importer, null, true));
        self::assertFalse($boolOrDefault->invoke($importer, 0, true));
    }

    public function testImportReindexesDuplicateSiblingPositionsAndFlushes(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);

        $importedA = new MenuItem();
        $importedA->setPosition(0);
        $importedB = new MenuItem();
        $importedB->setPosition(0);

        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTreeForExport')->willReturn([$importedA, $importedB]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        // create menu + after persist tree + after clear links + after reindex duplicates
        $em->expects(self::exactly(4))->method('flush');

        $importer = new MenuImporter($itemRepo, $menuRepo, $em);
        $result   = $importer->import([
            'menu'  => ['code' => 'dup-pos'],
            'items' => [
                ['label' => 'A', 'position' => 0],
                ['label' => 'B', 'position' => 0],
            ],
        ], MenuImporter::STRATEGY_REPLACE);

        self::assertSame([], $result['errors']);
        self::assertSame(0, $importedA->getPosition());
        self::assertSame(1, $importedB->getPosition());
    }
}
