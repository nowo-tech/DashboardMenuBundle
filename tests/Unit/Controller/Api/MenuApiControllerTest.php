<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Controller\Api;

use Nowo\DashboardMenuBundle\Controller\Api\MenuApiController;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MenuApiControllerTest extends TestCase
{
    public function testInvokeReturnsJsonResponseWithTree(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', ['_locale' => 'es']);

        $item   = $this->createItem('Home', 'app_home', 'link', null);
        $loader = $this->createLoaderWithTree([$item]);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/');
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = new MenuUrlResolver($urlGenerator, $requestStack);

        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['es', 'en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $data = json_decode($response->getContent(), true);
        self::assertIsArray($data);
        self::assertCount(1, $data);
        self::assertSame('Home', $data[0]['label']);
        self::assertSame('/', $data[0]['href']);
        self::assertSame('app_home', $data[0]['routeName']);
        self::assertSame('link', $data[0]['itemType']);
        self::assertSame([], $data[0]['children']);
    }

    public function testInvokeUsesRequestLocaleWhenNoQueryLocale(): void
    {
        $request = Request::create('/api/menu/nav');
        $request->setLocale('fr');

        $loader       = $this->createLoaderWithTree([]);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver  = new MenuUrlResolver($urlGenerator, $requestStack);
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['fr', 'en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        $data = json_decode($response->getContent(), true);
        self::assertSame([], $data);
    }

    public function testInvokePassesContextSetsWhenValidJson(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', [
            '_context_sets' => '[{"project":"p1"},{"project":"p2"}]',
        ]);

        $loader       = $this->createLoaderWithTree([]);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver  = new MenuUrlResolver($urlGenerator, $requestStack);
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testInvokePassesNullContextSetsWhenEmptyString(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', ['_context_sets' => '']);

        $loader       = $this->createLoaderWithTree([]);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver  = new MenuUrlResolver($urlGenerator, $requestStack);
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testInvokePassesNullContextSetsWhenInvalidJson(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', ['_context_sets' => 'not-json']);

        $loader       = $this->createLoaderWithTree([]);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver  = new MenuUrlResolver($urlGenerator, $requestStack);
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testInvokeContextSetsNormalizesNonArrayItemsToNull(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', [
            '_context_sets' => '[{"a":1},"string",null]',
        ]);

        $loader       = $this->createLoaderWithTree([]);
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver  = new MenuUrlResolver($urlGenerator, $requestStack);
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testInvokeReturnsNestedChildren(): void
    {
        $request = Request::create('/api/menu/nav');

        $root  = $this->createItemWithId('Root', 'home', 'link', null, 1);
        $child = $this->createItemWithId('Child', null, 'section', 'icon', 2);
        $child->setParent($root);
        $loader = $this->createLoaderWithTree([$root, $child]);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn(string $route): string => $route === 'home' ? '/' : '/child');
        $requestStack = $this->createStub(\Symfony\Component\HttpFoundation\RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver  = new MenuUrlResolver($urlGenerator, $requestStack);
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        $data = json_decode($response->getContent(), true);
        self::assertCount(1, $data);
        self::assertCount(1, $data[0]['children']);
        self::assertSame('Child', $data[0]['children'][0]['label']);
        self::assertSame('section', $data[0]['children'][0]['itemType']);
        self::assertSame('icon', $data[0]['children'][0]['icon']);
    }

    /**
     * @param list<MenuItem> $items
     */
    private function createLoaderWithTree(array $items): MenuTreeLoader
    {
        $menu = new Menu();
        $menu->setCode('nav');
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn($items);
        $config    = new MenuConfigResolver(['project' => null], $menuRepo);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $iconResolver = new MenuIconNameResolver([]);

        return new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            $config,
            $iconResolver,
            $container,
            new AllowAllMenuPermissionChecker(),
            null,
            60,
        );
    }

    private function createItem(string $label, ?string $routeName, string $itemType, ?string $icon): MenuItem
    {
        return $this->createItemWithId($label, $routeName, $itemType, $icon, 1);
    }

    private function createItemWithId(string $label, ?string $routeName, string $itemType, ?string $icon, int $id): MenuItem
    {
        $item = new MenuItem();
        $item->setLabel($label);
        $item->setRouteName($routeName);
        $item->setItemType($itemType);
        $item->setIcon($icon);
        $item->setPosition(0);
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($item, $id);

        return $item;
    }
}
