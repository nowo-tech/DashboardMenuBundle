<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MenuRepositoryPrivateMethodsTest extends TestCase
{
    public function testQuoteTableNameUsesPlatformQuotes(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $repo = new MenuRepository($registry);

        $conn = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDatabasePlatform'])
            ->getMock();
        $conn->method('getDatabasePlatform')->willReturn(new PostgreSQLPlatform());

        $ref = new ReflectionClass($repo);
        $m   = $ref->getMethod('quoteTableName');
        $m->setAccessible(true);

        self::assertSame('"dashboard_menu"', $m->invoke($repo, $conn, 'dashboard_menu'));
    }
}
