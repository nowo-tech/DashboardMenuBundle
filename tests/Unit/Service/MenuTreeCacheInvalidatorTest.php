<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use DateInterval;
use DateTimeInterface;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use function array_key_exists;
use function is_int;

final class MenuTreeCacheInvalidatorTest extends TestCase
{
    public function testInvalidateForMenuCodeIsNoOpWhenPoolIsNull(): void
    {
        $invalidator = new MenuTreeCacheInvalidator();

        $invalidator->invalidateForMenuCode('sidebar');

        self::assertSame(0, $invalidator->getVersionForMenuCode('sidebar'));
    }

    public function testInvalidateForMenuCodeBumpsVersion(): void
    {
        $stored      = [];
        $pool        = $this->createInMemoryPool($stored);
        $invalidator = new MenuTreeCacheInvalidator($pool);

        self::assertSame(0, $invalidator->getVersionForMenuCode('sidebar'));

        $invalidator->invalidateForMenuCode('sidebar');
        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));

        $invalidator->invalidateForMenuCode('sidebar');
        self::assertSame(2, $invalidator->getVersionForMenuCode('sidebar'));
    }

    public function testInvalidateForMenuCodeIgnoresEmptyCode(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::never())->method('getItem');

        $invalidator = new MenuTreeCacheInvalidator($pool);
        $invalidator->invalidateForMenuCode('');
    }

    public function testGetVersionForMenuCodeMemoizesPerRequest(): void
    {
        $versionItem = $this->createMock(CacheItemInterface::class);
        $versionItem->method('isHit')->willReturn(true);
        $versionItem->method('get')->willReturn(3);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::once())
            ->method('getItem')
            ->with(MenuTreeCacheInvalidator::versionKey('sidebar'))
            ->willReturn($versionItem);

        $invalidator = new MenuTreeCacheInvalidator($pool);

        self::assertSame(3, $invalidator->getVersionForMenuCode('sidebar'));
        self::assertSame(3, $invalidator->getVersionForMenuCode('sidebar'));
    }

    public function testResetClearsVersionMemoForFrankenPhpWorkerMode(): void
    {
        $versionItem = $this->createMock(CacheItemInterface::class);
        $versionItem->method('isHit')->willReturn(true);
        $versionItem->method('get')->willReturnOnConsecutiveCalls(1, 2);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::exactly(2))
            ->method('getItem')
            ->with(MenuTreeCacheInvalidator::versionKey('sidebar'))
            ->willReturn($versionItem);

        $invalidator = new MenuTreeCacheInvalidator($pool);

        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));
        $invalidator->reset();
        self::assertSame(2, $invalidator->getVersionForMenuCode('sidebar'));
    }

    public function testInvalidateUpdatesMemoWithoutExtraPoolReadOnSubsequentGet(): void
    {
        $versionItem = $this->createMock(CacheItemInterface::class);
        $versionItem->method('isHit')->willReturn(false);
        $versionItem->method('set')->willReturnSelf();
        $versionItem->method('expiresAfter')->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::once())
            ->method('getItem')
            ->with(MenuTreeCacheInvalidator::versionKey('sidebar'))
            ->willReturn($versionItem);
        $pool->expects(self::once())->method('save')->with($versionItem);

        $invalidator = new MenuTreeCacheInvalidator($pool);
        $invalidator->invalidateForMenuCode('sidebar');

        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));
        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));
    }

    /**
     * @param array<string, array{value: mixed, ttl: int|null}> $stored
     */
    private function createInMemoryPool(array &$stored): CacheItemPoolInterface
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(static function (string $key) use (&$stored): CacheItemInterface {
            return new class($key, $stored) implements CacheItemInterface {
                /** @param array<string, array{value: mixed, ttl: int|null}> $stored */
                public function __construct(
                    private readonly string $key,
                    private array &$stored,
                ) {
                }

                public function getKey(): string
                {
                    return $this->key;
                }

                public function get(): mixed
                {
                    return $this->stored[$this->key]['value'] ?? null;
                }

                public function isHit(): bool
                {
                    return array_key_exists($this->key, $this->stored) && $this->stored[$this->key]['value'] !== null;
                }

                public function set(mixed $value): static
                {
                    $this->stored[$this->key] = ['value' => $value, 'ttl' => $this->stored[$this->key]['ttl'] ?? null];

                    return $this;
                }

                public function expiresAt(?DateTimeInterface $expiration): static
                {
                    return $this;
                }

                public function expiresAfter(int|DateInterval|null $time): static
                {
                    $this->stored[$this->key]['ttl'] = is_int($time) ? $time : null;

                    return $this;
                }

                public function tag(array $tags): static
                {
                    return $this;
                }

                public function getMetadata(): array
                {
                    return [];
                }
            };
        });
        $pool->method('save')->willReturnCallback(static function (CacheItemInterface $item) use (&$stored): bool {
            $stored[$item->getKey()] = ['value' => $item->get(), 'ttl' => $stored[$item->getKey()]['ttl'] ?? null];

            return true;
        });

        return $pool;
    }
}
