<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function md5;

/**
 * Invalidates menu tree cache entries by bumping a per-menu-code version counter.
 * Works with any PSR-6 pool (no tag support required); stale tree keys expire by TTL.
 * Version lookups are memoized per request to avoid a second pool read on every loadTree().
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
class MenuTreeCacheInvalidator implements ResetInterface
{
    private const VERSION_KEY_PREFIX = 'nowo_dashboard_menu.tree_version.';

    /** Version counters are kept long-lived so bumped trees stop matching loader keys immediately. */
    private const VERSION_TTL_SECONDS = 31_536_000;

    /** @var array<string, int> */
    private array $versionMemo = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $cachePool = null,
    ) {
    }

    public function invalidateForMenuCode(string $menuCode): void
    {
        if ($menuCode === '' || !$this->cachePool instanceof CacheItemPoolInterface) {
            return;
        }

        $item = $this->cachePool->getItem(self::versionKey($menuCode));
        $next = $item->isHit() ? ((int) $item->get()) + 1 : 1;
        $item->set($next);
        $item->expiresAfter(self::VERSION_TTL_SECONDS);
        $this->cachePool->save($item);
        $this->versionMemo[$menuCode] = $next;
    }

    public function getVersionForMenuCode(string $menuCode): int
    {
        if ($menuCode === '' || !$this->cachePool instanceof CacheItemPoolInterface) {
            return 0;
        }

        if (array_key_exists($menuCode, $this->versionMemo)) {
            return $this->versionMemo[$menuCode];
        }

        $item                         = $this->cachePool->getItem(self::versionKey($menuCode));
        $version                      = $item->isHit() ? (int) $item->get() : 0;
        $this->versionMemo[$menuCode] = $version;

        return $version;
    }

    public function reset(): void
    {
        $this->versionMemo = [];
    }

    public static function versionKey(string $menuCode): string
    {
        return self::VERSION_KEY_PREFIX . md5($menuCode);
    }
}
