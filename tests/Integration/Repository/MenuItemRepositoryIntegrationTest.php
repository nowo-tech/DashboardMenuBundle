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
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->createSchema();
        $this->repository = $this->entityManager->getRepository(MenuItem::class);
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

        $qb     = $this->repository->getPossibleParentsQueryBuilder($menu, [$item->getId()]);
        $result = $qb->getQuery()->getResult();
        self::assertCount(0, $result);
    }
}
