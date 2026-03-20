<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;

final class MenuRepositoryFindMenuAndItemsRawTest extends TestCase
{
    public function testFindMenuAndItemsRawFallsBackToFirstMenuRowWhenNoContextKeysMatch(): void
    {
        $menuTable = 'dashboard_menu';
        $itemTable = 'dashboard_menu_item';

        $metaMenu = $this->createMock(ClassMetadata::class);
        $metaMenu->method('getTableName')->willReturn($menuTable);

        $metaItem = $this->createMock(ClassMetadata::class);
        $metaItem->method('getTableName')->willReturn($itemTable);

        $conn = $this->createMock(Connection::class);
        $conn->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());

        $wantKey  = Menu::canonicalContextKey(['tenant' => 'acme']);
        $menuRows = [
            [
                'id'             => 5,
                'code'           => 'raw',
                'attributes_key' => 'wrong-' . $wantKey,
            ],
        ];
        $itemRows = [
            ['id' => 1, 'label' => 'Item1', 'parent_id' => null, 'position' => 0],
        ];

        $conn->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls($menuRows, $itemRows);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->method('getClassMetadata')->willReturnCallback(
            static fn(string $class): object => $class === Menu::class ? $metaMenu : $metaItem,
        );

        $repo = $this->getMockBuilder(MenuRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $repo->method('getEntityManager')->willReturn($em);

        $raw = $repo->findMenuAndItemsRaw('raw', [['tenant' => 'acme']]);

        self::assertIsArray($raw);
        self::assertSame(5, $raw['menu']['id'] ?? null);
        self::assertCount(1, $raw['items']);
        self::assertSame('Item1', $raw['items'][0]['label'] ?? null);
    }

    public function testFindMenuAndItemsRawReturnsNullWhenMenuRowHasNoId(): void
    {
        $menuTable = 'dashboard_menu';
        $itemTable = 'dashboard_menu_item';

        $metaMenu = $this->createMock(ClassMetadata::class);
        $metaMenu->method('getTableName')->willReturn($menuTable);

        $metaItem = $this->createMock(ClassMetadata::class);
        $metaItem->method('getTableName')->willReturn($itemTable);

        $conn = $this->createMock(Connection::class);
        $conn->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());

        $menuRows = [
            [
                // Missing/null menu id => should return null.
                'id'             => null,
                'code'           => 'raw',
                'attributes_key' => 'no-match',
            ],
        ];

        $conn->method('fetchAllAssociative')->willReturn($menuRows);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($conn);
        $em->method('getClassMetadata')->willReturnCallback(
            static fn(string $class): object => $class === Menu::class ? $metaMenu : $metaItem,
        );

        $repo = $this->getMockBuilder(MenuRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getEntityManager'])
            ->getMock();
        $repo->method('getEntityManager')->willReturn($em);

        self::assertNull($repo->findMenuAndItemsRaw('raw', [['tenant' => 'acme']]));
    }
}
