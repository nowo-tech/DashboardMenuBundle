<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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

        $request = Request::create('/es/');
        $request->setLocale('es');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $resolver = $this->createResolver($urlGenerator, $requestStack);

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

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack, $router);
        $href     = $resolver->getHref($item);

        self::assertSame('/show/42/info', $href);
        self::assertNotNull($capturedParams);
        self::assertArrayHasKey('id', $capturedParams);
        self::assertSame(42, $capturedParams['id']);
        self::assertArrayHasKey('tab', $capturedParams);
        self::assertSame('info', $capturedParams['tab']);
        self::assertArrayHasKey('_locale', $capturedParams);
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

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack, $router);
        self::assertSame('/ok', $resolver->getHref($item));

        $flashes = $session->getFlashBag()->peek('error');
        self::assertNotEmpty($flashes);
        self::assertStringContainsString('boom', (string) $flashes[0]);
    }

    private function createResolver(UrlGeneratorInterface $urlGenerator, RequestStack $requestStack): MenuUrlResolver
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        return new MenuUrlResolver($urlGenerator, $requestStack, $router);
    }
}
