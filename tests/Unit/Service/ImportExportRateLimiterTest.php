<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service;

use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use function is_array;

final class ImportExportRateLimiterTest extends TestCase
{
    public function testConsumeEarlyReturnsWhenLimitOrIntervalInvalid(): void
    {
        $clock     = new MockClock();
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::never())->method('getItem');

        (new ImportExportRateLimiter($cachePool, 0, 60, $clock))->consume('k');
        (new ImportExportRateLimiter($cachePool, 10, 0, $clock))->consume('k');
        (new ImportExportRateLimiter(null, 10, 60, $clock))->consume('k');
    }

    public function testConsumeCreatesNewWindowWhenItemNotHit(): void
    {
        $clock     = new MockClock('2026-07-28T12:00:00+00:00');
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cachePool->expects(self::once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $cacheItem->expects(self::once())
            ->method('isHit')
            ->willReturn(false);

        $cacheItem->expects(self::once())
            ->method('set')
            ->with(self::callback(static function (mixed $data): bool {
                if (!is_array($data)) {
                    return false;
                }

                return isset($data['s'], $data['c']) && (int) $data['c'] === 1;
            }))
            ->willReturn($cacheItem);

        $cacheItem->expects(self::once())
            ->method('expiresAfter')
            ->with(70);

        $cachePool->expects(self::once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $limiter = new ImportExportRateLimiter($cachePool, 10, 60, $clock);
        $limiter->consume('key');
    }

    public function testConsumeIncrementsWindowWhenItemHitWithinInterval(): void
    {
        $clock    = new MockClock('2026-07-28T12:00:00+00:00');
        $now      = $clock->now()->getTimestamp();
        $interval = 60;

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cachePool->expects(self::once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $cacheItem->expects(self::once())
            ->method('isHit')
            ->willReturn(true);

        $cacheItem->expects(self::once())
            ->method('get')
            ->willReturn(['s' => $now - ($interval - 1), 'c' => 1]);

        $cacheItem->expects(self::once())
            ->method('set')
            ->with(self::callback(static function (mixed $data): bool {
                if (!is_array($data)) {
                    return false;
                }

                return isset($data['c']) && (int) $data['c'] === 2;
            }))
            ->willReturn($cacheItem);

        $cacheItem->expects(self::once())
            ->method('expiresAfter')
            ->with($interval + 10);

        $cachePool->expects(self::once())
            ->method('save')
            ->with($cacheItem)
            ->willReturn(true);

        $limiter = new ImportExportRateLimiter($cachePool, 10, $interval, $clock);
        $limiter->consume('key');
    }

    public function testConsumeThrowsWhenOverLimit(): void
    {
        $clock    = new MockClock('2026-07-28T12:00:00+00:00');
        $now      = $clock->now()->getTimestamp();
        $interval = 60;
        $limit    = 2;

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $cachePool->expects(self::once())
            ->method('getItem')
            ->willReturn($cacheItem);

        $cacheItem->expects(self::once())
            ->method('isHit')
            ->willReturn(true);

        $cacheItem->expects(self::once())
            ->method('get')
            ->willReturn(['s' => $now - ($interval - 1), 'c' => $limit]);

        $cachePool->expects(self::never())
            ->method('save');

        $this->expectException(TooManyRequestsHttpException::class);

        $limiter = new ImportExportRateLimiter($cachePool, $limit, $interval, $clock);
        $limiter->consume('key');
    }
}
