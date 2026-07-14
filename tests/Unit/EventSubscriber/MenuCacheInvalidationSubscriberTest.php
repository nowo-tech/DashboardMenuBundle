<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\EventSubscriber;

use DateInterval;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\UnitOfWork;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\EventSubscriber\MenuCacheInvalidationSubscriber;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use function array_key_exists;
use function is_int;

final class MenuCacheInvalidationSubscriberTest extends TestCase
{
    public function testPostPersistInvalidatesMenuCode(): void
    {
        $stored      = [];
        $invalidator = new MenuTreeCacheInvalidator($this->createInMemoryPool($stored));

        $menu = new Menu();
        $menu->setCode('sidebar');

        $subscriber = new MenuCacheInvalidationSubscriber($invalidator);
        $subscriber->postPersist(new PostPersistEventArgs($menu, $this->createEntityManager()));

        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));
    }

    public function testPostPersistInvalidatesMenuCodeFromMenuItem(): void
    {
        $stored      = [];
        $invalidator = new MenuTreeCacheInvalidator($this->createInMemoryPool($stored));

        $menu = new Menu();
        $menu->setCode('footer');
        $item = new MenuItem();
        $item->setMenu($menu);

        $subscriber = new MenuCacheInvalidationSubscriber($invalidator);
        $subscriber->postPersist(new PostPersistEventArgs($item, $this->createEntityManager()));

        self::assertSame(1, $invalidator->getVersionForMenuCode('footer'));
    }

    public function testPostUpdateInvalidatesOldAndNewMenuCodeWhenCodeChanges(): void
    {
        $stored      = [];
        $invalidator = new MenuTreeCacheInvalidator($this->createInMemoryPool($stored));

        $menu = new Menu();
        $menu->setCode('new-code');

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getEntityChangeSet')->with($menu)->willReturn(['code' => ['old-code', 'new-code']]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $subscriber = new MenuCacheInvalidationSubscriber($invalidator);
        $subscriber->postUpdate(new PostUpdateEventArgs($menu, $em));

        self::assertSame(1, $invalidator->getVersionForMenuCode('new-code'));
        self::assertSame(1, $invalidator->getVersionForMenuCode('old-code'));
    }

    public function testPostRemoveInvalidatesMenuCode(): void
    {
        $stored      = [];
        $invalidator = new MenuTreeCacheInvalidator($this->createInMemoryPool($stored));

        $menu = new Menu();
        $menu->setCode('aside');

        $subscriber = new MenuCacheInvalidationSubscriber($invalidator);
        $subscriber->postRemove(new PostRemoveEventArgs($menu, $this->createEntityManager()));

        self::assertSame(1, $invalidator->getVersionForMenuCode('aside'));
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getEntityChangeSet')->willReturn([]);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        return $em;
    }

    /**
     * @param array<string, array{value: mixed, ttl: int|null}> $stored
     */
    private function createInMemoryPool(array &$stored): CacheItemPoolInterface
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturnCallback(static function (string $key) use (&$stored): CacheItemInterface {
            if (!array_key_exists($key, $stored)) {
                $stored[$key] = ['value' => null, 'ttl' => null];
            }

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
                    return $this->stored[$this->key]['value'];
                }

                public function isHit(): bool
                {
                    return $this->stored[$this->key]['value'] !== null;
                }

                public function set(mixed $value): static
                {
                    $this->stored[$this->key]['value'] = $value;

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
            $stored[$item->getKey()]['value'] = $item->get();

            return true;
        });

        return $pool;
    }
}
