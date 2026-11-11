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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Starts every main request with empty menu / cache-version / href memos (and empty dev
 * collectors), even when `services_resetter` does not run between requests (FrankenPHP /
 * RoadRunner workers with kernel not reset). Cache versions are then read once per request
 * from the shared PSR-6 pool, so bumps made by other workers are seen on the next request.
 *
 * It also resets the menu entity manager when a previous request closed it after a failed flush.
 * It never clears an open entity manager: detaching application entities stays the host's job.
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
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->menuRepository->reset();
        $this->cacheInvalidator->reset();
        $this->menuUrlResolver->reset();
        $this->dataCollector?->reset();
        $this->menuQueryCounter?->reset();

        $this->recoverClosedEntityManager();
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
