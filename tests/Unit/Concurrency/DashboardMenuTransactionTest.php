<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Concurrency;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\DashboardMenuBundle\Concurrency\DashboardMenuTransaction;
use Nowo\DashboardMenuBundle\Doctrine\DashboardMenuEntityManagerResolver;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * REQ-RUNTIME-003 rules 1 and 2 (2.23): the bundle's write unit on the host's manager and on its own manager.
 */
final class DashboardMenuTransactionTest extends TestCase
{
    public function testHostUnitFlushesBeforeTheCommitAndReturnsTheResult(): void
    {
        $connection = $this->connection();
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $manager = $this->manager($connection, $this->unitOfWork());
        $manager->expects(self::once())->method('flush');

        $result = (new DashboardMenuTransaction($manager))->run(static fn (EntityManagerInterface $em): string => $em === $manager ? 'ok' : 'other');

        self::assertSame('ok', $result);
    }

    public function testPendingMenuChangesAreTheBundlesOwnAndAreFlushed(): void
    {
        $unitOfWork = $this->unitOfWork(insertions: [new MenuItem()], updates: [new Menu()], collections: [$this->collection(new Menu())]);
        $manager    = $this->manager($this->connection(), $unitOfWork);
        $manager->expects(self::once())->method('flush');

        $result = new stdClass();
        self::assertSame($result, (new DashboardMenuTransaction($manager))->run(static fn (): stdClass => $result));
    }

    public function testPendingHostEntityRefusesTheUnit(): void
    {
        $connection = $this->connection();
        $connection->expects(self::never())->method('beginTransaction');
        $manager = $this->manager($connection, $this->unitOfWork(deletions: [new stdClass()]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DashboardMenuTransaction::HOST_PENDING_MESSAGE);
        (new DashboardMenuTransaction($manager))->run(static fn (): null => null);
    }

    public function testPendingHostCollectionRefusesTheUnit(): void
    {
        $manager = $this->manager($this->connection(), $this->unitOfWork(collections: [$this->collection(new stdClass())]));

        $this->expectException(LogicException::class);
        (new DashboardMenuTransaction($manager))->run(static fn (): null => null);
    }

    public function testFailedUnitOnAnOpenHostManagerDetachesOnlyTheMenuEntities(): void
    {
        $menu       = new Menu();
        $item       = new MenuItem();
        $newItem    = new MenuItem();
        $hostEntity = new stdClass();
        $unitOfWork = $this->unitOfWork();
        $unitOfWork->method('getIdentityMap')->willReturn([
            Menu::class     => [1 => $menu],
            MenuItem::class => [2 => $item],
            stdClass::class => [3 => $hostEntity],
        ]);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturnOnConsecutiveCalls([], [$newItem, $hostEntity]);
        $manager = $this->manager($this->connection(), $unitOfWork);
        $manager->method('isOpen')->willReturn(true);
        $manager->method('contains')->willReturnCallback(static fn (object $entity): bool => $entity !== $item);
        $detached = [];
        $manager->method('detach')->willReturnCallback(static function (object $entity) use (&$detached): void {
            $detached[] = $entity;
        });
        $manager->expects(self::never())->method('clear');
        $manager->expects(self::never())->method('close');

        $caught = null;
        try {
            (new DashboardMenuTransaction($manager))->run(static function (): never {
                throw new RuntimeException('refused');
            });
        } catch (RuntimeException $failure) {
            $caught = $failure->getMessage();
        }
        self::assertSame('refused', $caught);

        self::assertSame([$newItem, $menu], $detached);
    }

    public function testAFailedUnitClearsTheCodeLookupMemo(): void
    {
        $manager = $this->manager($this->connection(), $this->unitOfWork());
        $menus   = $this->createMock(MenuRepository::class);
        $menus->expects(self::once())->method('reset');

        $this->expectException(TransientStorageFailure::class);
        (new DashboardMenuTransaction($manager, menuRepository: $menus))->run(static function (): never {
            throw StorageErrors::deadlock();
        });
    }

    public function testFailedUnitReplacesAClosedHostManagerThroughTheRegistry(): void
    {
        $manager = $this->manager($this->connection(), $this->unitOfWork());
        $manager->method('isOpen')->willReturn(false);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->method('getManager')->willReturn($manager);
        $registry->expects(self::once())->method('resetManager')->with('default');

        $this->expectException(TransientStorageFailure::class);
        (new DashboardMenuTransaction(new DashboardMenuEntityManagerResolver($registry), $registry))->run(static function (): never {
            throw StorageErrors::deadlock();
        });
    }

    public function testAClosedManagerGivenDirectlyIsLeftToTheHost(): void
    {
        $manager = $this->manager($this->connection(), $this->unitOfWork());
        $manager->method('isOpen')->willReturn(false);
        $manager->expects(self::never())->method('detach');

        $this->expectException(TransientStorageFailure::class);
        (new DashboardMenuTransaction($manager))->run(static function (): never {
            throw StorageErrors::lockWaitTimeout();
        });
    }

    public function testOwnManagerRunsOnTheEntityManagerRunner(): void
    {
        $connection = $this->connection();
        $connection->expects(self::once())->method('commit');
        $manager = $this->manager($connection, $this->unitOfWork());
        $manager->method('getEventManager')->willReturn(new EventManager());
        $manager->expects(self::once())->method('flush');

        $unit = new DashboardMenuTransaction($manager, $this->createMock(ManagerRegistry::class), 'dashboard_menu', true);

        $result = new stdClass();
        self::assertSame($result, $unit->run(static fn (): stdClass => $result));
    }

    public function testTreeCacheVersionsAreBumpedAgainAfterTheCommit(): void
    {
        $invalidator = new MenuTreeCacheInvalidator(new ArrayAdapter());
        $manager     = $this->manager($this->connection(), $this->unitOfWork());

        (new DashboardMenuTransaction($manager, cacheInvalidator: $invalidator))->run(static function () use ($invalidator): void {
            $invalidator->invalidateForMenuCode('sidebar');
        });

        self::assertSame(2, $invalidator->getVersionForMenuCode('sidebar'));
        // Nothing pending any more: a second pass bumps nothing.
        $invalidator->invalidatePendingAfterCommit();
        self::assertSame(2, $invalidator->getVersionForMenuCode('sidebar'));
    }

    public function testInsideAnOpenOuterTransactionTheAfterCommitBumpWaits(): void
    {
        $invalidator = new MenuTreeCacheInvalidator(new ArrayAdapter());
        $connection  = $this->connection(true);
        $manager     = $this->manager($connection, $this->unitOfWork());

        (new DashboardMenuTransaction($manager, cacheInvalidator: $invalidator))->run(static function () use ($invalidator): void {
            $invalidator->invalidateForMenuCode('sidebar');
        });

        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));
        $invalidator->reset();
        $invalidator->invalidatePendingAfterCommit();
        self::assertSame(1, $invalidator->getVersionForMenuCode('sidebar'));
    }

    /**
     * @return Connection&MockObject
     */
    private function connection(bool $outerTransaction = false): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')->willReturn($outerTransaction);

        return $connection;
    }

    /**
     * @return EntityManagerInterface&MockObject
     */
    private function manager(Connection $connection, UnitOfWork $unitOfWork): EntityManagerInterface
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $manager->method('getClassMetadata')->willReturnMap([
            [Menu::class, new ClassMetadata(Menu::class)],
            [MenuItem::class, new ClassMetadata(MenuItem::class)],
        ]);

        return $manager;
    }

    /**
     * @param list<object> $insertions
     * @param list<object> $updates
     * @param list<object> $deletions
     * @param list<PersistentCollection<int, object>> $collections
     *
     * @return MockObject&UnitOfWork
     */
    private function unitOfWork(array $insertions = [], array $updates = [], array $deletions = [], array $collections = []): UnitOfWork
    {
        $unitOfWork = $this->createMock(UnitOfWork::class);
        if ($insertions !== []) {
            $unitOfWork->method('getScheduledEntityInsertions')->willReturn($insertions);
        }
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn($updates);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn($deletions);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn($collections);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

        return $unitOfWork;
    }

    /**
     * @return PersistentCollection<int, object>
     */
    private function collection(object $owner): PersistentCollection
    {
        // PersistentCollection is final: a real one, with only its owner set.
        $collection = (new ReflectionClass(PersistentCollection::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(PersistentCollection::class, 'owner'))->setValue($collection, $owner);

        return $collection;
    }
}
