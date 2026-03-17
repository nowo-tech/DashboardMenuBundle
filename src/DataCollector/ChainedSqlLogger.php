<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector;

/**
 * Wraps an existing SQLLogger and forwards calls while recording each query on MenuQueryCounter.
 * Compatible with Doctrine\DBAL\Logging\SQLLogger (DBAL 2/3); only used when that API exists.
 *
 * @internal
 */
final readonly class ChainedSqlLogger
{
    public function __construct(
        private ?object $inner,
        public MenuQueryCounter $counter,
    ) {
    }

    public function startQuery(string $sql, mixed $params = null, mixed $types = null): void
    {
        $this->counter->recordQuery();
        if ($this->inner !== null && method_exists($this->inner, 'startQuery')) {
            $this->inner->startQuery($sql, $params, $types);
        }
    }

    public function stopQuery(): void
    {
        if ($this->inner !== null && method_exists($this->inner, 'stopQuery')) {
            $this->inner->stopQuery();
        }
    }
}
