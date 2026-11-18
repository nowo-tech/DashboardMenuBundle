<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Service\ResetInterface;

use function array_key_exists;
use function array_keys;
use function md5;

/**
 * Invalidates menu tree cache entries by bumping a per-menu-code version counter.
 * Works with any PSR-6 pool (no tag support required); stale tree keys expire by TTL.
 * Version lookups are memoized per request to avoid a second pool read on every loadTree().
 *
 * 2.23 (REQ-RUNTIME-003 rule 1): a bump made by a flush runs inside the write transaction, so a concurrent reader can
 * still load the old tree and cache it under the new version before the commit. The codes bumped since the last
 * commit are therefore kept, and {@see invalidatePendingAfterCommit()} bumps them again once the bundle's write unit
 * has ended (outside any transaction). Both lists are request state, emptied by {@see reset()}.
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

    /** @var array<string, true> codes bumped since the last after-commit pass */
    private array $pendingAfterCommit = [];

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
        $this->versionMemo[$menuCode]        = $next;
        $this->pendingAfterCommit[$menuCode] = true;
    }

    /**
     * Bumps again every code bumped since the last call (2.23): called by the bundle's write unit once it has ended,
     * so a tree a concurrent reader cached between the flush and the commit stops matching.
     */
    public function invalidatePendingAfterCommit(): void
    {
        $codes                    = array_keys($this->pendingAfterCommit);
        $this->pendingAfterCommit = [];
        foreach ($codes as $code) {
            $this->invalidateForMenuCode($code);
        }

        $this->pendingAfterCommit = [];
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
        $this->versionMemo        = [];
        $this->pendingAfterCommit = [];
    }

    public static function versionKey(string $menuCode): string
    {
        return self::VERSION_KEY_PREFIX . md5($menuCode);
    }
}
