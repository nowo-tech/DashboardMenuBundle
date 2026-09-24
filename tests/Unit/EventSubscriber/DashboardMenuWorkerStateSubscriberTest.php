<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardMenuWorkerStateSubscriber;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use WeakMap;

use function count;

final class DashboardMenuWorkerStateSubscriberTest extends TestCase
{
    public function testSubscribesToMainRequestBeforeRoutingAndSecurity(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 4096]],
            DashboardMenuWorkerStateSubscriber::getSubscribedEvents(),
        );
    }

    public function testSecondRequestSeesMenusAndCacheVersionsWrittenByAnotherWorkerWithoutReset(): void
    {
        $pool           = new ArrayAdapter();
        $thisWorker     = new MenuTreeCacheInvalidator($pool);
        $otherWorker    = new MenuTreeCacheInvalidator($pool);
        $createdLater   = (new Menu())->setCode('sidebar');
        $menuRepository = $this->createMenuRepository([null, $createdLater]);
        $subscriber     = new DashboardMenuWorkerStateSubscriber(
            $menuRepository,
            $thisWorker,
            $this->createUrlResolver(),
        );

        $subscriber->onKernelRequest($this->createEvent());
        self::assertSame(0, $thisWorker->getVersionForMenuCode('sidebar'));
        self::assertNull($menuRepository->findOneByCodeAndContext('sidebar', null));

        $otherWorker->invalidateForMenuCode('sidebar');

        self::assertSame(0, $thisWorker->getVersionForMenuCode('sidebar'), 'Memo is request-scoped, not call-scoped.');
        self::assertNull($menuRepository->findOneByCodeAndContext('sidebar', null));

        $subscriber->onKernelRequest($this->createEvent());

        self::assertSame(1, $thisWorker->getVersionForMenuCode('sidebar'));
        self::assertSame($createdLater, $menuRepository->findOneByCodeAndContext('sidebar', null));
    }

    public function testSubRequestsKeepTheMainRequestMemos(): void
    {
        $menuRepository = $this->createMock(MenuRepository::class);
        $menuRepository->expects(self::never())->method('reset');
        $invalidator = $this->createMock(MenuTreeCacheInvalidator::class);
        $invalidator->expects(self::never())->method('reset');
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        $urlResolver = $this->createUrlResolver();
        $hrefMemo    = new ReflectionProperty(MenuUrlResolver::class, 'hrefMemo');
        $before      = $hrefMemo->getValue($urlResolver);

        (new DashboardMenuWorkerStateSubscriber($menuRepository, $invalidator, $urlResolver, $registry))
            ->onKernelRequest($this->createEvent(HttpKernelInterface::SUB_REQUEST));

        self::assertSame($before, $hrefMemo->getValue($urlResolver));
    }

    public function testMainRequestResetsHrefMemoAndDevQueryCounter(): void
    {
        $requestStack = new RequestStack();
        $request      = Request::create('/back-office/a/home');
        $request->attributes->set('_route_params', ['partnerMachineName' => 'a']);
        $requestStack->push($request);

        $urlResolver = $this->createUrlResolver($requestStack);
        $item        = new MenuItem();
        $idRef       = new ReflectionProperty(MenuItem::class, 'id');
        $idRef->setValue($item, 42);
        $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $item->setExternalUrl('https://example.com/a');
        $urlResolver->getHref($item);

        $hrefMemo = new ReflectionProperty(MenuUrlResolver::class, 'hrefMemo');
        /** @var WeakMap<Request, array<string, string>> $map */
        $map = $hrefMemo->getValue($urlResolver);
        self::assertTrue(isset($map[$request]));

        $queryCounter = new MenuQueryCounter();
        $queryCounter->recordQuery();
        $queryCounter->startSegment();
        $queryCounter->recordQuery();
        self::assertSame(1, $queryCounter->getSegmentCount());

        (new DashboardMenuWorkerStateSubscriber(
            $this->createStub(MenuRepository::class),
            new MenuTreeCacheInvalidator(),
            $urlResolver,
            null,
            null,
            $queryCounter,
        ))->onKernelRequest($this->createEvent());

        /** @var WeakMap<Request, array<string, string>> $mapAfter */
        $mapAfter = $hrefMemo->getValue($urlResolver);
        self::assertFalse(isset($mapAfter[$request]));
        self::assertSame(0, $queryCounter->getSegmentCount());
    }

    public function testDevCollectorStartsEmptyOnEveryMainRequest(): void
    {
        $collector = new DashboardMenuDataCollector();
        $collector->addMenuLoad('sidebar', [], []);
        $collector->collect(new Request(), new Response());
        self::assertNotSame([], $collector->getMenus());

        (new DashboardMenuWorkerStateSubscriber(
            $this->createStub(MenuRepository::class),
            new MenuTreeCacheInvalidator(),
            $this->createUrlResolver(),
            null,
            $collector,
        ))->onKernelRequest($this->createEvent());

        self::assertSame([], $collector->getMenus());
    }

    public function testClosedEntityManagerIsResetByNameOnTheNextMainRequest(): void
    {
        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with(Menu::class)->willReturn($closed);
        $registry->method('getManagerNames')->willReturn([
            'default' => 'doctrine.orm.default_entity_manager',
            'menus'   => 'doctrine.orm.menus_entity_manager',
        ]);
        $registry->method('getManager')->willReturnCallback(
            fn (?string $name): ObjectManager => $name === 'menus' ? $closed : $this->createStub(ObjectManager::class),
        );
        $registry->expects(self::once())->method('resetManager')->with('menus');

        (new DashboardMenuWorkerStateSubscriber(
            $this->createStub(MenuRepository::class),
            new MenuTreeCacheInvalidator(),
            $this->createUrlResolver(),
            $registry,
        ))->onKernelRequest($this->createEvent());
    }

    public function testOpenUnknownOrUnnamedManagersAreLeftUntouched(): void
    {
        $open = $this->createStub(EntityManagerInterface::class);
        $open->method('isOpen')->willReturn(true);
        $closed = $this->createStub(EntityManagerInterface::class);
        $closed->method('isOpen')->willReturn(false);

        foreach ([$open, null, $closed] as $manager) {
            $registry = $this->createMock(ManagerRegistry::class);
            $registry->expects(self::once())->method('getManagerForClass')->willReturn($manager);
            $registry->method('getManagerNames')->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
            $registry->method('getManager')->willReturn($this->createStub(ObjectManager::class));
            $registry->expects(self::never())->method('resetManager');

            (new DashboardMenuWorkerStateSubscriber(
                $this->createStub(MenuRepository::class),
                new MenuTreeCacheInvalidator(),
                $this->createUrlResolver(),
                $registry,
            ))->onKernelRequest($this->createEvent());
        }
    }

    /**
     * @param list<Menu|null> $results
     *
     * @return MenuRepository&MockObject
     */
    private function createMenuRepository(array $results): MenuRepository
    {
        $repository = $this->getMockBuilder(MenuRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();
        $repository->expects(self::exactly(count($results)))
            ->method('findOneBy')
            ->willReturnOnConsecutiveCalls(...$results);

        return $repository;
    }

    private function createUrlResolver(?RequestStack $requestStack = null): MenuUrlResolver
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        return new MenuUrlResolver(
            $this->createStub(UrlGeneratorInterface::class),
            $requestStack ?? new RequestStack(),
            $router,
            $container,
        );
    }

    private function createEvent(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/api/menu/sidebar'), $type);
    }
}
