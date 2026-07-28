<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\EventSubscriber;

use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
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

    public function testIgnoresNonDashboardRoutes(): void
    {
        $checker = $this->createMock(DashboardMenuAccessCheckerInterface::class);
        $checker->expects(self::never())->method('canAccess');

        $subscriber = new DashboardAccessSubscriber($checker);
        $request    = Request::create('/');
        $request->attributes->set('_route', 'some_other_route');
        $subscriber->onKernelController($this->createControllerEvent($request));
    }

    public function testDeniesWhenCheckerFails(): void
    {
        $checker = $this->createMock(DashboardMenuAccessCheckerInterface::class);
        $checker->method('canAccess')->willReturn(false);

        $subscriber = new DashboardAccessSubscriber($checker);
        $request    = Request::create('/');
        $request->attributes->set('_route', 'nowo_dashboard_menu_dashboard_home');

        $this->expectException(AccessDeniedException::class);
        $subscriber->onKernelController($this->createControllerEvent($request));
    }

    public function testAllowsWhenCheckerPasses(): void
    {
        $checker = $this->createMock(DashboardMenuAccessCheckerInterface::class);
        $checker->expects(self::once())->method('canAccess')->willReturn(true);

        $subscriber = new DashboardAccessSubscriber($checker);
        $request    = Request::create('/');
        $request->attributes->set('_route', 'nowo_dashboard_menu_dashboard_index');
        $subscriber->onKernelController($this->createControllerEvent($request));
    }
}
