<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\EventSubscriber;

use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DashboardAccessSubscriberTest extends TestCase
{
    private function createControllerEvent(Request $request): ControllerEvent
    {
        $ref  = new ReflectionClass(ControllerEvent::class);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            self::fail('ControllerEvent has no constructor.');
        }

        $kernel = $this->createMock(KernelInterface::class);
        $args   = [];
        foreach ($ctor->getParameters() as $p) {
            $name = $p->getName();

            if ($name === 'kernel') {
                $args[] = $kernel;
                continue;
            }
            if ($name === 'request') {
                $args[] = $request;
                continue;
            }
            if ($name === 'controller') {
                $args[] = static fn (): null => null;
                continue;
            }
            if ($name === 'requestType') {
                $args[] = null;
                continue;
            }

            if ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
                continue;
            }
            if ($p->allowsNull()) {
                $args[] = null;
                continue;
            }

            self::fail('Unable to build ControllerEvent argument for: ' . $name);
        }

        /* @var ControllerEvent */
        return $ref->newInstanceArgs($args);
    }

    public function testGetSubscribedEventsRegistersControllerListener(): void
    {
        $events = DashboardAccessSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        self::assertSame(['onKernelController', 0], $events[KernelEvents::CONTROLLER]);
    }

    public function testOnKernelControllerReturnsWhenRequiredRoleIsNull(): void
    {
        $subscriber = new DashboardAccessSubscriber(requiredRole: null);

        $request = Request::create('/');
        $event   = $this->createControllerEvent($request);

        self::assertNull($request->attributes->get('_route'));

        $subscriber->onKernelController($event);
    }

    public function testOnKernelControllerReturnsWhenRouteDoesNotMatchPrefix(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $subscriber = new DashboardAccessSubscriber(requiredRole: 'ROLE_X', authorizationChecker: $auth);

        $request = Request::create('/');
        $request->attributes->set('_route', 'some_other_route');

        $event = $this->createControllerEvent($request);

        $subscriber->onKernelController($event);
    }

    public function testOnKernelControllerThrowsWhenRouteMatchesAndAccessDenied(): void
    {
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->expects(self::once())
            ->method('isGranted')
            ->with('ROLE_X')
            ->willReturn(false);

        $subscriber = new DashboardAccessSubscriber(requiredRole: 'ROLE_X', authorizationChecker: $auth);

        $request = Request::create('/');
        $request->attributes->set('_route', 'nowo_dashboard_menu_dashboard_home');

        $event = $this->createControllerEvent($request);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Dashboard requires role "ROLE_X".');

        $subscriber->onKernelController($event);
    }
}
