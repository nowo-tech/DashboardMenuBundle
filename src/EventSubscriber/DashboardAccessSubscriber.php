<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function sprintf;

/**
 * When dashboard.required_role is set, requires that role for all dashboard routes.
 * Requires SecurityBundle and security.authorization_checker. No-op when required_role is null.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final readonly class DashboardAccessSubscriber implements EventSubscriberInterface
{
    private const DASHBOARD_ROUTE_PREFIX = 'nowo_dashboard_menu_dashboard_';

    public function __construct(
        private ?string $requiredRole,
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
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
        if ($this->requiredRole === null || $this->requiredRole === '' || !$this->authorizationChecker instanceof AuthorizationCheckerInterface) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        if ($route === null || !str_starts_with((string) $route, self::DASHBOARD_ROUTE_PREFIX)) {
            return;
        }

        if (!$this->authorizationChecker->isGranted($this->requiredRole)) {
            throw new AccessDeniedException(sprintf('Dashboard requires role "%s".', $this->requiredRole));
        }
    }
}
