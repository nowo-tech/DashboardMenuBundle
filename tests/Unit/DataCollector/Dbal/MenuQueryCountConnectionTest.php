<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector\Dbal;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountConnection;
use Nowo\DashboardMenuBundle\DataCollector\Dbal\MenuQueryCountStatement;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use PDO;
use PHPUnit\Framework\TestCase;

final class MenuQueryCountConnectionTest extends TestCase
{
    public function testPrepareReturnsMenuQueryCountStatement(): void
    {
        $innerStatement  = $this->createMock(StatementInterface::class);
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerConnection->method('prepare')->with('SELECT 1')->willReturn($innerStatement);

        $counter    = new MenuQueryCounter();
        $connection = new MenuQueryCountConnection($innerConnection, $counter);

        $statement = $connection->prepare('SELECT 1');

        self::assertInstanceOf(MenuQueryCountStatement::class, $statement);
    }

    public function testQuoteDelegatesToInner(): void
    {
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerConnection->method('quote')->with("o'reilly")->willReturn("'o''reilly'");

        $counter    = new MenuQueryCounter();
        $connection = new MenuQueryCountConnection($innerConnection, $counter);

        self::assertSame("'o''reilly'", $connection->quote("o'reilly"));
    }

    public function testExecRecordsQueryAndDelegatesToInner(): void
    {
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerConnection->method('exec')->with('DELETE FROM t')->willReturn(5);

        $counter = new MenuQueryCounter();
        $counter->startSegment();
        $connection = new MenuQueryCountConnection($innerConnection, $counter);

        $result = $connection->exec('DELETE FROM t');

        self::assertSame(5, $result);
        self::assertSame(1, $counter->getSegmentCount());
    }

    public function testQueryRecordsQueryAndDelegatesToInner(): void
    {
        $innerResult     = $this->createMock(Result::class);
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerConnection->method('query')->with('SELECT 1')->willReturn($innerResult);

        $counter = new MenuQueryCounter();
        $counter->startSegment();
        $connection = new MenuQueryCountConnection($innerConnection, $counter);

        $result = $connection->query('SELECT 1');

        self::assertSame($innerResult, $result);
        self::assertSame(1, $counter->getSegmentCount());
    }

    public function testGetNativeConnectionDelegatesToInner(): void
    {
        $pdo             = new PDO('sqlite::memory:');
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerConnection->method('getNativeConnection')->willReturn($pdo);

        $counter    = new MenuQueryCounter();
        $connection = new MenuQueryCountConnection($innerConnection, $counter);

        self::assertSame($pdo, $connection->getNativeConnection());
    }

    public function testBeginTransactionCommitRollBackLastInsertIdGetServerVersionDelegateToInner(): void
    {
        $innerConnection = $this->createMock(\Doctrine\DBAL\Driver\Connection::class);
        $innerConnection->expects(self::once())->method('beginTransaction');
        $innerConnection->expects(self::once())->method('commit');
        $innerConnection->expects(self::once())->method('rollBack');
        $innerConnection->method('lastInsertId')->willReturn('42');
        $innerConnection->method('getServerVersion')->willReturn('1.0');

        $counter    = new MenuQueryCounter();
        $connection = new MenuQueryCountConnection($innerConnection, $counter);

        $connection->beginTransaction();
        $connection->commit();
        $connection->rollBack();
        self::assertSame('42', $connection->lastInsertId());
        self::assertSame('1.0', $connection->getServerVersion());
    }
}
