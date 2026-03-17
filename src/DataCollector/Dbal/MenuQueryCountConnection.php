<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector\Dbal;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;

/**
 * Connection decorator that wraps prepare() so executed statements are counted.
 *
 * @internal
 */
final readonly class MenuQueryCountConnection implements Connection
{
    public function __construct(
        private Connection $inner,
        private MenuQueryCounter $counter,
    ) {
    }

    public function prepare(string $sql): Statement
    {
        return new MenuQueryCountStatement($this->inner->prepare($sql), $this->counter);
    }

    public function quote(string $value): string
    {
        return $this->inner->quote($value);
    }

    public function exec(string $sql): int|string
    {
        $this->counter->recordQuery();

        return $this->inner->exec($sql);
    }

    public function query(string $sql): Result
    {
        $this->counter->recordQuery();

        return $this->inner->query($sql);
    }

    public function getNativeConnection(): mixed
    {
        return $this->inner->getNativeConnection();
    }

    public function beginTransaction(): void
    {
        $this->inner->beginTransaction();
    }

    public function commit(): void
    {
        $this->inner->commit();
    }

    public function rollBack(): void
    {
        $this->inner->rollBack();
    }

    public function lastInsertId(): int|string
    {
        return $this->inner->lastInsertId();
    }

    public function getServerVersion(): string
    {
        return $this->inner->getServerVersion();
    }
}
