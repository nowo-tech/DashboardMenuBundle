<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector\Dbal;

use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountConnection;
use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountDriver;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use PHPUnit\Framework\TestCase;

final class MenuQueryCountDriverTest extends TestCase
{
    public function testConnectReturnsMenuQueryCountConnection(): void
    {
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerDriver     = $this->createMock(\Doctrine\DBAL\Driver::class);
        $innerDriver->method('connect')->willReturn($innerConnection);

        $counter = new MenuQueryCounter();
        $driver  = new MenuQueryCountDriver($innerDriver, $counter);

        $connection = $driver->connect([]);

        self::assertInstanceOf(MenuQueryCountConnection::class, $connection);
    }
}
