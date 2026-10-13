<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\EventSubscriber;

use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Enforces DashboardMenuAccessCheckerInterface on dashboard admin routes (REQ-UI-002).
 * When the dashboard is disabled, its routes answer 404 (they may still be imported by the host).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DashboardAccessSubscriber implements EventSubscriberInterface
{
    private const DASHBOARD_ROUTE_PREFIX = 'nowo_dashboard_menu_dashboard_';

    public function __construct(
        private DashboardMenuAccessCheckerInterface $accessChecker,
        private bool $dashboardEnabled = true,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if ($route === null || !str_starts_with((string) $route, self::DASHBOARD_ROUTE_PREFIX)) {
            return;
        }

        if (!$this->dashboardEnabled) {
            throw new NotFoundHttpException('Dashboard Menu admin is disabled.');
        }

        if (!$this->accessChecker->canAccess()) {
            throw new AccessDeniedException('Dashboard Menu admin requires an authorized user.');
        }
    }
}
