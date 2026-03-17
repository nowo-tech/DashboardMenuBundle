<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DataCollector\Dbal;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;

/**
 * Statement decorator that counts each execute() as one query.
 *
 * @internal
 */
final readonly class MenuQueryCountStatement implements StatementInterface
{
    public function __construct(
        private StatementInterface $inner,
        private MenuQueryCounter $counter,
    ) {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        $this->inner->bindValue($param, $value, $type);
    }

    public function execute(): Result
    {
        $this->counter->recordQuery();

        return $this->inner->execute();
    }
}
