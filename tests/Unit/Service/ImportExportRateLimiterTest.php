<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use function is_array;

final class ImportExportRateLimiterTest extends TestCase
{
    public function testConsumeEarlyReturnsWhenLimitOrIntervalInvalid(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool->expects(self::never())->method('getItem');

        (new ImportExportRateLimiter($cachePool, 0, 60))->consume('k');
        (new ImportExportRateLimiter($cachePool, 10, 0))->consume('k');
        (new ImportExportRateLimiter(null, 10, 60))->consume('k');
    }

    public function testConsumeCreatesNewWindowWhenItemNotHit(): void
    {
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

        $limiter = new ImportExportRateLimiter($cachePool, 10, 60);
        $limiter->consume('key');
    }

    public function testConsumeIncrementsWindowWhenItemHitWithinInterval(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $now      = time();
        $interval = 60;

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

        $limiter = new ImportExportRateLimiter($cachePool, 10, $interval);
        $limiter->consume('key');
    }

    public function testConsumeThrowsWhenOverLimit(): void
    {
        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = $this->createMock(CacheItemInterface::class);

        $now      = time();
        $interval = 60;
        $limit    = 2;

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

        $limiter = new ImportExportRateLimiter($cachePool, $limit, $interval);
        $limiter->consume('key');
    }
}
