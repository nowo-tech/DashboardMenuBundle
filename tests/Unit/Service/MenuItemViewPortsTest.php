<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagMenuLinkResolversPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagPermissionCheckersPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuNodeView;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuItemViewCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function in_array;

/**
 * Host adapters written against {@see MenuItemView} instead of the {@see MenuItem} entity.
 */
final class MenuItemViewPortsTest extends TestCase
{
    public function testViewPermissionCheckerFiltersTheTree(): void
    {
        $checker = new ViewChecker();
        $loader  = $this->createRawLoader($checker);

        $labels = array_map(static fn (array $node): string => $node['item']->getLabel(), $loader->loadTree('main', 'en'));

        self::assertSame(['Public', 'Service'], $labels);
        self::assertContains('Secret', $checker->seenLabels);
        self::assertSame('main', $checker->seenMenuCode);
    }

    public function testEntityPortWinsWhenAServiceImplementsBoth(): void
    {
        $checker = new BothChecker();
        $loader  = $this->createRawLoader($checker);

        $labels = array_map(static fn (array $node): string => $node['item']->getLabel(), $loader->loadTree('main', 'en'));

        self::assertSame(['Public', 'Secret', 'Service'], $labels);
        self::assertFalse($checker->viewCalled);
    }

    public function testViewLinkResolverAddsDynamicChildrenAndLoadTreeViewReturnsDtos(): void
    {
        $resolver = new ViewLinkResolver();
        $loader   = $this->createRawLoader(new ViewChecker(), $resolver);

        $nodes = $loader->loadTreeView('main', 'en');

        self::assertContainsOnlyInstancesOf(MenuNodeView::class, $nodes);
        self::assertSame(['Public', 'Service'], array_map(static fn (MenuNodeView $n): string => $n->item->label, $nodes));
        self::assertSame('app.projects', $resolver->seenResolver);
        self::assertCount(1, $nodes[1]->children);
        self::assertSame('Project A', $nodes[1]->children[0]->item->label);
        self::assertSame('/projects/a', $nodes[1]->children[0]->item->runtimeHref);
    }

    public function testUrlResolverCallsViewLinkResolver(): void
    {
        $container = $this->createStub(SymfonyContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.single');
        $container->method('get')->willReturn(new ViewSingleLinkResolver());

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $urlResolver = new MenuUrlResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack(), $router, $container);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.single');

        self::assertSame('/reports', $urlResolver->getHref($item));
    }

    public function testResolverOutputAndDynamicRowsGetTheSchemeAllowlist(): void
    {
        $container = $this->createStub(SymfonyContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.evil');
        $container->method('get')->willReturn(new class implements MenuItemViewLinkResolverInterface {
            public function resolveItemHref(MenuItemView $item, ?Request $request, mixed $permissionContext = null): string
            {
                return 'javascript:alert(1)';
            }
        });
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $urlResolver = new MenuUrlResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack(), $router, $container);

        $service = new MenuItem();
        $service->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $service->setLinkResolver('app.evil');
        self::assertSame('#', $urlResolver->getHref($service));

        $dynamic = new MenuItem();
        $dynamic->setRuntimeHref(' vbscript:msgbox(1)');
        self::assertSame('#', $urlResolver->getHref($dynamic));

        $safe = new MenuItem();
        $safe->setRuntimeHref(' /projects/a ');
        self::assertSame('/projects/a', $urlResolver->getHref($safe));
    }

    public function testDecoratorCallsViewCurrentMatcherAndEntityMatcherStillWorks(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $item->setExternalUrl('/admin');
        $item->setRouteName('admin_index');

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $container   = $this->createStub(SymfonyContainerInterface::class);
        $urlResolver = new MenuUrlResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack(), $router, $container);

        $decorator = new CurrentRouteTreeDecorator($urlResolver, [new ViewCurrentMatcher()]);
        $result    = $decorator->decorate([['item' => $item, 'children' => []]], Request::create('/admin/users'));

        self::assertTrue($result[0]['isCurrent']);

        $entityMatcher = new class implements MenuCurrentMatcherInterface {
            public function isCurrent(MenuItem $item, Request $request, string $resolvedHref): bool
            {
                return false;
            }
        };
        $decorator = new CurrentRouteTreeDecorator($urlResolver, [$entityMatcher]);
        self::assertFalse($decorator->decorate([['item' => $item, 'children' => []]], Request::create('/admin/users'))[0]['isCurrent']);
    }

    public function testAutoTagPassesTagViewPorts(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.view_checker', ViewChecker::class);
        $container->register('app.view_resolver', ViewLinkResolver::class);

        (new AutoTagPermissionCheckersPass())->process($container);
        (new AutoTagMenuLinkResolversPass())->process($container);

        self::assertSame([['label' => 'View checker']], $container->getDefinition('app.view_checker')->getTag('nowo_dashboard_menu.permission_checker'));
        self::assertSame([['label' => 'app.view_resolver']], $container->getDefinition('app.view_resolver')->getTag('nowo_dashboard_menu.menu_link_resolver'));
    }

    public function testExtensionAutoconfiguresViewCurrentMatcher(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([], $container);

        $instanceof = $container->getAutoconfiguredInstanceof();
        self::assertArrayHasKey(MenuItemViewCurrentMatcherInterface::class, $instanceof);
        self::assertTrue($instanceof[MenuItemViewCurrentMatcherInterface::class]->hasTag('nowo_dashboard_menu.current_matcher'));
    }

    private function createRawLoader(MenuPermissionCheckerInterface|MenuItemViewPermissionCheckerInterface $checker, ?object $linkResolver = null): MenuTreeLoader
    {
        $raw = [
            'menu'  => ['id' => 1, 'code' => 'main', 'attributes_key' => '', 'permission_checker' => 'app.checker'],
            'items' => [
                ['id' => 10, 'menu_id' => 1, 'parent_id' => null, 'position' => 0, 'label' => 'Public', 'link_type' => 'route', 'route_name' => 'app_home', 'item_type' => 'link'],
                ['id' => 20, 'menu_id' => 1, 'parent_id' => null, 'position' => 1, 'label' => 'Secret', 'link_type' => 'route', 'route_name' => 'app_secret', 'item_type' => 'link', 'permission_keys' => '["secret"]'],
                ['id' => 30, 'menu_id' => 1, 'parent_id' => null, 'position' => 2, 'label' => 'Service', 'link_type' => null, 'route_name' => null, 'item_type' => 'service', 'link_resolver' => 'app.projects'],
            ],
        ];

        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->willReturn($raw);

        $checkerLocator = $this->createStub(ContainerInterface::class);
        $checkerLocator->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.checker');
        $checkerLocator->method('get')->willReturn($checker);

        $resolverLocator = $this->createStub(ContainerInterface::class);
        $resolverLocator->method('has')->willReturnCallback(static fn (string $id): bool => $linkResolver !== null && $id === 'app.projects');
        $resolverLocator->method('get')->willReturn($linkResolver);

        return new MenuTreeLoader(
            $menuRepo,
            $this->createStub(MenuItemRepository::class),
            new MenuConfigResolver(['project' => null], $menuRepo),
            new MenuIconNameResolver([]),
            $checkerLocator,
            new AllowAllMenuPermissionChecker(),
            $resolverLocator,
        );
    }
}

final class ViewChecker implements MenuItemViewPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'View checker';

    /** @var list<string> */
    public array $seenLabels = [];

    public ?string $seenMenuCode = null;

    public function canViewItem(MenuItemView $item, mixed $context = null): bool
    {
        $this->seenLabels[] = $item->label;
        $this->seenMenuCode ??= $item->menuCode;

        return !in_array('secret', $item->permissionKeys, true);
    }
}

final class BothChecker implements MenuPermissionCheckerInterface, MenuItemViewPermissionCheckerInterface
{
    public bool $viewCalled = false;

    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }

    public function canViewItem(MenuItemView $item, mixed $context = null): bool
    {
        $this->viewCalled = true;

        return false;
    }
}

final class ViewLinkResolver implements MenuItemViewLinkResolverInterface
{
    public ?string $seenResolver = null;

    public function resolveItemHref(MenuItemView $item, ?Request $request, mixed $permissionContext = null): array
    {
        $this->seenResolver = $item->linkResolver;

        return [['label' => 'Project A', 'href' => '/projects/a', 'position' => 0]];
    }
}

final class ViewSingleLinkResolver implements MenuItemViewLinkResolverInterface
{
    public function resolveItemHref(MenuItemView $item, ?Request $request, mixed $permissionContext = null): string
    {
        return '/reports';
    }
}

final class ViewCurrentMatcher implements MenuItemViewCurrentMatcherInterface
{
    public function isCurrentItem(MenuItemView $item, Request $request, string $resolvedHref): bool
    {
        return $item->routeName === 'admin_index' && str_starts_with($request->getPathInfo(), '/admin');
    }
}
