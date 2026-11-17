<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLinkResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

use function in_array;

/**
 * REQ-TEST-003: children produced by a service item's link resolver (rows, malformed rows, failures, permission checks).
 */
final class MenuTreeLoaderDynamicChildrenTest extends TestCase
{
    public function testRowsBecomeChildNodesAndTheInvalidOnesAreSkipped(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            /** @phpstan-return list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}> */
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): array
            {
                /** @phpstan-var list<array{label: string, href: string, position: int, icon?: string|null, targetBlank?: bool}> $rows malformed rows on purpose */
                $rows = [
                    'not a row',
                    ['label' => '   ', 'href' => '/blank'],
                    ['label' => 'No href'],
                    ['label' => 'Shown', 'href' => ' /shown ', 'position' => '7', 'icon' => 'bi-star', 'targetBlank' => true],
                    ['label' => 'Hidden', 'href' => '/hidden'],
                ];

                return $rows;
            }
        };

        $collector = new DashboardMenuDataCollector();
        $children  = $this->childrenOf($resolver, 'test.resolver', ['test.resolver'], new class implements MenuPermissionCheckerInterface {
            public function canView(MenuItem $item, mixed $context = null): bool
            {
                return $item->getLabel() !== 'Hidden';
            }
        }, $collector);

        self::assertCount(1, $children);
        self::assertSame('Shown', $children[0]['item']->getLabel());
        self::assertSame('/shown', $children[0]['item']->getRuntimeHref());
        self::assertSame(7, $children[0]['item']->getPosition());
        self::assertTrue($children[0]['item']->getTargetBlank());
    }

    public function testTheResolverCanBeNamedByItsDashboardLabel(): void
    {
        $resolver = new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): array
            {
                return [['label' => 'Via label', 'href' => '/l', 'position' => 0]];
            }
        };

        $children = $this->childrenOf($resolver, 'My resolver', ['app.resolver'], null, null, ['app.resolver' => 'My resolver']);

        self::assertCount(1, $children);
    }

    /**
     * @return iterable<string, array{object|string|null, bool}>
     */
    public static function unusableResolvers(): iterable
    {
        yield 'a resolver that answers a plain URL' => [new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                return '/just-a-link';
            }
        }, false];
        yield 'a resolver that throws' => [new class implements MenuLinkResolverInterface {
            public function resolveHref(MenuItem $item, ?Request $request, mixed $permissionContext = null): string
            {
                throw new RuntimeException('down');
            }
        }, false];
        yield 'a service that is not a resolver' => [new stdClass(), false];
        yield 'a service that cannot be fetched' => [null, true];
    }

    #[DataProvider('unusableResolvers')]
    public function testAnUnusableResolverYieldsNoChildren(?object $resolver, bool $getThrows): void
    {
        self::assertSame([], $this->childrenOf($resolver, 'test.resolver', ['test.resolver'], null, null, [], $getThrows));
    }

    public function testNoChildrenWithoutAResolverIdOrWithAnUnknownOne(): void
    {
        self::assertSame([], $this->childrenOf(null, null, []));
        self::assertSame([], $this->childrenOf(null, '', []));
        self::assertSame([], $this->childrenOf(null, 'unknown.id', ['other']));
    }

    /**
     * @param list<string> $knownIds
     * @param array<string, string> $choices
     *
     * @return list<array<string, mixed>>
     */
    private function childrenOf(?object $resolver, ?string $resolverId, array $knownIds, ?MenuPermissionCheckerInterface $checker = null, ?DashboardMenuDataCollector $collector = null, array $choices = [], bool $getThrows = false): array
    {
        $menu = new Menu();
        $menu->setCode('main');
        $menu->setPermissionChecker($checker instanceof MenuPermissionCheckerInterface ? 'row_checker' : null);

        $service = new MenuItem();
        (new ReflectionProperty(MenuItem::class, 'id'))->setValue($service, 1);
        $service->setMenu($menu);
        $service->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $service->setLabel('Svc');
        $service->setLinkResolver($resolverId);
        $service->setPosition(0);

        $menuRepo = $this->createMock(MenuRepository::class);
        $menuRepo->method('findForCodeWithContextSets')->willReturn($menu);
        $itemRepo = $this->createMock(MenuItemRepository::class);
        $itemRepo->method('findAllForMenuOrderedByTree')->willReturn([$service]);

        $resolvers = $this->createMock(ContainerInterface::class);
        $resolvers->method('has')->willReturnCallback(static fn (string $id): bool => in_array($id, $knownIds, true));
        $resolvers->method('get')->willReturnCallback(static function (string $id) use ($resolver, $getThrows): ?object {
            if ($getThrows) {
                throw new RuntimeException('cannot build');
            }

            return $resolver;
        });
        $checkers = $this->createMock(ContainerInterface::class);
        $checkers->method('has')->willReturn($checker instanceof MenuPermissionCheckerInterface);
        $checkers->method('get')->willReturn($checker);

        $loader = new MenuTreeLoader(
            $menuRepo,
            $itemRepo,
            new MenuConfigResolver(['project' => null], $menuRepo),
            new MenuIconNameResolver([]),
            $checkers,
            new AllowAllMenuPermissionChecker(),
            $resolvers,
            $choices,
            null,
            null,
            60,
            $collector,
        );

        $tree = $loader->loadTree('main', 'en');

        return $tree[0]['children'] ?? [];
    }
}
