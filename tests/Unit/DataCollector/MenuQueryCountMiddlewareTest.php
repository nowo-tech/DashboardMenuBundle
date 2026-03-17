<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector;

use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountDriver;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCountMiddleware;
use PHPUnit\Framework\TestCase;

final class MenuQueryCountMiddlewareTest extends TestCase
{
    public function testWrapReturnsMenuQueryCountDriver(): void
    {
        $counter = new MenuQueryCounter();
        $driver  = $this->createMock(\Doctrine\DBAL\Driver::class);

        $middleware = new MenuQueryCountMiddleware($counter);
        $wrapped    = $middleware->wrap($driver);

        self::assertInstanceOf(MenuQueryCountDriver::class, $wrapped);
    }
}
