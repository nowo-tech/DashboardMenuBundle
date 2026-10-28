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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function array_fill;
use function array_fill_keys;
use function array_map;
use function is_callable;
use function json_encode;
use function range;

use const JSON_THROW_ON_ERROR;

final class MenuApiControllerTest extends TestCase
{
    public function testInvokeReturnsJsonResponseWithTree(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', ['_locale' => 'es']);

        $item   = $this->createItem('Home', 'app_home', 'link', null);
        $loader = $this->createLoaderWithTree([$item]);

        $urlResolver = $this->createMenuUrlResolver('/');

        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['es', 'en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true);
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
        $urlResolver  = $this->createMenuUrlResolver('/');
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['fr', 'en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true);
        self::assertSame([], $data);
    }

    public function testInvokePassesContextSetsWhenValidJson(): void
    {
        $request = Request::create('/api/menu/nav', 'GET', [
            '_context_sets' => '[{"project":"p1"},{"project":"p2"}]',
        ]);

        $loader       = $this->createLoaderWithTree([]);
        $urlResolver  = $this->createMenuUrlResolver('/');
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
        $urlResolver  = $this->createMenuUrlResolver('/');
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
        $urlResolver  = $this->createMenuUrlResolver('/');
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
        $urlResolver  = $this->createMenuUrlResolver('/');
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function oversizedContextSets(): iterable
    {
        yield 'too many sets' => [json_encode(array_fill(0, MenuApiController::MAX_CONTEXT_SETS + 1, ['p' => 'x']), JSON_THROW_ON_ERROR)];
        yield 'too many keys' => [json_encode([array_fill_keys(array_map(static fn (int $i): string => 'k' . $i, range(0, MenuApiController::MAX_CONTEXT_KEYS)), 1)], JSON_THROW_ON_ERROR)];
        yield 'non-scalar value' => ['[{"a":{"nested":1}}]'];
        yield 'list instead of map' => ['[["a","b"]]'];
    }

    #[DataProvider('oversizedContextSets')]
    public function testInvokeRejectsUnboundedContextSets(string $sets): void
    {
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $controller = new MenuApiController($this->createLoaderWithTree([]), $this->createMenuUrlResolver('/'), $codeResolver, new MenuLocaleResolver(['en'], 'en'));

        $response = ($controller)(Request::create('/api/menu/nav', 'GET', ['_context_sets' => $sets]), 'nav');

        self::assertSame(400, $response->getStatusCode());
    }

    public function testInvokeAcceptsSetsAtTheLimits(): void
    {
        $sets         = array_fill(0, MenuApiController::MAX_CONTEXT_SETS, array_fill_keys(array_map(static fn (int $i): string => 'k' . $i, range(1, MenuApiController::MAX_CONTEXT_KEYS)), true));
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $controller = new MenuApiController($this->createLoaderWithTree([]), $this->createMenuUrlResolver('/'), $codeResolver, new MenuLocaleResolver(['en'], 'en'));

        $response = ($controller)(Request::create('/api/menu/nav', 'GET', ['_context_sets' => json_encode($sets, JSON_THROW_ON_ERROR)]), 'nav');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testInvokeReturnsNestedChildren(): void
    {
        $request = Request::create('/api/menu/nav');

        $root  = $this->createItemWithId('Root', 'home', 'link', null, 1);
        $child = $this->createItemWithId('Child', 'child_route', 'link', 'icon', 2);
        $child->setParent($root);
        $loader = $this->createLoaderWithTree([$root, $child]);

        $urlResolver  = $this->createMenuUrlResolver(static fn (string $r): string => $r === 'home' ? '/' : '/child');
        $codeResolver = $this->createStub(MenuCodeResolverInterface::class);
        $codeResolver->method('resolveMenuCode')->willReturn('nav');
        $localeResolver = new MenuLocaleResolver(['en'], 'en');

        $controller = new MenuApiController($loader, $urlResolver, $codeResolver, $localeResolver);
        $response   = ($controller)($request, 'nav');

        $content = $response->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true);
        self::assertCount(1, $data);
        self::assertCount(1, $data[0]['children']);
        self::assertSame('Child', $data[0]['children'][0]['label']);
        self::assertSame('link', $data[0]['children'][0]['itemType']);
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
            $container,
            [],
            null,
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

    private function createMenuUrlResolver(string|callable $generateReturn = '/'): MenuUrlResolver
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(is_callable($generateReturn) ? $generateReturn : static fn (): string => $generateReturn);
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        $container = $this->createStub(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturn(false);

        return new MenuUrlResolver($urlGenerator, $requestStack, $router, $container);
    }
}
