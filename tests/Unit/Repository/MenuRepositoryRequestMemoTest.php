<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Repository;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;

final class MenuRepositoryRequestMemoTest extends TestCase
{
    public function testFindOneByCodeAndContextMemoizesHitsAndNullsUntilReset(): void
    {
        $menu = new Menu();
        $menu->setCode('staff');

        $repo = $this->getMockBuilder(MenuRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $repo->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls($menu, null);

        self::assertSame($menu, $repo->findOneByCodeAndContext('staff', null));
        self::assertSame($menu, $repo->findOneByCodeAndContext('staff', []));
        self::assertSame($menu, $repo->findOneByCodeAndContext('staff', null));

        $repo->reset();

        self::assertNull($repo->findOneByCodeAndContext('staff', null));
        self::assertNull($repo->findOneByCodeAndContext('staff', null));
    }
}
