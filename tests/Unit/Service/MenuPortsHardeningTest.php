<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\AutoTagMenuLinkResolversPass;
use Nowo\DashboardMenuBundle\DependencyInjection\Compiler\MultiVariantPortsPass;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Repository\MenuItemRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\DashboardMenuBundle\Service\MenuItemContextCurrentMatcherInterface;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuRequestContextFactory;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Service\PermissionKeyAwareMenuPermissionChecker;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\ContextOnlyChecker;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\DualHostChecker;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\PrefixMatcherOnly;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\PrefixMatcherOverriding;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\RecordingLogger;
use Nowo\DashboardMenuBundle\Tests\Unit\Service\Fixtures\ResolverWithClassAsId;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;

use function array_column;
use function array_map;
use function print_r;

/**
 * Audit MN fixes: deny-keyed fallback for an unresolvable checker (MN-D-M1), build-time warning for multi-variant
 * services (MN-D-L1), dropped context values (MN-D-L2), link resolvers registered under their class (MN-D-L3) and the
 * redacted dump of the request context (MN-D-I2).
 */
final class MenuPortsHardeningTest extends TestCase
{
    public function testNoCheckerConfiguredStillAllowsEverything(): void
    {
        self::assertSame(['Public', 'Secret'], $this->labels($this->loader(null, null)->loadTree('main', 'en')));
    }

    public function testAnUnresolvableCheckerHidesKeyedItemsAndLogsAnError(): void
    {
        $logger = new RecordingLogger();

        self::assertSame(['Public'], $this->labels($this->loader('app.typo', null, $logger)->loadTree('main', 'en')));
        self::assertSame('error', $logger->records[0][0]);
        self::assertStringContainsString('"app.typo"', $logger->records[0][1]);
    }

    public function testACheckerImplementingNoPortIsTreatedAsUnresolvable(): void
    {
        self::assertSame(['Public'], $this->labels($this->loader('app.checker', new stdClass())->loadTree('main', 'en')));
    }

    public function testAnUnresolvableCheckerThrowsOnlyWhenStrict(): void
    {
        $logger = new RecordingLogger();
        self::assertSame(['Public'], $this->labels($this->loader('app.typo', null, $logger, true)->loadTree('main', 'en')), 'debug alone never locks the dashboard');
        self::assertSame('error', $logger->records[0][0]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"app.typo"');

        $this->loader('app.typo', null, null, false, true)->loadTree('main', 'en');
    }

    public function testStrictModeIsOffByDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([], $container);
        self::assertFalse($container->getParameter('nowo_dashboard_menu.strict_permission_checker'));

        $strict = new ContainerBuilder();
        $strict->setParameter('kernel.environment', 'prod');
        $strict->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([['strict_permission_checker' => true]], $strict);
        self::assertTrue($strict->getParameter('nowo_dashboard_menu.strict_permission_checker'));
    }

    public function testDroppedContextValuesAreReportedInDebugOnly(): void
    {
        $logger = new RecordingLogger();
        $loader = $this->loader('app.checker', new ContextOnlyChecker(), $logger, true);

        self::assertSame(['Public'], $this->labels($loader->loadTree('main', 'en', new stdClass())), 'an object becomes an empty context: the needed attribute is missing, so keyed items are denied');
        self::assertSame(['Public', 'Secret'], $this->labels($loader->loadTree('main', 'en', ['tenant' => 'acme'])));
        self::assertSame(['Public'], $this->labels($loader->loadTree('main', 'en', ['tenant' => new stdClass()])));
        self::assertSame(['warning', 'warning'], array_column($logger->records, 0));
        self::assertSame('object stdClass', $logger->records[0][2]['dropped']);
        self::assertSame('attribute tenant (stdClass)', $logger->records[1][2]['dropped']);

        $quiet = new RecordingLogger();
        $this->loader('app.checker', new ContextOnlyChecker(), $quiet)->loadTree('main', 'en', new stdClass());
        $this->loader('app.checker', new PermissionKeyAwareMenuPermissionChecker(), $quiet, true)->loadTree('main', 'en', new stdClass());
        self::assertSame([], $quiet->records, 'no warning outside debug, nor for a checker that receives the raw value');
    }

    public function testDroppedValuesDescribeWhatTheValueObjectCannotCarry(): void
    {
        self::assertSame([], MenuRequestContextFactory::droppedValues(null));
        self::assertSame([], MenuRequestContextFactory::droppedValues('admin'));
        self::assertSame([], MenuRequestContextFactory::droppedValues(Request::create('/')));
        self::assertSame([], MenuRequestContextFactory::droppedValues(MenuRequestContextFactory::permissionContext(null)));
        self::assertSame([], MenuRequestContextFactory::droppedValues(['tenant' => 'acme', 'roles' => ['a']]));
        self::assertSame(['object stdClass'], MenuRequestContextFactory::droppedValues(new stdClass()));
        self::assertSame(['attribute #0 (string)', 'attribute map (array)'], MenuRequestContextFactory::droppedValues(['x', 'map' => ['k' => 'v'], 'ok' => 1]));
    }

    public function testMultiVariantServicesAreReportedAtBuild(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $container->register('app.dual', DualHostChecker::class)->addTag('nowo_dashboard_menu.permission_checker');
        $container->register('app.single', ContextOnlyChecker::class)->addTag('nowo_dashboard_menu.permission_checker');
        $container->register('app.prefix', PrefixMatcherOnly::class)->addTag('nowo_dashboard_menu.current_matcher');
        $container->register('app.prefix_override', PrefixMatcherOverriding::class)->addTag('nowo_dashboard_menu.current_matcher');
        $container->register('bundle.allow_all', AllowAllMenuPermissionChecker::class)->addTag('nowo_dashboard_menu.permission_checker');

        (new MultiVariantPortsPass())->process($container);

        $log = $container->getCompiler()->getLog();
        self::assertCount(2, $log);
        self::assertStringContainsString('"app.dual"', $log[0]);
        self::assertStringContainsString('calls only ' . MenuPermissionCheckerInterface::class, $log[0]);
        self::assertStringContainsString('"app.prefix_override"', $log[1]);
        self::assertStringContainsString(MenuCurrentMatcherInterface::class, $log[1]);

        $pass = new MultiVariantPortsPass();
        self::assertNull($pass->warningFor('Missing\\Class', 'x', [MenuPermissionCheckerInterface::class, MenuItemContextPermissionCheckerInterface::class]));
        self::assertNull($pass->warningFor(PrefixMatcherOnly::class, 'x', [MenuCurrentMatcherInterface::class, MenuItemContextCurrentMatcherInterface::class]));

        $debug = new ContainerBuilder();
        $debug->setParameter('kernel.debug', true);
        $debug->register('app.dual', DualHostChecker::class)->addTag('nowo_dashboard_menu.permission_checker');
        (new MultiVariantPortsPass())->process($debug);
        self::assertCount(1, $debug->getCompiler()->getLog());
    }

    public function testLinkResolversRegisteredUnderTheirClassAreAutoTagged(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ResolverWithClassAsId::class, new Definition());

        (new AutoTagMenuLinkResolversPass())->process($container);

        self::assertTrue($container->getDefinition(ResolverWithClassAsId::class)->hasTag('nowo_dashboard_menu.menu_link_resolver'));
    }

    public function testRequestContextDumpRedactsTokenLikeQueryValues(): void
    {
        $secret  = 'REDACTED-TEST-x';
        $context = new MenuRequestContext('app_reset', [], '/reset', 'en', ['token' => $secret, 'access_key' => $secret, 'code' => $secret, 'page' => '2']);

        self::assertSame(['token' => '[redacted]', 'access_key' => '[redacted]', 'code' => '[redacted]', 'page' => '2'], $context->__debugInfo()['query']);
        self::assertStringNotContainsString($secret, print_r($context, true));
        self::assertSame('app_reset', $context->__debugInfo()['route']);
    }

    public function testImportRefusesAMenuNamingAnUnknownChecker(): void
    {
        $known  = ['app.checker' => 'App checker', 'app.other' => 'Shared label', 'app.third' => 'Shared label'];
        $stored = [];
        $em     = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$stored): void {
            if ($entity instanceof Menu) {
                $stored[] = $entity->getPermissionChecker();
            }
        });
        $importer = new MenuImporter($this->createStub(MenuItemRepository::class), $this->createStub(MenuRepository::class), $em, $known);

        $result = $importer->import(['menu' => ['code' => 'side', 'permissionChecker' => 'app.typo'], 'items' => []]);
        self::assertSame(0, $result['created']);
        self::assertSame(['Menu "side": unknown permission checker "app.typo"; the menu was not imported.'], $result['errors']);

        $ambiguous = $importer->import(['menu' => ['code' => 'side', 'permissionChecker' => 'Shared label'], 'items' => []]);
        self::assertSame(0, $ambiguous['created']);
        self::assertStringContainsString('names several checkers', $ambiguous['errors'][0]);

        foreach (['app.checker', ' App checker ', '', null] as $checker) {
            $ok = $importer->import(['menu' => ['code' => 'side', 'permissionChecker' => $checker], 'items' => []]);
            self::assertSame([], $ok['errors'], (string) $checker);
            self::assertSame(1, $ok['created']);
        }
        self::assertSame(['app.checker', 'app.checker', null, null], $stored, 'a label is stored as the service id it names');

        $unchecked = new MenuImporter($this->createStub(MenuItemRepository::class), $this->createStub(MenuRepository::class), $this->createStub(EntityManagerInterface::class));
        self::assertSame([], $unchecked->import(['menu' => ['code' => 'side', 'permissionChecker' => 'app.typo'], 'items' => []])['errors']);
    }

    /**
     * @param list<array<string, mixed>> $tree
     *
     * @return list<string>
     */
    private function labels(array $tree): array
    {
        return array_map(static fn (array $node): string => $node['item']->getLabel(), $tree);
    }

    private function loader(?string $checkerId, ?object $checker, ?RecordingLogger $logger = null, bool $debug = false, bool $strict = false): MenuTreeLoader
    {
        $raw = [
            'menu'  => ['id' => 1, 'code' => 'main', 'attributes_key' => '', 'permission_checker' => $checkerId],
            'items' => [
                ['id' => 10, 'menu_id' => 1, 'parent_id' => null, 'position' => 0, 'label' => 'Public', 'link_type' => 'route', 'route_name' => 'app_home', 'item_type' => 'link'],
                ['id' => 20, 'menu_id' => 1, 'parent_id' => null, 'position' => 1, 'label' => 'Secret', 'link_type' => 'route', 'route_name' => 'app_secret', 'item_type' => 'link', 'permission_keys' => '["secret"]'],
            ],
        ];
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findMenuAndItemsRaw')->willReturn($raw);

        $checkerLocator = $this->createStub(ContainerInterface::class);
        $checkerLocator->method('has')->willReturnCallback(static fn (string $id): bool => $checker !== null && $id === 'app.checker');
        $checkerLocator->method('get')->willReturn($checker);

        return new MenuTreeLoader(
            $menuRepo,
            $this->createStub(MenuItemRepository::class),
            new MenuConfigResolver(['project' => null], $menuRepo),
            new MenuIconNameResolver([]),
            $checkerLocator,
            new AllowAllMenuPermissionChecker(),
            $this->createStub(ContainerInterface::class),
            logger: $logger,
            debug: $debug,
            strictPermissionChecker: $strict,
        );
    }
}
