<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
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

    public function __construct(
        private ?CacheItemPoolInterface $cachePool,
        private int $limit,
        private int $interval,
    ) {
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
        $now      = time();
        $data     = $item->isHit() ? $item->get() : null;

        if ($data === null || !isset($data['s'], $data['c']) || ($now - (int) $data['s']) >= $this->interval) {
            $data = ['s' => $now, 'c' => 1];
        } else {
            $data['c'] = (int) $data['c'] + 1;
        }

        if ($data['c'] > $this->limit) {
            throw new TooManyRequestsHttpException($this->interval, sprintf('Too many import/export requests. Limit is %d per %d seconds.', $this->limit, $this->interval));
        }

        $item->set($data);
        $item->expiresAfter($this->interval + 10);
        $this->cachePool->save($item);
    }
}
