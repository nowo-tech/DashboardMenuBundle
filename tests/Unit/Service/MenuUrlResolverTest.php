<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MenuUrlResolverTest extends TestCase
{
    public function testGetHrefReturnsExternalUrlWhenLinkTypeIsExternal(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $item->setExternalUrl('https://example.com/page');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = new RequestStack();

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack);

        self::assertSame('https://example.com/page', $resolver->getHref($item));
    }

    public function testGetHrefReturnsHashWhenRouteNameIsNull(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName(null);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = new RequestStack();

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack);

        self::assertSame('#', $resolver->getHref($item));
    }

    public function testGetHrefReturnsHashWhenRouteNameIsEmpty(): void
    {
        $item = new MenuItem();
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = new RequestStack();

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack);

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

        $requestStack = new RequestStack();

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack);

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

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack);

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

        $resolver = new MenuUrlResolver($urlGenerator, $requestStack);

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

        $resolver = new MenuUrlResolver($urlGenerator, new RequestStack());

        self::assertSame('#', $resolver->getHref($item));
    }
}
