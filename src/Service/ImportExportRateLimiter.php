<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use function sprintf;

/**
 * Simple rate limiter for dashboard import/export actions.
 * Uses a fixed window: at most limit requests per interval seconds per key (e.g. user/IP).
 * When limit or interval is 0, consume() does nothing.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class ImportExportRateLimiter
{
    private const CACHE_KEY_PREFIX = 'nowo_dashboard_menu_io_';

    private LoggerInterface $logger;

    public function __construct(
        private ?CacheItemPoolInterface $cachePool,
        private int $limit,
        private int $interval,
        private ClockInterface $clock,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Consumes one request for the given key (e.g. user id or IP).
     * Throws TooManyRequestsHttpException when over the limit.
     */
    public function consume(string $key): void
    {
        if ($this->limit <= 0 || $this->interval <= 0 || !$this->cachePool instanceof CacheItemPoolInterface) {
            return;
        }

        $cacheKey = self::CACHE_KEY_PREFIX . hash('sha256', $key);
        $item     = $this->cachePool->getItem($cacheKey);
        $now      = $this->clock->now()->getTimestamp();
        $data     = $item->isHit() ? $item->get() : null;

        if ($data === null || !isset($data['s'], $data['c']) || ($now - (int) $data['s']) >= $this->interval) {
            $data = ['s' => $now, 'c' => 1];
        } else {
            $data['c'] = (int) $data['c'] + 1;
        }

        if ($data['c'] > $this->limit) {
            $this->logger->warning('Dashboard import/export rate limit exceeded.', [
                'bundle'   => 'nowo_dashboard_menu',
                'action'   => 'import_export_rate_limit',
                'limit'    => $this->limit,
                'interval' => $this->interval,
            ]);
            throw new TooManyRequestsHttpException($this->interval, sprintf('Too many import/export requests. Limit is %d per %d seconds.', $this->limit, $this->interval));
        }

        $item->set($data);
        $item->expiresAfter($this->interval + 10);
        $this->cachePool->save($item);
    }
}
