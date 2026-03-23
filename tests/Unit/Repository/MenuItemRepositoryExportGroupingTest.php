<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MenuItemRepositoryExportGroupingTest extends TestCase
{
    public function testFindAllForMenusOrderedByTreeForExportSkipsItemsWithoutMenuId(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $repo = $this->getMockBuilder(MenuItemRepository::class)
            ->setConstructorArgs([$registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $menu      = new Menu();
        $menuIdRef = new ReflectionProperty(Menu::class, 'id');
        $menuIdRef->setValue($menu, 77);

        $withMenu = new MenuItem();
        $withMenu->setMenu($menu);
        $withMenu->setLabel('with-menu');

        $withoutMenu = new MenuItem();
        $withoutMenu->setMenu(null);
        $withoutMenu->setLabel('without-menu');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$withMenu, $withoutMenu]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);
        $qb->method('orderBy')->willReturn($qb);
        $qb->method('addOrderBy')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $repo->method('createQueryBuilder')->willReturn($qb);

        $grouped = $repo->findAllForMenusOrderedByTreeForExport([$menu]);

        self::assertCount(1, $grouped);
        self::assertCount(1, $grouped[77] ?? []);
        self::assertSame('with-menu', $grouped[77][0]->getLabel());
    }
}
