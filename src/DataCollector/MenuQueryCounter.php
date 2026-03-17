<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector;

use Closure;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/**
 * Counts SQL queries in segments so the profiler can report per-menu query counts.
 * Wraps the DBAL connection's SQLLogger when supported (DBAL 2/3); no-op when not.
 *
 * @internal
 */
final class MenuQueryCounter implements ResetInterface
{
    private int $count = 0;

    private int $segmentStart = 0;

    private bool $wrapped = false;

    public function __construct(
        /** @var Closure(object): bool|null Optional; when null, uses default check (getSQLLogger/setSQLLogger). Used for testing no-op path. */
        private readonly ?Closure $configSupportsSQLLogger = null
    )
    {
    }

    public function startSegment(): void
    {
        $this->segmentStart = $this->count;
    }

    public function getSegmentCount(): int
    {
        return $this->count - $this->segmentStart;
    }

    public function recordQuery(): void
    {
        ++$this->count;
    }

    /**
     * Wraps the connection's SQLLogger so queries are counted. Only wraps once per connection.
     * No-op if the connection does not support getConfiguration/getSQLLogger/setSQLLogger.
     */
    public function wrapConnection(Connection $connection): void
    {
        if ($this->wrapped) {
            return;
        }
        try {
            $config = $connection->getConfiguration();
        } catch (Throwable) {
            return;
        }
        $supports = $this->configSupportsSQLLogger ?? static fn (object $c): bool => method_exists($c, 'getSQLLogger') && method_exists($c, 'setSQLLogger');
        if (!$supports($config)) {
            return;
        }
        /** @phpstan-ignore method.notFound (DBAL 2/3 Configuration) */
        $current = $config->getSQLLogger();
        if ($current instanceof ChainedSqlLogger && $current->counter === $this) {
            $this->wrapped = true;

            return;
        }
        /* @phpstan-ignore method.notFound (DBAL 2/3 Configuration) */
        $config->setSQLLogger(new ChainedSqlLogger($current, $this));
        $this->wrapped = true;
    }

    public function reset(): void
    {
        $this->count        = 0;
        $this->segmentStart = 0;
        $this->wrapped      = false;
    }
}
