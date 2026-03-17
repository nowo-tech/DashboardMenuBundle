<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector\Dbal;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountStatement;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use PHPUnit\Framework\TestCase;

final class MenuQueryCountStatementTest extends TestCase
{
    public function testBindValueDelegatesToInner(): void
    {
        $innerStatement = $this->createMock(StatementInterface::class);
        $innerStatement->expects(self::once())->method('bindValue')->with(1, 'val', ParameterType::STRING);

        $counter   = new MenuQueryCounter();
        $statement = new MenuQueryCountStatement($innerStatement, $counter);

        $statement->bindValue(1, 'val', ParameterType::STRING);
    }

    public function testExecuteRecordsQueryAndDelegatesToInner(): void
    {
        $innerResult    = $this->createMock(Result::class);
        $innerStatement = $this->createMock(StatementInterface::class);
        $innerStatement->method('execute')->willReturn($innerResult);

        $counter = new MenuQueryCounter();
        $counter->startSegment();
        $statement = new MenuQueryCountStatement($innerStatement, $counter);

        $result = $statement->execute();

        self::assertSame($innerResult, $result);
        self::assertSame(1, $counter->getSegmentCount());
    }
}
