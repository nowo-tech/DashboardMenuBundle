<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector\Dbal;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use SensitiveParameter;

/**
 * Driver decorator that wraps the connection so every executed query is counted.
 * Extends AbstractDriverMiddleware when available (DBAL 3.3+), else implements Driver.
 *
 * @internal
 */
final class MenuQueryCountDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly MenuQueryCounter $counter,
    ) {
        parent::__construct($driver);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        $inner = parent::connect($params);

        return new MenuQueryCountConnection($inner, $this->counter);
    }
}
