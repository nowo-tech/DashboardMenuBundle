<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Twig;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class MenuExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsExpectedTwigFunctions(): void
    {
        $extension = $this->createExtension();
        $functions = $extension->getFunctions();
        self::assertCount(3, $functions);
        $names = array_map(static fn (\Twig\TwigFunction $f): string => $f->getName(), $functions);
        self::assertContains('dashboard_menu_tree', $names);
        self::assertContains('dashboard_menu_href', $names);
        self::assertContains('dashboard_menu_config', $names);
    }

    public function testGetFiltersReturnsDashboardMenuIconNameFilter(): void
    {
        $extension = $this->createExtension();
        $filters   = $extension->getFilters();
        self::assertCount(1, $filters);
        self::assertSame('dashboard_menu_icon_name', $filters[0]->getName());
    }

    public function testGetGlobalsReturnsDashboardLayoutTemplate(): void
    {
        $extension = $this->createExtension();
        $globals   = $extension->getGlobals();
        self::assertArrayHasKey('nowo_dashboard_layout_template', $globals);
        self::assertSame('@NowoDashboardMenuBundle/dashboard/layout.html.twig', $globals['nowo_dashboard_layout_template']);
        self::assertArrayHasKey('nowo_dashboard_ux_autocomplete_available', $globals);
        self::assertFalse($globals['nowo_dashboard_ux_autocomplete_available']);
    }

    public function testGetHrefDelegatesToUrlResolver(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/home');
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver = $this->createMenuUrlResolver($urlGenerator, $requestStack);

        $item = new MenuItem();
        $item->setRouteName('app_home');
        $extension = $this->createExtension(urlResolver: $urlResolver);
        self::assertSame('/home', $extension->getHref($item, 0));
    }

    public function testGetMenuConfigWithoutRequestUsesMenuCode(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $configResolver = new MenuConfigResolver(['project' => null], $menuRepo);
        $requestStack   = new RequestStack();

        $extension = $this->createExtension(configResolver: $configResolver, requestStack: $requestStack);
        $config    = $extension->getMenuConfig('sidebar');
        self::assertSame('dashboard-menu', $config['classes']['menu']);
        self::assertSame('menu-section-label', $config['classes']['section_label']);
        self::assertNull($config['ul_id']);
        self::assertNull($config['depth_limit']);
        self::assertNull($config['menu_name']);
    }

    public function testGetMenuTreeWithoutRequestReturnsTreeWithDefaultLocale(): void
    {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $itemRepo     = $this->createStub(MenuItemRepository::class);
        $config       = new MenuConfigResolver(['project' => null], $menuRepo);
        $container    = $this->createStub(ContainerInterface::class);
        $loader       = $this->createMenuTreeLoader($menuRepo, $itemRepo, $config, $container);
        $requestStack = new RequestStack();

        $extension = $this->createExtension(menuTreeLoader: $loader, requestStack: $requestStack);
        $result    = $extension->getMenuTree('nav');
        self::assertSame([], $result);
    }

    public function testGetMenuConfigWithRequestResolvesCodeAndReturnsConfig(): void
    {
        $request = Request::create('/page');
        $request->setLocale('es');
        $stack = new RequestStack();
        $stack->push($request);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $configResolver = new MenuConfigResolver(['project' => null], $menuRepo);

        $extension = $this->createExtension(configResolver: $configResolver, requestStack: $stack);
        $config    = $extension->getMenuConfig('sidebar', [null, []]);
        self::assertSame('dashboard-menu', $config['classes']['menu']);
        self::assertSame('menu-section-label', $config['classes']['section_label']);
        self::assertNull($config['ul_id']);
        self::assertNull($config['depth_limit']);
    }

    public function testGetMenuTreeWithRequestUsesResolvedCodeAndLocale(): void
    {
        $request = Request::create('/page');
        $request->setLocale('es');
        $stack = new RequestStack();
        $stack->push($request);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $itemRepo  = $this->createStub(MenuItemRepository::class);
        $config    = new MenuConfigResolver(['project' => null], $menuRepo);
        $container = $this->createStub(ContainerInterface::class);
        $loader    = $this->createMenuTreeLoader($menuRepo, $itemRepo, $config, $container);

        $extension = $this->createExtension(menuTreeLoader: $loader, requestStack: $stack);
        $result    = $extension->getMenuTree('nav');
        self::assertSame([], $result);
    }

    public function testGetMenuTreeWithRequestAndDataCollectorCallsAddMenuLoadWhenTreeHasItemWithMenu(): void
    {
        $request = Request::create('/page');
        $stack   = new RequestStack();
        $stack->push($request);

        $menu = new Menu();
        $menu->setCode('sidebar');
        $menu->setContext(['locale' => 'en']);
        $item = new MenuItem();
        $item->setMenu($menu);
        $item->setLabel('Home');
        $item->setPosition(0);
        $ref = new ReflectionProperty(MenuItem::class, 'id');
        $ref->setValue($item, 1);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn($menu);
        $itemRepo = $this->createStub(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([$item]);
        $config    = new MenuConfigResolver(['project' => null], $menuRepo);
        $container = $this->createStub(ContainerInterface::class);
        $loader    = $this->createMenuTreeLoader($menuRepo, $itemRepo, $config, $container);

        $dataCollector = new DashboardMenuDataCollector();
        $extension     = $this->createExtension(menuTreeLoader: $loader, requestStack: $stack, dataCollector: $dataCollector);
        $result        = $extension->getMenuTree('nav');
        $dataCollector->collect($request, new \Symfony\Component\HttpFoundation\Response());

        self::assertCount(1, $result);
        self::assertSame(['locale' => 'en'], $dataCollector->getMenus()[0]['resolved_context'] ?? null);
    }

    public function testGetMenuTreeWithRequestAndDataCollectorAndEmptyTreeDoesNotCallAddMenuLoadWithResolvedContext(): void
    {
        $request = Request::create('/page');
        $stack   = new RequestStack();
        $stack->push($request);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $itemRepo  = $this->createStub(MenuItemRepository::class);
        $config    = new MenuConfigResolver(['project' => null], $menuRepo);
        $container = $this->createStub(ContainerInterface::class);
        $loader    = $this->createMenuTreeLoader($menuRepo, $itemRepo, $config, $container);

        $dataCollector = new DashboardMenuDataCollector();
        $extension     = $this->createExtension(menuTreeLoader: $loader, requestStack: $stack, dataCollector: $dataCollector);
        $extension->getMenuTree('nav');
        $dataCollector->collect($request, new \Symfony\Component\HttpFoundation\Response());

        self::assertCount(1, $dataCollector->getMenus());
        self::assertNull($dataCollector->getMenus()[0]['resolved_context']);
    }

    public function testGetMenuTreeWithDataCollectorAndMenuQueryCounterAndConnectionStartsSegmentAndPassesQueryCount(): void
    {
        $request = Request::create('/page');
        $stack   = new RequestStack();
        $stack->push($request);

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $itemRepo  = $this->createStub(MenuItemRepository::class);
        $config    = new MenuConfigResolver(['project' => null], $menuRepo);
        $container = $this->createStub(ContainerInterface::class);
        $loader    = $this->createMenuTreeLoader($menuRepo, $itemRepo, $config, $container);

        $dataCollector    = new DashboardMenuDataCollector();
        $menuQueryCounter = new MenuQueryCounter();
        $connection       = $this->createStub(\Doctrine\DBAL\Connection::class);
        $connection->method('getConfiguration')->willThrowException(new RuntimeException('no config'));

        $extension = $this->createExtension(
            menuTreeLoader: $loader,
            requestStack: $stack,
            dataCollector: $dataCollector,
            menuQueryCounter: $menuQueryCounter,
            connection: $connection,
        );
        $extension->getMenuTree('nav');
        $dataCollector->collect($request, new \Symfony\Component\HttpFoundation\Response());

        self::assertCount(1, $dataCollector->getMenus());
        self::assertSame(0, $dataCollector->getMenus()[0]['query_count']);
    }

    private function createExtension(
        ?MenuTreeLoader $menuTreeLoader = null,
        ?MenuUrlResolver $urlResolver = null,
        ?MenuConfigResolver $configResolver = null,
        ?RequestStack $requestStack = null,
        ?DashboardMenuDataCollector $dataCollector = null,
        ?MenuQueryCounter $menuQueryCounter = null,
        ?\Doctrine\DBAL\Connection $connection = null,
    ): MenuExtension {
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn(null);
        $itemRepo  = $this->createStub(MenuItemRepository::class);
        $config    = $configResolver ?? new MenuConfigResolver(['project' => null], $menuRepo);
        $container = $this->createStub(ContainerInterface::class);
        $loader    = $menuTreeLoader ?? $this->createMenuTreeLoader($menuRepo, $itemRepo, $config, $container);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('#');
        $reqStack = $this->createStub(RequestStack::class);
        $reqStack->method('getCurrentRequest')->willReturn(null);
        $urlResolver ??= $this->createMenuUrlResolver($urlGenerator, $reqStack);

        $requestStack ??= new RequestStack();
        $urlGenForDecorator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenForDecorator->method('generate')->willReturn('#');
        $decorator      = new CurrentRouteTreeDecorator($this->createMenuUrlResolver($urlGenForDecorator, $reqStack));
        $localeResolver = new MenuLocaleResolver([]);

        $iconResolver = new MenuIconNameResolver([]);

        return new MenuExtension(
            $loader,
            $urlResolver,
            $config,
            new DefaultMenuCodeResolver(),
            $requestStack,
            $decorator,
            $localeResolver,
            $iconResolver,
            '@NowoDashboardMenuBundle/dashboard/layout.html.twig',
            false,
            $dataCollector,
            $menuQueryCounter,
            $connection,
        );
    }

    private function createMenuUrlResolver(UrlGeneratorInterface $urlGenerator, RequestStack $requestStack): MenuUrlResolver
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        return new MenuUrlResolver($urlGenerator, $requestStack, $router);
    }

    private function createMenuTreeLoader(MenuRepository $menuRepo, MenuItemRepository $itemRepo, MenuConfigResolver $config, ContainerInterface $container): MenuTreeLoader
    {
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
}
