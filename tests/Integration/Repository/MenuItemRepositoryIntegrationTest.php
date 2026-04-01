<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Tests\Kernel\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MenuItemRepositoryIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MenuItemRepository $repository;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        $this->createSchema();
        $repository = $this->entityManager->getRepository(MenuItem::class);
        self::assertInstanceOf(MenuItemRepository::class, $repository);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->createSchema($metadata);
    }

    public function testFindAllForMenuOrderedByTreeReturnsOrderedItemsWithLabelResolved(): void
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $root = new MenuItem();
        $root->setMenu($menu);
        $root->setLabel('Root');
        $root->setPosition(1);
        $child = new MenuItem();
        $child->setMenu($menu);
        $child->setParent($root);
        $child->setLabel('Child');
        $child->setPosition(0);
        $this->entityManager->persist($root);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $items = $this->repository->findAllForMenuOrderedByTree($menu, 'en');
        self::assertCount(2, $items);
        self::assertSame('Root', $items[0]->getLabel());
        self::assertSame('Child', $items[1]->getLabel());
    }

    public function testFindAllForMenuOrderedByTreeResolvesLocaleFromTranslations(): void
    {
        $menu = new Menu();
        $menu->setCode('i18n');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('Fallback');
        $item->setTranslations(['en' => 'Home', 'es' => 'Inicio']);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $itemsEn = $this->repository->findAllForMenuOrderedByTree($menu, 'en');
        self::assertSame('Home', $itemsEn[0]->getLabel());
        $itemsEs = $this->repository->findAllForMenuOrderedByTree($menu, 'es');
        self::assertSame('Inicio', $itemsEs[0]->getLabel());
    }

    public function testFindSiblingsByPositionWithNullParent(): void
    {
        $menu = new Menu();
        $menu->setCode('sib');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $a = new MenuItem();
        $a->setMenu($menu);
        $a->setLabel('A');
        $a->setPosition(0);
        $b = new MenuItem();
        $b->setMenu($menu);
        $b->setLabel('B');
        $b->setPosition(1);
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $siblings = $this->repository->findSiblingsByPosition($a);
        self::assertCount(2, $siblings);
        self::assertSame('A', $siblings[0]->getLabel());
        self::assertSame('B', $siblings[1]->getLabel());
    }

    public function testFindSiblingsByPositionWithParent(): void
    {
        $menu = new Menu();
        $menu->setCode('sib2');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $parent = new MenuItem();
        $parent->setMenu($menu);
        $parent->setLabel('Parent');
        $child1 = new MenuItem();
        $child1->setMenu($menu);
        $child1->setParent($parent);
        $child1->setLabel('C1');
        $child1->setPosition(0);
        $child2 = new MenuItem();
        $child2->setMenu($menu);
        $child2->setParent($parent);
        $child2->setLabel('C2');
        $child2->setPosition(1);
        $this->entityManager->persist($parent);
        $this->entityManager->persist($child1);
        $this->entityManager->persist($child2);
        $this->entityManager->flush();

        $siblings = $this->repository->findSiblingsByPosition($child1);
        self::assertCount(2, $siblings);
        self::assertSame('C1', $siblings[0]->getLabel());
        self::assertSame('C2', $siblings[1]->getLabel());
    }

    public function testFindAllForMenuOrderedByTreeForExportDoesNotResolveLabel(): void
    {
        $menu = new Menu();
        $menu->setCode('export');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('Fallback');
        $item->setTranslations(['en' => 'Home', 'es' => 'Inicio']);
        $item->setPosition(0);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $items = $this->repository->findAllForMenuOrderedByTreeForExport($menu);
        self::assertCount(1, $items);
        // Export method should not mutate label based on locale.
        self::assertSame('Fallback', $items[0]->getLabel());
        self::assertSame(['en' => 'Home', 'es' => 'Inicio'], $items[0]->getTranslations());
    }

    public function testGetPossibleParentsQueryBuilderWithoutExclude(): void
    {
        $menu = new Menu();
        $menu->setCode('parents');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $qb = $this->repository->getPossibleParentsQueryBuilder($menu, []);
        self::assertNotNull($qb->getDQLPart('where'));
        $result = $qb->getQuery()->getResult();
        self::assertIsArray($result);
    }

    public function testGetPossibleParentsQueryBuilderWithExcludeIds(): void
    {
        $menu = new Menu();
        $menu->setCode('excl');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('One');
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        $itemId = $item->getId();
        self::assertNotNull($itemId);
        $qb     = $this->repository->getPossibleParentsQueryBuilder($menu, [$itemId]);
        $result = $qb->getQuery()->getResult();
        self::assertCount(0, $result);
    }

    public function testCountForMenusReturnsCountsByMenuId(): void
    {
        $menuA = new Menu();
        $menuA->setCode('count_a');
        $this->entityManager->persist($menuA);

        $menuB = new Menu();
        $menuB->setCode('count_b');
        $this->entityManager->persist($menuB);
        $this->entityManager->flush();

        $a1 = new MenuItem();
        $a1->setMenu($menuA);
        $this->entityManager->persist($a1);

        $a2 = new MenuItem();
        $a2->setMenu($menuA);
        $this->entityManager->persist($a2);

        $b1 = new MenuItem();
        $b1->setMenu($menuB);
        $this->entityManager->persist($b1);

        $this->entityManager->flush();

        $menuAId = $menuA->getId();
        $menuBId = $menuB->getId();
        self::assertNotNull($menuAId);
        self::assertNotNull($menuBId);
        $result = $this->repository->countForMenus([$menuAId, $menuBId]);

        self::assertSame(2, $result[$menuAId] ?? null);
        self::assertSame(1, $result[$menuBId] ?? null);
    }

    public function testCountForMenusWithEmptyInputReturnsEmptyArray(): void
    {
        self::assertSame([], $this->repository->countForMenus([]));
    }

    public function testFindAllForMenusOrderedByTreeForExportGroupsByMenuId(): void
    {
        $menuA = new Menu();
        $menuA->setCode('group_a');
        $this->entityManager->persist($menuA);

        $menuB = new Menu();
        $menuB->setCode('group_b');
        $this->entityManager->persist($menuB);
        $this->entityManager->flush();

        $a1 = new MenuItem();
        $a1->setMenu($menuA);
        $a1->setLabel('A1');
        $a1->setPosition(0);
        $this->entityManager->persist($a1);

        $a2 = new MenuItem();
        $a2->setMenu($menuA);
        $a2->setLabel('A2');
        $a2->setPosition(1);
        $this->entityManager->persist($a2);

        $b1 = new MenuItem();
        $b1->setMenu($menuB);
        $b1->setLabel('B1');
        $b1->setPosition(0);
        $this->entityManager->persist($b1);
        $this->entityManager->flush();

        $grouped = $this->repository->findAllForMenusOrderedByTreeForExport([$menuA, $menuB]);

        self::assertCount(2, $grouped);
        self::assertCount(2, $grouped[$menuA->getId()] ?? []);
        self::assertCount(1, $grouped[$menuB->getId()] ?? []);
    }

    public function testFindMaxPositionForParentReturnsMinusOneWhenNoSiblings(): void
    {
        $menu = new Menu();
        $menu->setCode('max_empty');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        self::assertSame(-1, $this->repository->findMaxPositionForParent($menu, null));
    }

    public function testFindMaxPositionForParentReturnsMaxForParentAndRoot(): void
    {
        $menu = new Menu();
        $menu->setCode('max_values');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $rootA = new MenuItem();
        $rootA->setMenu($menu);
        $rootA->setLabel('rootA');
        $rootA->setPosition(2);
        $this->entityManager->persist($rootA);

        $rootB = new MenuItem();
        $rootB->setMenu($menu);
        $rootB->setLabel('rootB');
        $rootB->setPosition(4);
        $this->entityManager->persist($rootB);
        $this->entityManager->flush();

        $child1 = new MenuItem();
        $child1->setMenu($menu);
        $child1->setParent($rootA);
        $child1->setLabel('child1');
        $child1->setPosition(0);
        $this->entityManager->persist($child1);

        $child2 = new MenuItem();
        $child2->setMenu($menu);
        $child2->setParent($rootA);
        $child2->setLabel('child2');
        $child2->setPosition(7);
        $this->entityManager->persist($child2);
        $this->entityManager->flush();

        self::assertSame(4, $this->repository->findMaxPositionForParent($menu, null));
        self::assertSame(7, $this->repository->findMaxPositionForParent($menu, $rootA));
    }

    public function testFindAllForMenusOrderedByTreeForExportWithEmptyMenusReturnsEmptyArray(): void
    {
        self::assertSame([], $this->repository->findAllForMenusOrderedByTreeForExport([]));
    }

    public function testReindexPositionsWithStepRewritesPositionsPerSiblingGroup(): void
    {
        $menu = new Menu();
        $menu->setCode('reindex');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $rootLate = new MenuItem();
        $rootLate->setMenu($menu);
        $rootLate->setLabel('second_root');
        $rootLate->setPosition(50);
        $this->entityManager->persist($rootLate);

        $rootEarly = new MenuItem();
        $rootEarly->setMenu($menu);
        $rootEarly->setLabel('first_root');
        $rootEarly->setPosition(10);
        $this->entityManager->persist($rootEarly);
        $this->entityManager->flush();

        $childB = new MenuItem();
        $childB->setMenu($menu);
        $childB->setParent($rootEarly);
        $childB->setLabel('child_b');
        $childB->setPosition(20);
        $this->entityManager->persist($childB);

        $childA = new MenuItem();
        $childA->setMenu($menu);
        $childA->setParent($rootEarly);
        $childA->setLabel('child_a');
        $childA->setPosition(5);
        $this->entityManager->persist($childA);
        $this->entityManager->flush();

        $changed = $this->repository->reindexPositionsWithStep($menu, 100);
        self::assertGreaterThan(0, $changed);
        $this->entityManager->flush();
        $this->entityManager->refresh($rootEarly);
        $this->entityManager->refresh($rootLate);
        $this->entityManager->refresh($childA);
        $this->entityManager->refresh($childB);

        self::assertSame(100, $rootEarly->getPosition());
        self::assertSame(200, $rootLate->getPosition());
        self::assertSame(100, $childA->getPosition());
        self::assertSame(200, $childB->getPosition());
    }

    public function testFindIdsOfItemAndDescendantsReturnsSelfAndAllDescendants(): void
    {
        $menu = new Menu();
        $menu->setCode('subtree');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $g = new MenuItem();
        $g->setMenu($menu);
        $g->setLabel('G');
        $g->setPosition(0);
        $this->entityManager->persist($g);

        $p = new MenuItem();
        $p->setMenu($menu);
        $p->setParent($g);
        $p->setLabel('P');
        $p->setPosition(0);
        $this->entityManager->persist($p);

        $c = new MenuItem();
        $c->setMenu($menu);
        $c->setParent($p);
        $c->setLabel('C');
        $c->setPosition(0);
        $this->entityManager->persist($c);
        $this->entityManager->flush();

        $gId = $g->getId();
        $pId = $p->getId();
        $cId = $c->getId();
        self::assertNotNull($gId);
        self::assertNotNull($pId);
        self::assertNotNull($cId);

        $fromG = $this->repository->findIdsOfItemAndDescendants($g);
        self::assertEqualsCanonicalizing([$gId, $pId, $cId], $fromG);

        $fromP = $this->repository->findIdsOfItemAndDescendants($p);
        self::assertEqualsCanonicalizing([$pId, $cId], $fromP);

        $fromC = $this->repository->findIdsOfItemAndDescendants($c);
        self::assertSame([$cId], $fromC);

        self::assertEqualsCanonicalizing($fromG, $this->repository->findIdsInSubtreeStartingAt($menu, (int) $gId));
        self::assertEqualsCanonicalizing($fromP, $this->repository->findIdsInSubtreeStartingAt($menu, (int) $pId));
        self::assertEqualsCanonicalizing($fromC, $this->repository->findIdsInSubtreeStartingAt($menu, (int) $cId));
    }

    public function testReindexPositionsWithStepReturnsZeroWhenAlreadySpaced(): void
    {
        $menu = new Menu();
        $menu->setCode('reindex_ok');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $a = new MenuItem();
        $a->setMenu($menu);
        $a->setLabel('a');
        $a->setPosition(100);
        $this->entityManager->persist($a);

        $b = new MenuItem();
        $b->setMenu($menu);
        $b->setLabel('b');
        $b->setPosition(200);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        self::assertSame(0, $this->repository->reindexPositionsWithStep($menu, 100));
    }

    public function testApplyTreeLayoutReparentsAndSetsPositions(): void
    {
        $menu = new Menu();
        $menu->setCode('tree_layout');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $a = new MenuItem();
        $a->setMenu($menu);
        $a->setLabel('a');
        $a->setPosition(100);
        $b = new MenuItem();
        $b->setMenu($menu);
        $b->setLabel('b');
        $b->setPosition(200);
        $this->entityManager->persist($a);
        $this->entityManager->persist($b);
        $this->entityManager->flush();

        $aId = $a->getId();
        $bId = $b->getId();
        self::assertNotNull($aId);
        self::assertNotNull($bId);

        $nodes = [
            ['id' => $bId, 'parent_id' => null, 'position' => 0],
            ['id' => $aId, 'parent_id' => $bId, 'position' => 0],
        ];
        $this->repository->applyTreeLayout($menu, $nodes, 100);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $items = $this->repository->findAllForMenuOrderedByTreeForExport($menu);
        self::assertCount(2, $items);
        /** @var array<int, MenuItem> $byId */
        $byId = [];
        foreach ($items as $it) {
            $byId[(int) $it->getId()] = $it;
        }
        self::assertNull($byId[(int) $bId]->getParent());
        self::assertSame((int) $bId, $byId[(int) $aId]->getParent()?->getId());
        self::assertSame(100, $byId[(int) $bId]->getPosition());
        self::assertSame(100, $byId[(int) $aId]->getPosition());
    }

    public function testApplyTreeLayoutRejectsSectionNotAtRoot(): void
    {
        $menu = new Menu();
        $menu->setCode('tree_section_root');
        $this->entityManager->persist($menu);
        $this->entityManager->flush();

        $link = new MenuItem();
        $link->setMenu($menu);
        $link->setLabel('link');
        $link->setItemType(MenuItem::ITEM_TYPE_LINK);
        $link->setPosition(100);

        $section = new MenuItem();
        $section->setMenu($menu);
        $section->setLabel('section');
        $section->setItemType(MenuItem::ITEM_TYPE_SECTION);
        $section->setPosition(200);

        $this->entityManager->persist($link);
        $this->entityManager->persist($section);
        $this->entityManager->flush();

        $linkId    = $link->getId();
        $sectionId = $section->getId();
        self::assertNotNull($linkId);
        self::assertNotNull($sectionId);

        $nodes = [
            ['id' => $linkId, 'parent_id' => null, 'position' => 0],
            ['id' => $sectionId, 'parent_id' => $linkId, 'position' => 0],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(MenuItemRepository::TREE_LAYOUT_SECTION_MUST_BE_ROOT);
        $this->repository->applyTreeLayout($menu, $nodes, 100);
    }
}
