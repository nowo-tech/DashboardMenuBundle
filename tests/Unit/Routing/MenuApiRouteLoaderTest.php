<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Routing;

use Nowo\DashboardMenuBundle\Controller\Api\MenuApiController;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Routing\MenuApiRouteLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

use function dirname;

final class MenuApiRouteLoaderTest extends TestCase
{
    public function testSupportsOnlyItsType(): void
    {
        $loader = new MenuApiRouteLoader(true, '/api/menu');

        self::assertTrue($loader->supports('.', 'nowo_dashboard_menu_api'));
        self::assertFalse($loader->supports('.', 'attribute'));
        self::assertFalse($loader->supports('.'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function prefixes(): iterable
    {
        yield 'default' => ['/api/menu', '/api/menu/{code}'];
        yield 'custom' => ['/internal/navigation', '/internal/navigation/{code}'];
        yield 'trailing slash' => ['/menus/api/', '/menus/api/{code}'];
        yield 'missing leading slash' => ['menus', '/menus/{code}'];
        yield 'root' => ['/', '/{code}'];
    }

    #[DataProvider('prefixes')]
    public function testRouteUsesConfiguredPathPrefix(string $prefix, string $expectedPath): void
    {
        $route = $this->resolvedLoader(true, $prefix)->load('.', 'nowo_dashboard_menu_api')->get('nowo_dashboard_menu_api');

        self::assertNotNull($route);
        self::assertSame($expectedPath, $route->getPath());
        self::assertSame(MenuApiController::class, $route->getDefault('_controller'));
        self::assertSame('[a-zA-Z0-9_-]+', $route->getRequirement('code'));
        self::assertSame(['GET'], $route->getMethods());
    }

    public function testNoRouteWhenApiIsDisabled(): void
    {
        $routes = $this->resolvedLoader(false, '/api/menu')->load('.', 'nowo_dashboard_menu_api');

        self::assertCount(0, $routes);
    }

    public function testRouteIsDeclaredByAttributeOnTheController(): void
    {
        $attributes = (new ReflectionClass(MenuApiController::class))->getAttributes(Route::class);

        self::assertCount(1, $attributes);
        $route = $attributes[0]->newInstance();
        self::assertSame(MenuApiRouteLoader::ROUTE_NAME, $route->name);
        self::assertSame('/{code}', $route->path);
        self::assertSame(['GET'], $route->methods);
    }

    public function testBundleRoutesFileDelegatesToTheLoader(): void
    {
        $routes = $this->loadBundleRoutes(new MenuApiRouteLoader(true, '/v2/menus'));

        self::assertSame(['nowo_dashboard_menu_api'], array_keys($routes->all()));
        self::assertSame('/v2/menus/{code}', $routes->get('nowo_dashboard_menu_api')?->getPath());

        self::assertCount(0, $this->loadBundleRoutes(new MenuApiRouteLoader(false, '/api/menu')));
    }

    public function testExtensionWiresTheLoaderWithApiConfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.environment', 'test');
        (new DashboardMenuExtension())->load([['api' => ['enabled' => false, 'path_prefix' => '/menus/api']]], $container);

        self::assertTrue($container->hasDefinition(MenuApiRouteLoader::class));
        $definition = $container->getDefinition(MenuApiRouteLoader::class);
        self::assertTrue($definition->hasTag('routing.loader'));
        self::assertSame('%nowo_dashboard_menu.api.enabled%', $definition->getArgument('$enabled'));
        self::assertSame('%nowo_dashboard_menu.api.path_prefix%', $definition->getArgument('$pathPrefix'));
        self::assertFalse($container->getParameter('nowo_dashboard_menu.api.enabled'));
        self::assertSame('/menus/api', $container->getParameter('nowo_dashboard_menu.api.path_prefix'));
    }

    private function resolvedLoader(bool $enabled, string $prefix): MenuApiRouteLoader
    {
        $loader = new MenuApiRouteLoader($enabled, $prefix);
        new LoaderResolver([$loader, new AttributeRouteControllerLoader()]);

        return $loader;
    }

    private function loadBundleRoutes(MenuApiRouteLoader $apiLoader): RouteCollection
    {
        $locator  = new FileLocator(dirname(__DIR__, 3) . '/src/Resources/config');
        $resolver = new LoaderResolver([new YamlFileLoader($locator), $apiLoader, new AttributeRouteControllerLoader()]);
        $routes   = (new DelegatingLoader($resolver))->load('routes.yaml');
        self::assertInstanceOf(RouteCollection::class, $routes);

        return $routes;
    }
}
