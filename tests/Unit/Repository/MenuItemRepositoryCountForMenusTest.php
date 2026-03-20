<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use PHPUnit\Framework\TestCase;

final class MenuItemRepositoryCountForMenusTest extends TestCase
{
    public function testCountForMenusSkipsRowsWithNonPositiveMenuId(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $repo = $this->getMockBuilder(MenuItemRepository::class)
            ->setConstructorArgs([$registry])
            ->onlyMethods(['createQueryBuilder'])
            ->getMock();

        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn([
            ['menu_id' => 0, 'item_count' => 99],
            ['menu_id' => -1, 'item_count' => 11],
            ['menu_id' => 3, 'item_count' => 2],
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturn($qb);
        $qb->method('where')->willReturn($qb);
        $qb->method('setParameter')->willReturn($qb);
        $qb->method('groupBy')->willReturn($qb);
        $qb->method('getQuery')->willReturn($query);

        $repo->method('createQueryBuilder')->willReturn($qb);

        $result = $repo->countForMenus([1, 2, 3]);

        self::assertSame([3 => 2], $result);
    }
}
