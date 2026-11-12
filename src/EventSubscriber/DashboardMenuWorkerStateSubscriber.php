<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\Doctrine\DashboardMenuEntityManagerResolver;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Nowo\DashboardMenuBundle\Worker\DashboardMenuOwnEntityManagerSubscriber;
use Nowo\DashboardMenuBundle\Worker\UnitOfWorkBoundaries;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Starts every main request with empty menu / cache-version / href memos (and empty dev
 * collectors), even when `services_resetter` does not run between requests (FrankenPHP /
 * RoadRunner workers with kernel not reset). Cache versions are then read once per request
 * from the shared PSR-6 pool, so bumps made by other workers are seen on the next request.
 *
 * 2.21.0: the same memos are also emptied when a Messenger message is received, handled or failed, and when the
 * OUTERMOST console command starts and terminates (judged by {@see UnitOfWorkBoundaries}), so a long-running consumer
 * or command never reuses the menus / cache versions of the previous message or command.
 *
 * On a main request it also resets the menu entity manager when a previous request closed it after a failed flush.
 * It never clears an open entity manager: the bundle's OWN manager (`doctrine.entity_manager`, not the host default)
 * is cleared by {@see DashboardMenuOwnEntityManagerSubscriber}; detaching
 * application entities stays the host's job.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DashboardMenuWorkerStateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MenuRepository $menuRepository,
        private MenuTreeCacheInvalidator $cacheInvalidator,
        private MenuUrlResolver $menuUrlResolver,
        private ?ManagerRegistry $managerRegistry = null,
        private ?DashboardMenuDataCollector $dataCollector = null,
        private ?MenuQueryCounter $menuQueryCounter = null,
        private ?string $entityManagerName = null,
        private ?UnitOfWorkBoundaries $boundaries = null,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                                   => ['onKernelRequest', 4096],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED => ['resetMemos', 4096],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED  => ['resetMemos', -1024],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_FAILED   => ['resetMemos', -1024],
            UnitOfWorkBoundaries::CONSOLE_COMMAND                   => ['onConsoleBoundary', 4096],
            UnitOfWorkBoundaries::CONSOLE_TERMINATE                 => ['onConsoleBoundary', -1024],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->resetMemos();
        $this->recoverClosedEntityManager();
    }

    /**
     * Start or end of a console command: empties the memos only for the outermost command (a command run in-process
     * from another command, a message handler or a controller keeps the outer unit's memos).
     */
    public function onConsoleBoundary(object $event): void
    {
        if (!$this->boundaries instanceof UnitOfWorkBoundaries || $this->boundaries->isOutermost($event)) {
            $this->resetMemos();
        }
    }

    /**
     * Empties the menu / cache-version / href memos and the dev collectors (no entity manager is touched).
     */
    public function resetMemos(): void
    {
        $this->menuRepository->reset();
        $this->cacheInvalidator->reset();
        $this->menuUrlResolver->reset();
        $this->dataCollector?->reset();
        $this->menuQueryCounter?->reset();
    }

    private function recoverClosedEntityManager(): void
    {
        if (!$this->managerRegistry instanceof ManagerRegistry) {
            return;
        }

        // 2.18: the menu manager only (doctrine.entity_manager, else the one that maps Menu).
        (new DashboardMenuEntityManagerResolver($this->managerRegistry, $this->entityManagerName))->resetIfClosed();
    }
}
