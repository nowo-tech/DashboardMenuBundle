<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector;

use Nowo\DashboardMenuBundle\DataCollector\ChainedSqlLogger;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ChainedSqlLoggerTest extends TestCase
{
    public function testStartQueryRecordsAndForwardsToInner(): void
    {
        $counter = new MenuQueryCounter();
        $inner   = new SqlLoggerSpy();

        $logger = new ChainedSqlLogger($inner, $counter);
        $counter->startSegment();
        $logger->startQuery('SELECT 1');
        $logger->startQuery('SELECT 2', ['a' => 1], ['a' => 'integer']);

        self::assertSame(2, $counter->getSegmentCount());
        self::assertSame([['startQuery', 'SELECT 1', null, null], ['startQuery', 'SELECT 2', ['a' => 1], ['a' => 'integer']]], $inner->calls);
    }

    public function testStartQueryRecordsWhenInnerNull(): void
    {
        $counter = new MenuQueryCounter();
        $logger  = new ChainedSqlLogger(null, $counter);
        $counter->startSegment();
        $logger->startQuery('SELECT 1');
        $logger->startQuery('SELECT 2');

        self::assertSame(2, $counter->getSegmentCount());
    }

    public function testStartQueryDoesNotForwardWhenInnerHasNoStartQuery(): void
    {
        $counter = new MenuQueryCounter();
        $inner   = new stdClass();
        $logger  = new ChainedSqlLogger($inner, $counter);
        $counter->startSegment();
        $logger->startQuery('SELECT 1');

        self::assertSame(1, $counter->getSegmentCount());
    }

    public function testStopQueryForwardsToInner(): void
    {
        $counter = new MenuQueryCounter();
        $inner   = new SqlLoggerSpy();

        $logger = new ChainedSqlLogger($inner, $counter);
        $logger->stopQuery();
        $logger->stopQuery();

        self::assertSame([['stopQuery'], ['stopQuery']], $inner->calls);
    }

    public function testStopQueryNoOpWhenInnerNull(): void
    {
        $counter = new MenuQueryCounter();
        $logger  = new ChainedSqlLogger(null, $counter);
        $logger->stopQuery();
        self::assertSame(0, $counter->getSegmentCount());
    }

    public function testStopQueryNoOpWhenInnerHasNoStopQuery(): void
    {
        $counter = new MenuQueryCounter();
        $inner   = new stdClass();
        $logger  = new ChainedSqlLogger($inner, $counter);
        $logger->stopQuery();
        self::assertSame(0, $counter->getSegmentCount());
    }
}

final class SqlLoggerSpy
{
    /** @var list<list<mixed>> */
    public array $calls = [];

    /**
     * @param mixed $sql
     * @param mixed $params
     * @param mixed $types
     */
    public function startQuery($sql, $params = null, $types = null): void
    {
        $this->calls[] = ['startQuery', $sql, $params, $types];
    }

    public function stopQuery(): void
    {
        $this->calls[] = ['stopQuery'];
    }
}
