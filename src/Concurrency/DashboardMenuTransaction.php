<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Concurrency;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\DashboardMenuBundle\Doctrine\DashboardMenuEntityManagerResolver;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\DoctrineTransactionRunner;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * One write unit of the menu entities (2.23, REQ-RUNTIME-003 rule 1): every dashboard action, Live save and import
 * runs its writes here, in ONE transaction on the manager of the menu entities, flushed before the commit.
 *
 * - The bundle's own manager (`doctrine.entity_manager` set and not the host's default): transaction-doctrine's
 *   {@see DoctrineTransactionRunner::forEntityManager()}; after a failure that manager is closed and reset (it maps the
 *   menu entities only).
 * - The host's manager (the default case): the manager is never cleared, closed or reset while it is open (rule 2).
 *   The dashboard forms bind into managed {@see Menu} / {@see MenuItem} entities before the unit starts, so pending
 *   changes on those two classes are the bundle's own work and are flushed by the unit. Pending changes on any OTHER
 *   entity belong to the host: the unit refuses to start with a LogicException (the same rule as
 *   {@see DoctrineTransactionRunner::forHostEntityManager()}), since its flush would carry them into a transaction
 *   that may roll back. The unit runs on transaction-doctrine's connection runner (levels rolled back to the entry
 *   level, nested units on savepoints, a lost savepoint never taken as success); after a failure a closed manager is
 *   replaced through the registry, and in an open one only the menu entities are detached, so nothing the rollback
 *   undid can be flushed later by the host. The host's own entities are not touched.
 *
 * Side effects after the commit (rule 1): the menu tree cache versions bumped by the flush (inside the transaction, so
 * a concurrent reader may have cached the old tree under the new version) are bumped again once the outermost unit has
 * ended ({@see MenuTreeCacheInvalidator::invalidatePendingAfterCommit()}).
 *
 * Deadlocks, lock wait timeouts and serialization failures leave as {@see TransientStorageFailure} (the storage error
 * as previous, no Retry-After hint: the configured conflict_response decides); anything else is rethrown unchanged. No retry here: the answer is the 409 of
 * {@see DashboardMenuConflictAnswer}.
 *
 * Stateless (FrankenPHP worker mode, kernel reset off): the manager is looked up on every call.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DashboardMenuTransaction
{
    public const HOST_PENDING_MESSAGE = 'The entity manager of the menu entities has pending changes on other entities (the host\'s): flush or clear them before a dashboard menu write; the menu transaction would otherwise carry them into a transaction that may roll back.';

    /**
     * @param DashboardMenuEntityManagerResolver|EntityManagerInterface $manager the resolver of the menu manager, or
     *                                                                           the manager itself (direct construction: a closed manager is then left to the host)
     * @param ManagerRegistry|null $registry the Doctrine registry (reset of the bundle's own manager)
     * @param string|null $managerName `doctrine.entity_manager` (null: the manager that maps the menu entities)
     * @param bool $ownManager whether that manager is the bundle's own (configured and not the host's default)
     * @param MenuTreeCacheInvalidator|null $cacheInvalidator re-bumps the tree cache versions after the commit
     * @param MenuRepository|null $menuRepository its request memo is cleared after a failed unit
     * @param LoggerInterface|null $logger structured failure / repair notices of transaction-doctrine (no SQL)
     */
    public function __construct(
        private DashboardMenuEntityManagerResolver|EntityManagerInterface $manager,
        private ?ManagerRegistry $registry = null,
        private ?string $managerName = null,
        private bool $ownManager = false,
        private ?MenuTreeCacheInvalidator $cacheInvalidator = null,
        private ?MenuRepository $menuRepository = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Runs $operation in one transaction and flushes the manager before the commit.
     *
     * @template T
     *
     * @param callable(EntityManagerInterface): T $operation receives the manager of the menu entities
     *
     * @throws TransientStorageFailure on a deadlock, lock wait timeout or serialization failure (nothing committed)
     * @throws LogicException when the host's manager has pending changes on entities that are not menu entities
     * @throws DBALException any other storage error, unchanged (a unique violation, a lost connection, ...)
     *
     * @return T
     */
    public function run(callable $operation): mixed
    {
        $manager = $this->manager instanceof EntityManagerInterface ? $this->manager : $this->manager->entityManager();

        try {
            return $this->ownManager ? $this->runOnOwnManager($manager, $operation) : $this->runOnHostManager($manager, $operation);
        } catch (Throwable $failure) {
            // The failure detached, cleared or reset the menu entities: the request's code lookup memo must not serve
            // them to a retry in the same request.
            $this->menuRepository?->reset();

            throw $failure;
        } finally {
            if (!$manager->getConnection()->isTransactionActive()) {
                $this->cacheInvalidator?->invalidatePendingAfterCommit();
            }
        }
    }

    /**
     * @template T
     *
     * @param callable(EntityManagerInterface): T $operation
     *
     * @return T
     */
    private function runOnOwnManager(EntityManagerInterface $manager, callable $operation): mixed
    {
        /** @var T $result */
        $result = DoctrineTransactionRunner::forEntityManager($manager, $this->registry, $this->managerName, null, $this->logger)
            ->run(static fn (): mixed => $operation($manager));

        return $result;
    }

    /**
     * @template T
     *
     * @param callable(EntityManagerInterface): T $operation
     *
     * @return T
     */
    private function runOnHostManager(EntityManagerInterface $manager, callable $operation): mixed
    {
        $this->assertNoHostPendingChanges($manager);

        try {
            /** @var T $result */
            $result = DoctrineTransactionRunner::forConnection($manager->getConnection(), null, $this->logger)
                ->run(static function () use ($operation, $manager): mixed {
                    $result = $operation($manager);
                    $manager->flush();

                    return $result;
                });

            return $result;
        } catch (Throwable $failure) {
            $this->releaseHostManager($manager);

            throw $failure;
        }
    }

    /**
     * Pending changes on entities that are not menu entities belong to the host: refuse to carry them.
     */
    private function assertNoHostPendingChanges(EntityManagerInterface $manager): void
    {
        $unitOfWork = $manager->getUnitOfWork();
        $unitOfWork->computeChangeSets();
        $pending = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];
        foreach ($pending as $entity) {
            if (!$this->isMenuEntity($entity)) {
                throw new LogicException(self::HOST_PENDING_MESSAGE);
            }
        }

        foreach ([...$unitOfWork->getScheduledCollectionUpdates(), ...$unitOfWork->getScheduledCollectionDeletions()] as $collection) {
            if (!$this->isMenuEntity($collection->getOwner())) {
                throw new LogicException(self::HOST_PENDING_MESSAGE);
            }
        }
    }

    /**
     * After a failed unit on the host's manager: a closed manager is replaced (rule 2 allows only that), an open one
     * forgets the menu entities only (managed or scheduled for insertion), never the host's.
     */
    private function releaseHostManager(EntityManagerInterface $manager): void
    {
        if (!$manager->isOpen()) {
            if ($this->manager instanceof DashboardMenuEntityManagerResolver) {
                $this->manager->resetIfClosed();
            }

            return;
        }

        $unitOfWork = $manager->getUnitOfWork();
        $entities   = $unitOfWork->getScheduledEntityInsertions();
        foreach ([Menu::class, MenuItem::class] as $class) {
            foreach ($unitOfWork->getIdentityMap()[$manager->getClassMetadata($class)->rootEntityName] ?? [] as $entity) {
                $entities[] = $entity;
            }
        }

        foreach ($entities as $entity) {
            if ($this->isMenuEntity($entity) && $manager->contains($entity)) {
                $manager->detach($entity);
            }
        }
    }

    private function isMenuEntity(?object $entity): bool
    {
        return $entity instanceof Menu || $entity instanceof MenuItem;
    }
}
