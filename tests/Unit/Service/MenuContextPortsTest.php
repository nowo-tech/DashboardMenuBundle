<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagMenuLinkResolversPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagPermissionCheckersPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\MenuCodeResolverPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\CurrentRouteTreeDecorator;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuItemContextCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemViewAdapters;
use Nowo\DashboardMenuBundle\Service\MenuItemViewPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuRequestContextFactory;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use Nowo\DashboardMenuBundle\Service\PermissionKeyAwareMenuPermissionChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function is_string;

/**
 * The request-free ports of 2.19 (REQ-ARCH-001 rule 3): value objects, the translation of the legacy `mixed`
 * context, precedence between variants, and old-vs-new equivalence of the bundled implementations.
 */
final class MenuContextPortsTest extends TestCase
{
    public function testRequestContextKeepsOnlyPlainRouteParams(): void
    {
        $context = new MenuRequestContext('admin_users', ['tenant' => 'acme', 'page' => 2, 'flag' => true, 'none' => null, 'object' => new stdClass(), 7 => 'x'], '/admin/users', 'es', ['q' => 'a'], 'example.test');

        self::assertSame(['tenant' => 'acme', 'page' => 2, 'flag' => true, 'none' => null], $context->routeParams);
        self::assertSame('acme', $context->routeParam('tenant'));
        self::assertNull($context->routeParam('missing'));
        self::assertTrue($context->hasRouteParam('none'));
        self::assertFalse($context->hasRouteParam('object'));
        self::assertSame('admin_users', $context->route);
        self::assertSame('/admin/users', $context->path);
        self::assertSame('es', $context->locale);
        self::assertSame(['q' => 'a'], $context->query);
        self::assertSame('example.test', $context->host);
    }

    public function testPermissionContextKeepsOnlyPlainAttributes(): void
    {
        $context = new MenuPermissionContext(null, ['tenant' => 'acme', 'roles' => ['a', 'b'], 'map' => ['k' => 'v'], 'mixed' => ['a', new stdClass()], 'object' => new stdClass(), 3 => 'x', 'none' => null]);

        self::assertSame(['tenant' => 'acme', 'roles' => ['a', 'b'], 'none' => null], $context->attributes);
        self::assertSame(['a', 'b'], $context->attribute('roles'));
        self::assertNull($context->attribute('missing'));
        self::assertTrue($context->hasAttribute('none'));
        self::assertFalse($context->hasAttribute('object'));
        self::assertNull($context->request);
    }

    public function testFactoryTranslatesTheRequestAndTheLegacyContext(): void
    {
        $request = Request::create('https://example.test/admin/users?q=a');
        $request->attributes->set('_route', 'admin_users');
        $request->attributes->set('_route_params', ['tenant' => 'acme']);
        $request->setLocale('fr');

        $view = MenuRequestContextFactory::fromRequest($request);
        self::assertSame('admin_users', $view->route);
        self::assertSame(['tenant' => 'acme'], $view->routeParams);
        self::assertSame('/admin/users', $view->path);
        self::assertSame('fr', $view->locale);
        self::assertSame(['q' => 'a'], $view->query);
        self::assertSame('example.test', $view->host);

        $bare = Request::create('/x');
        $bare->attributes->set('_route_params', 'not-an-array');
        self::assertNull(MenuRequestContextFactory::fromRequest($bare)->route);
        self::assertSame([], MenuRequestContextFactory::fromRequest($bare)->routeParams);

        $given = new MenuPermissionContext();
        self::assertSame($given, MenuRequestContextFactory::permissionContext($given));
        self::assertSame('admin_users', MenuRequestContextFactory::permissionContext($request)->request?->route);
        self::assertSame(['tenant' => 'acme'], MenuRequestContextFactory::permissionContext(['tenant' => 'acme'])->attributes);
        self::assertSame(['value' => 'admin'], MenuRequestContextFactory::permissionContext('admin')->attributes);
        self::assertEquals(new MenuPermissionContext(), MenuRequestContextFactory::permissionContext(null));
        self::assertEquals(new MenuPermissionContext(), MenuRequestContextFactory::permissionContext(new stdClass()));
    }

    /**
     * @return iterable<string, array{0: list<string>|null, 1: bool}>
     */
    public static function items(): iterable
    {
        yield 'no keys' => [null, true];
        yield 'empty keys' => [[], true];
        yield 'one unknown key' => [['unknown.key'], true];
        yield 'two keys, all' => [['a', 'b'], true];
        yield 'two keys, any' => [['a', 'b'], false];
    }

    /**
     * @param list<string>|null $keys
     */
    #[DataProvider('items')]
    public function testBundledCheckersAnswerTheSameOnBothPorts(?array $keys, bool $unanimous): void
    {
        $item = new MenuItem();
        $item->setPermissionKeys($keys);
        $item->setIsUnanimous($unanimous);
        $view = MenuItemView::fromEntity($item);

        foreach ([null, Request::create('/admin'), ['tenant' => 'acme'], 'admin'] as $legacyContext) {
            $context = MenuRequestContextFactory::permissionContext($legacyContext);

            $keyAware = new PermissionKeyAwareMenuPermissionChecker();
            self::assertSame($keyAware->canView($item, $legacyContext), $keyAware->canViewItemFor($view, $context));
            self::assertSame(($keys ?? []) === [], $keyAware->canViewItemFor($view, $context), 'keyed items are denied by default');

            $allowAll = new AllowAllMenuPermissionChecker();
            self::assertSame($allowAll->canView($item, $legacyContext), $allowAll->canViewItemFor($view, $context));
        }
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string|null}>
     */
    public static function routes(): iterable
    {
        yield 'exact prefix' => ['admin_ops_defaults', 'admin_ops_defaults'];
        yield 'section child' => ['admin_ops_defaults', 'admin_ops_defaults_section'];
        yield 'kit prefix' => ['nowo_http_log_admin_index', 'nowo_http_log_detail'];
        yield 'other route' => ['admin_ops_defaults', 'admin_appearance'];
        yield 'unknown item route' => ['admin_appearance', 'admin_appearance'];
        yield 'no current route' => ['admin_ops_defaults', null];
        yield 'empty current route' => ['admin_ops_defaults', ''];
        yield 'no item route' => [null, 'admin_ops_defaults'];
        yield 'empty item route' => ['', 'admin_ops_defaults'];
    }

    #[DataProvider('routes')]
    public function testRoutePrefixMatcherAnswersTheSameOnBothPorts(?string $itemRoute, ?string $currentRoute): void
    {
        $matcher = new class extends AbstractRoutePrefixMenuCurrentMatcher {
            protected function routePrefixes(): array
            {
                return ['admin_ops_defaults' => ['admin_ops_defaults'], 'nowo_http_log_admin_index' => ['nowo_http_log_']];
            }
        };
        $item = new MenuItem();
        $item->setRouteName($itemRoute);
        $request = Request::create('/x');
        if ($currentRoute !== null) {
            $request->attributes->set('_route', $currentRoute);
        }

        self::assertSame(
            $matcher->isCurrent($item, $request, '/x'),
            $matcher->isCurrentItemFor(MenuItemView::fromEntity($item), MenuRequestContextFactory::fromRequest($request), '/x'),
        );
    }

    public function testDefaultCodeResolverAnswersTheSameOnBothPorts(): void
    {
        $resolver = new DefaultMenuCodeResolver();
        $request  = Request::create('/');

        self::assertSame($resolver->resolveMenuCode($request, 'sidebar'), $resolver->resolveMenuCodeFor(MenuRequestContextFactory::fromRequest($request), 'sidebar'));
        self::assertSame('sidebar', MenuItemViewAdapters::resolveMenuCode($resolver, $request, 'sidebar'));
        self::assertSame('sidebar_admin', MenuItemViewAdapters::resolveMenuCode(new ContextCodeResolver(), $request, 'sidebar'));
    }

    public function testAContextOnlyCheckerIsUsedNotTheAllowAllFallback(): void
    {
        $checker = new ContextChecker();
        $request = Request::create('/admin');
        $request->attributes->set('_route', 'admin_index');

        $labels = array_map(static fn (array $node): string => $node['item']->getLabel(), $this->createRawLoader($checker)->loadTree('main', 'en', $request));

        self::assertSame(['Public', 'Service'], $labels, 'the keyed item stays hidden: the context checker is called, not the allow-all fallback');
        self::assertSame(['admin_index'], array_values(array_unique($checker->seenRoutes)));
    }

    public function testAnOlderVariantWinsWhenAServiceImplementsSeveral(): void
    {
        $item = new MenuItem();
        $item->setPermissionKeys(['secret']);

        self::assertTrue(MenuItemViewAdapters::canView(new EntityAndContextChecker(), $item, null), 'entity method called');
        self::assertTrue(MenuItemViewAdapters::canView(new ViewAndContextChecker(), $item, null), 'view method called');
        self::assertFalse(MenuItemViewAdapters::canView(new ContextChecker(), $item, null));
    }

    public function testContextLinkResolverReceivesValueObjects(): void
    {
        $resolver = new ContextLinkResolver();
        $request  = Request::create('/admin');
        $request->attributes->set('_route', 'admin_index');
        $request->attributes->set('_route_params', ['tenant' => 'acme']);
        $stack = new RequestStack([$request]);

        $nodes = $this->createRawLoader(new ContextChecker(), $resolver, $stack)->loadTree('main', 'en', ['tenant' => 'acme']);

        self::assertSame('Projects of acme', $nodes[1]['children'][0]['item']->getLabel());
        self::assertSame('admin_index', $resolver->seenRoute);
        self::assertSame(['tenant' => 'acme'], $resolver->seenAttributes);

        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.single');
        $container = $this->createStub(SymfonyContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => $id === 'app.single');
        $container->method('get')->willReturn(new ContextLinkResolver('/reports'));
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());

        self::assertSame('/reports', (new MenuUrlResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack(), $router, $container))->getHref($item));
        self::assertSame([['label' => 'Projects of nobody', 'href' => '/projects/nobody', 'position' => 0]], MenuItemViewAdapters::resolveHref(new ContextLinkResolver(), $item, null, null));
    }

    public function testDecoratorCallsTheContextCurrentMatcher(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $item->setExternalUrl('/admin');
        $item->setRouteName('admin_index');
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn(new RouteCollection());
        $urlResolver = new MenuUrlResolver($this->createStub(UrlGeneratorInterface::class), new RequestStack(), $router, $this->createStub(SymfonyContainerInterface::class));

        $request = Request::create('/admin/users');
        $request->attributes->set('_route', 'admin_users');

        $decorated = (new CurrentRouteTreeDecorator($urlResolver, [new ContextMatcher()]))->decorate([['item' => $item, 'children' => []]], $request);
        self::assertTrue($decorated[0]['isCurrent']);
        self::assertFalse((new CurrentRouteTreeDecorator($urlResolver, [new ContextMatcher()]))->decorate([['item' => $item, 'children' => []]], Request::create('/admin/users'))[0]['isCurrent']);
    }

    public function testAutoTagPassesAndExtensionKnowTheContextPorts(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.context_checker', ContextChecker::class);
        $container->register('app.context_resolver', ContextLinkResolver::class);
        (new AutoTagPermissionCheckersPass())->process($container);
        (new AutoTagMenuLinkResolversPass())->process($container);

        self::assertSame([['label' => 'Context checker']], $container->getDefinition('app.context_checker')->getTag('nowo_dashboard_menu.permission_checker'));
        self::assertSame([['label' => 'app.context_resolver']], $container->getDefinition('app.context_resolver')->getTag('nowo_dashboard_menu.menu_link_resolver'));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([], $container);
        $instanceof = $container->getAutoconfiguredInstanceof();
        self::assertTrue($instanceof[MenuItemContextCurrentMatcherInterface::class]->hasTag('nowo_dashboard_menu.current_matcher'));
        self::assertSame(DefaultMenuCodeResolver::class, (string) $container->getAlias(MenuCodeContextResolverInterface::class));
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function codeResolverSetups(): iterable
    {
        yield 'bundle defaults' => [null, null, MenuCodeResolverInterface::class];
        yield 'host overrides the deprecated interface' => [ContextCodeResolver::class, null, MenuCodeResolverInterface::class];
        yield 'host aliases the new interface' => [null, ContextCodeResolver::class, MenuCodeContextResolverInterface::class];
        yield 'host aliases both' => [DefaultMenuCodeResolver::class, ContextCodeResolver::class, MenuCodeContextResolverInterface::class];
    }

    #[DataProvider('codeResolverSetups')]
    public function testCodeResolverPassPicksTheHostChoice(?string $legacyTarget, ?string $contextTarget, string $expected): void
    {
        $container = new ContainerBuilder();
        $container->register(DefaultMenuCodeResolver::class, DefaultMenuCodeResolver::class);
        $container->setAlias(MenuCodeResolverInterface::class, $legacyTarget ?? DefaultMenuCodeResolver::class);
        $container->setAlias(MenuCodeContextResolverInterface::class, $contextTarget ?? DefaultMenuCodeResolver::class);
        $container->setAlias(MenuCodeResolverPass::RESOLVER_ID, MenuCodeResolverInterface::class);

        (new MenuCodeResolverPass())->process($container);

        self::assertSame($expected, (string) $container->getAlias(MenuCodeResolverPass::RESOLVER_ID));
    }

    public function testCodeResolverPassHandlesAServiceDefinedOnTheInterfaceId(): void
    {
        $container = new ContainerBuilder();
        $container->register(MenuCodeContextResolverInterface::class, ContextCodeResolver::class);
        $container->setAlias(MenuCodeResolverPass::RESOLVER_ID, MenuCodeResolverInterface::class);
        (new MenuCodeResolverPass())->process($container);
        self::assertSame(MenuCodeContextResolverInterface::class, (string) $container->getAlias(MenuCodeResolverPass::RESOLVER_ID));

        $empty = new ContainerBuilder();
        (new MenuCodeResolverPass())->process($empty);
        self::assertFalse($empty->hasAlias(MenuCodeResolverPass::RESOLVER_ID));

        $none = new ContainerBuilder();
        $none->setAlias(MenuCodeResolverPass::RESOLVER_ID, MenuCodeResolverInterface::class);
        (new MenuCodeResolverPass())->process($none);
        self::assertSame(MenuCodeResolverInterface::class, (string) $none->getAlias(MenuCodeResolverPass::RESOLVER_ID));
    }

    private function createRawLoader(MenuPermissionCheckerInterface|MenuItemViewPermissionCheckerInterface|MenuItemContextPermissionCheckerInterface $checker, ?object $linkResolver = null, ?RequestStack $requestStack = null): MenuTreeLoader
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
            requestStack: $requestStack,
        );
    }
}

final class ContextChecker implements MenuItemContextPermissionCheckerInterface
{
    public const DASHBOARD_LABEL = 'Context checker';

    /** @var list<string|null> */
    public array $seenRoutes = [];

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        $this->seenRoutes[] = $context->request?->route;

        return !in_array('secret', $item->permissionKeys, true);
    }
}

final class EntityAndContextChecker implements MenuPermissionCheckerInterface, MenuItemContextPermissionCheckerInterface
{
    public function canView(MenuItem $item, mixed $context = null): bool
    {
        return true;
    }

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return false;
    }
}

final class ViewAndContextChecker implements MenuItemViewPermissionCheckerInterface, MenuItemContextPermissionCheckerInterface
{
    public function canViewItem(MenuItemView $item, mixed $context = null): bool
    {
        return true;
    }

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        return false;
    }
}

final class ContextLinkResolver implements MenuItemContextLinkResolverInterface
{
    public ?string $seenRoute = null;

    /** @var array<string, mixed> */
    public array $seenAttributes = [];

    public function __construct(private readonly ?string $single = null)
    {
    }

    public function resolveItemHrefFor(MenuItemView $item, ?MenuRequestContext $request, MenuPermissionContext $context): string|array
    {
        if ($this->single !== null) {
            return $this->single;
        }
        $this->seenRoute      = $request?->route;
        $this->seenAttributes = $context->attributes;
        $tenant               = $context->attribute('tenant');

        return [['label' => 'Projects of ' . (is_string($tenant) ? $tenant : 'nobody'), 'href' => '/projects/' . (is_string($tenant) ? $tenant : 'nobody'), 'position' => 0]];
    }
}

final class ContextMatcher implements MenuItemContextCurrentMatcherInterface
{
    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool
    {
        return $item->routeName === 'admin_index' && $request->route === 'admin_users';
    }
}

final class ContextCodeResolver implements MenuCodeContextResolverInterface
{
    public function resolveMenuCodeFor(MenuRequestContext $request, string $hint): string
    {
        return $hint . '_admin';
    }
}
