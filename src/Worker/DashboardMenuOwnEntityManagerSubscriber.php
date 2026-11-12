<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Worker;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the bundle's OWN entity manager (`doctrine.entity_manager`) usable in a kept-alive kernel (2.21.0,
 * REQ-RUNTIME-002 rule 4; FrankenPHP worker mode with kernel reset false, no services resetter).
 *
 * Registered only when `doctrine.entity_manager` is set and is not the host's default manager: that manager maps the
 * menu entities only, so the bundle recreates it when a failed flush closed it and clears it at every OUTERMOST
 * unit-of-work boundary, so no managed `Menu` / `MenuItem` survives into the next request, message or command. The
 * host's managers are never reset nor cleared (rule 7: the host clears its own managers); without the option nothing
 * changes.
 *
 * Boundaries, judged by {@see UnitOfWorkBoundaries} (same policy as PageBuilderKitBundle 1.15.1):
 * - HTTP: the main `kernel.request` (priority 4096) and `kernel.terminate` (priority -1024), only outside a console
 *   command and a Messenger message; sub-requests share their main request's unit of work;
 * - Messenger: when a message is received (priority 4096) and after it is handled or failed (priority -1024);
 * - console: when the OUTERMOST command starts and terminates. A command run in-process from another command, a
 *   message handler or a controller does not clear the outer unit's manager.
 *
 * The manager arrives as an iterator of an IGNORE_ON_UNINITIALIZED_REFERENCE reference, so a boundary only touches a
 * manager the unit of work already built and never builds one itself.
 *
 * @internal registered by the bundle; the constructor is not a public API
 */
final readonly class DashboardMenuOwnEntityManagerSubscriber implements EventSubscriberInterface
{
    /**
     * @param iterable<EntityManagerInterface> $entityManagers the bundle-owned manager (skipped while uninitialized)
     * @param ManagerRegistry $registry the Doctrine registry, to recreate a closed manager
     * @param string $managerName `doctrine.entity_manager`
     */
    public function __construct(
        private UnitOfWorkBoundaries $boundaries,
        private iterable $entityManagers,
        private ManagerRegistry $registry,
        private string $managerName,
    ) {
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST                                   => ['onRequest', 4096],
            KernelEvents::TERMINATE                                 => ['onTerminate', -1024],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_RECEIVED => ['clearState', 4096],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_HANDLED  => ['clearState', -1024],
            UnitOfWorkBoundaries::MESSENGER_WORKER_MESSAGE_FAILED   => ['clearState', -1024],
            UnitOfWorkBoundaries::CONSOLE_COMMAND                   => ['onConsoleBoundary', 4096],
            UnitOfWorkBoundaries::CONSOLE_TERMINATE                 => ['onConsoleBoundary', -1024],
        ];
    }

    /**
     * Start of a main request: recovers / clears only outside a command and a message.
     */
    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->boundaries->isHttpOutermost()) {
            $this->clearState();
        }
    }

    /**
     * End of a request: clears only outside a command and a message (an in-process HttpKernel::handle()).
     */
    public function onTerminate(): void
    {
        if ($this->boundaries->isHttpOutermost()) {
            $this->clearState();
        }
    }

    /**
     * Start or end of a command: clears only for the outermost command (outside a message and a main request).
     */
    public function onConsoleBoundary(object $event): void
    {
        if ($this->boundaries->isOutermost($event)) {
            $this->clearState();
        }
    }

    /**
     * Recreates the bundle's manager when a failed write closed it, clears it when it is open (unflushed changes of
     * the finished unit are discarded); nothing when it was never built.
     */
    public function clearState(): void
    {
        foreach ($this->entityManagers as $entityManager) {
            if ($entityManager->isOpen()) {
                // @igor-ignore - This IS the unit-of-work boundary reset of the bundle's own manager (no state kept).
                $entityManager->clear();

                continue;
            }

            $this->registry->resetManager($this->managerName);
        }
    }
}
