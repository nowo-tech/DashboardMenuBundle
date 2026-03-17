<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DataCollector;

use Nowo\DashboardMenuBundle\DataCollector\ChainedSqlLogger;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MenuQueryCounterTest extends TestCase
{
    public function testStartSegmentAndGetSegmentCount(): void
    {
        $counter = new MenuQueryCounter();
        self::assertSame(0, $counter->getSegmentCount());

        $counter->startSegment();
        self::assertSame(0, $counter->getSegmentCount());

        $counter->recordQuery();
        $counter->recordQuery();
        self::assertSame(2, $counter->getSegmentCount());

        $counter->startSegment();
        self::assertSame(0, $counter->getSegmentCount());
        $counter->recordQuery();
        self::assertSame(1, $counter->getSegmentCount());
    }

    public function testRecordQueryIncrementsCount(): void
    {
        $counter = new MenuQueryCounter();
        $counter->recordQuery();
        $counter->recordQuery();
        $counter->startSegment();
        $counter->recordQuery();
        self::assertSame(1, $counter->getSegmentCount());
    }

    public function testResetZerosCountAndAllowsNewSegment(): void
    {
        $counter = new MenuQueryCounter();
        $counter->recordQuery();
        $counter->recordQuery();
        $counter->startSegment();
        $counter->recordQuery();
        $counter->reset();

        self::assertSame(0, $counter->getSegmentCount());
        $counter->startSegment();
        $counter->recordQuery();
        self::assertSame(1, $counter->getSegmentCount());
    }

    public function testWrapConnectionNoOpWhenConnectionGetConfigurationThrows(): void
    {
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willThrowException(new RuntimeException('No config'));

        $counter = new MenuQueryCounter();
        $counter->wrapConnection($connection);

        $counter->startSegment();
        self::assertSame(0, $counter->getSegmentCount());
    }

    public function testWrapConnectionWrapsLoggerWhenSupported(): void
    {
        $config     = new ConfigurationStub();
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willReturn($config);

        $counter = new MenuQueryCounter();
        $counter->wrapConnection($connection);

        self::assertInstanceOf(ChainedSqlLogger::class, $config->getSQLLogger());
        self::assertSame($counter, $config->getSQLLogger()->counter);

        $counter->startSegment();
        $config->getSQLLogger()->startQuery('SELECT 1');
        $config->getSQLLogger()->startQuery('SELECT 2');
        self::assertSame(2, $counter->getSegmentCount());
    }

    public function testWrapConnectionForwardsToInnerLogger(): void
    {
        $innerCalls = [];
        $inner      = new class($innerCalls) {
            public function __construct(private array &$calls)
            {
            }

            public function startQuery($sql, $params = null, $types = null): void
            {
                $this->calls[] = ['startQuery', $sql, $params, $types];
            }

            public function stopQuery(): void
            {
                $this->calls[] = ['stopQuery'];
            }
        };
        $config         = new ConfigurationStub();
        $config->logger = $inner;
        $connection     = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willReturn($config);

        $counter = new MenuQueryCounter();
        $counter->wrapConnection($connection);

        $chain = $config->getSQLLogger();
        $chain->startQuery('SELECT * FROM t', ['id' => 1], []);
        $chain->stopQuery();

        self::assertSame([['startQuery', 'SELECT * FROM t', ['id' => 1], []], ['stopQuery']], $innerCalls);
    }

    public function testWrapConnectionOnlyWrapsOnce(): void
    {
        $config     = new ConfigurationStub();
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willReturn($config);

        $counter = new MenuQueryCounter();
        $counter->wrapConnection($connection);
        $counter->wrapConnection($connection);

        self::assertSame(1, $config->setCalls);
    }

    public function testWrapConnectionWhenCurrentLoggerIsAlreadyOurChainWithSameCounterDoesNotDoubleWrap(): void
    {
        $counter        = new MenuQueryCounter();
        $chain          = new ChainedSqlLogger(null, $counter);
        $config         = new ConfigurationStub();
        $config->logger = $chain;
        $connection     = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willReturn($config);

        $counter->wrapConnection($connection);
        self::assertSame(0, $config->setCalls, 'When current logger is already our chain, setSQLLogger must not be called');
    }

    /**
     * Covers the branch when config does not support SQLLogger (e.g. DBAL 4).
     * Uses injected callable returning false so wrapConnection returns early without wrapping.
     */
    public function testWrapConnectionNoOpWhenConfigDoesNotSupportSQLLogger(): void
    {
        $config     = new ConfigurationStub();
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willReturn($config);

        $counter = new MenuQueryCounter(static fn (object $c): bool => false);
        $counter->wrapConnection($connection);

        $counter->startSegment();
        self::assertSame(0, $counter->getSegmentCount());
        self::assertSame(0, $config->setCalls, 'setSQLLogger must not be called when support check returns false');
    }
}
