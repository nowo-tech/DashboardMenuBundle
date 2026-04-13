<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class MenuUrlResolverTest extends TestCase
{
    public function testGetHrefReturnsExternalUrlWhenLinkTypeIsExternal(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $item->setExternalUrl('https://example.com/page');

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('https://example.com/page', $resolver->getHref($item));
    }

    public function testGetHrefReturnsHashWhenRouteNameIsNull(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName(null);

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefReturnsHashWhenLinkTypeIsNull(): void
    {
        $item = new MenuItem();
        $item->setLinkType(null);

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefReturnsHashWhenRouteNameIsEmpty(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('');

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefGeneratesRouteUrlWithoutRequest(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_home');
        $item->setRouteParams(['page' => 'dashboard']);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_home', ['page' => 'dashboard'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/en/?page=dashboard');

        $resolver = $this->createResolver($urlGenerator, new RequestStack());

        self::assertSame('/en/?page=dashboard', $resolver->getHref($item));
    }

    public function testGetHrefInjectsLocaleWhenRequestHasLocaleAndParamsDoNot(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_page');
        $item->setRouteParams(['section' => 'info']);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_page', ['_locale' => 'es', 'section' => 'info'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/es/info');

        $route           = new \Symfony\Component\Routing\Route('/{_locale}/info');
        $routeCollection = new RouteCollection();
        $routeCollection->add('app_page', $route);

        $request = Request::create('/es/');
        $request->setLocale('es');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack, $router, $this->createEmptyTestContainer());

        self::assertSame('/es/info', $resolver->getHref($item));
    }

    public function testGetHrefDoesNotOverwriteExistingLocaleInParams(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_page');
        $item->setRouteParams(['_locale' => 'fr', 'section' => 'info']);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->with('app_page', ['_locale' => 'fr', 'section' => 'info'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/fr/info');

        $request = Request::create('/es/');
        $request->setLocale('es');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $resolver = $this->createResolver($urlGenerator, $requestStack);

        self::assertSame('/fr/info', $resolver->getHref($item));
    }

    public function testGetHrefReturnsHashWhenGenerateThrowsException(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('invalid_route');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willThrowException(new \Symfony\Component\Routing\Exception\RouteNotFoundException('Route does not exist'));

        $resolver = $this->createResolver($urlGenerator, new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefAddsFlashMessageWhenGenerateThrowsAndRequestHasSession(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('invalid_route');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willThrowException(new \Symfony\Component\Routing\Exception\RouteNotFoundException('Route does not exist'));

        $request = Request::create('/en/');
        $request->setLocale('en');
        $session = new Session();
        $session->getFlashBag()->clear();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $resolver = $this->createResolver($urlGenerator, $requestStack);
        self::assertSame('#', $resolver->getHref($item));

        $flashes = $session->getFlashBag()->peek('error');
        self::assertNotEmpty($flashes);
        $found = false;
        foreach ($flashes as $msg) {
            if (str_contains((string) $msg, 'Menu URL:') && str_contains((string) $msg, 'Route does not exist')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected a flash message starting with "Menu URL:"');
    }

    public function testGetHrefCompletesMissingPathParamsFromCurrentRequest(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_show');
        $item->setRouteParams(['tab' => 'info']);

        $route           = new \Symfony\Component\Routing\Route('/show/{id}/{tab}');
        $routeCollection = new RouteCollection();
        $routeCollection->add('app_show', $route);

        $capturedParams = null;
        $urlGenerator   = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::once())
            ->method('generate')
            ->willReturnCallback(static function (string $name, array $params, int $referenceType = 0) use (&$capturedParams): string {
                $capturedParams = $params;

                return '/show/42/info';
            });

        $request = Request::create('/show/42/info');
        $request->setLocale('en');
        $request->attributes->set('_route_params', ['id' => 42, 'tab' => 'info']);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack, $router, $this->createEmptyTestContainer());
        $href     = $resolver->getHref($item);

        self::assertSame('/show/42/info', $href);
        self::assertNotNull($capturedParams);
        self::assertArrayHasKey('id', $capturedParams);
        self::assertSame(42, $capturedParams['id']);
        self::assertArrayHasKey('tab', $capturedParams);
        self::assertSame('info', $capturedParams['tab']);
        self::assertArrayNotHasKey('_locale', $capturedParams);
    }

    public function testGetHrefServiceReturnsHashWhenResolverReturnsChildList(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): array
            {
                return [
                    ['label' => 'Child', 'href' => '/child', 'position' => 0],
                ];
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.menu_dynamic');
        $container->method('get')->with('app.menu_dynamic')->willReturn($resolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.menu_dynamic');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('#', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefAddsFlashMessageWhenRouterLookupThrows(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_home');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/ok');

        $request = Request::create('/en/');
        $request->setLocale('en');
        $session = new Session();
        $session->getFlashBag()->clear();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willThrowException(new RuntimeException('boom'));

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack, $router, $this->createEmptyTestContainer());
        self::assertSame('/ok', $resolver->getHref($item));

        $flashes = $session->getFlashBag()->peek('error');
        self::assertNotEmpty($flashes);
        self::assertStringContainsString('boom', (string) $flashes[0]);
    }

    public function testGetHrefServiceReturnsValidStringHref(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                return '/dashboard/profile';
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.menu_profile');
        $container->method('get')->with('app.menu_profile')->willReturn($resolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.menu_profile');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('/dashboard/profile', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenLinkResolverIsNull(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver(null);

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenContainerDoesNotHaveService(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.nonexistent_resolver');

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenContainerGetThrows(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willThrowException(new RuntimeException('Service error'));

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.broken_resolver');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('#', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenResolvedObjectDoesNotImplementInterface(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new stdClass());

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.bad_resolver');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('#', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenResolvedIsEmptyString(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                return '';
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($resolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.empty_resolver');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('#', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenResolvedIsHash(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                return '#';
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($resolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.hash_resolver');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('#', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefServiceAddsAbsoluteSchemeWhenReferenceTypeIsAbsoluteUrl(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                return '/my/path';
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($resolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.path_resolver');

        $request      = Request::create('https://example.com/base/');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, $requestStack, $router, $container);

        $href = $menuUrlResolver->getHref($item, UrlGeneratorInterface::ABSOLUTE_URL);

        self::assertSame('https://example.com/my/path', $href);
    }

    public function testGetHrefAbsoluteUrlDoesNotModifyAlreadyAbsoluteHref(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('app_home');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://already.absolute/path');

        $resolver = $this->createResolver($urlGenerator, new RequestStack());

        self::assertSame('https://already.absolute/path', $resolver->getHref($item, UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function testNormalizeMenuLinkResolverServiceIdMatchesByLabel(): void
    {
        $linkResolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                return '/resolved-by-label';
            }
        };

        // Service is registered under its real ID but the item stores the human label
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'App\\Service\\MyResolver');
        $container->method('get')->with('App\\Service\\MyResolver')->willReturn($linkResolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        // Item stores the label (human-readable), not the service ID
        $item->setLinkResolver('My Resolver Label');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver(
            $urlGenerator,
            new RequestStack(),
            $router,
            $container,
            ['App\\Service\\MyResolver' => 'My Resolver Label'],
        );

        self::assertSame('/resolved-by-label', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefServiceReturnsHashWhenResolverThrowsException(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string|array
            {
                throw new RuntimeException('Resolver failed');
            }
        };

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($resolver);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.failing_resolver');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $router       = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $menuUrlResolver = new MenuUrlResolver($urlGenerator, new RequestStack(), $router, $container);

        self::assertSame('#', $menuUrlResolver->getHref($item));
    }

    public function testGetHrefUsesRuntimeHrefWhenSet(): void
    {
        $item = new MenuItem();
        $item->setRuntimeHref('/runtime/href');

        $resolver = $this->createResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack());

        self::assertSame('/runtime/href', $resolver->getHref($item));
    }

    private function createResolver(UrlGeneratorInterface $urlGenerator, RequestStack $requestStack): MenuUrlResolver
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        return new MenuUrlResolver($urlGenerator, $requestStack, $router, $this->createEmptyTestContainer());
    }

    private function createEmptyTestContainer(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        return $container;
    }
}
