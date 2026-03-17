<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountDriver;

/**
 * DBAL middleware that decorates the driver to count every SQL query.
 * Used when Configuration has no getSQLLogger/setSQLLogger (DBAL 4+).
 *
 * @internal
 */
final readonly class MenuQueryCountMiddleware implements Middleware
{
    public function __construct(
        private MenuQueryCounter $counter,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        return new MenuQueryCountDriver($driver, $this->counter);
    }
}
