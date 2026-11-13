<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\EntityManager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\DashboardMenuBundle\Command\GenerateDashboardMenuMigrationCommand;
use Nowo\DashboardMenuBundle\Controller\Dashboard\MenuDashboardController;
use Nowo\DashboardMenuBundle\Doctrine\DashboardMenuEntityManagerResolver;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Event\MenuTreeBuiltEvent;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuNodeView;
use Nowo\DashboardMenuBundle\Model\MenuView;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Tests\Integration\EntityManager\Entity\HostNote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use function array_keys;
use function array_map;
use function getmypid;
use function is_file;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * `doctrine.entity_manager` (2.18, architecture bar rule 6) through a real kernel: a standard host keeps the manager
 * that maps the entities; a DDD host maps them on a dedicated manager only (also with `auto_mapping: true` on the
 * default and without `default_entity_manager`); the dashboard, the importer and the migration command use it; the
 * host's manager is never flushed, cleared or reset. Also the DTO read path: {@see MenuView} and
 * {@see MenuTreeBuiltEvent}.
 */
final class DedicatedEntityManagerKernelTest extends TestCase
{
    private ?EntityManagerTestKernel $kernel = null;

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testStandardHostKeepsTheDefaultManager(): void
    {
        $this->boot([
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['auto_mapping' => true],
        ]);
        $default = $this->registry()->getManager();

        self::assertSame($default, $this->registry()->getManagerForClass(Menu::class));
        self::assertSame($default, $this->container()->get('nowo_dashboard_menu.entity_manager'));
    }

    public function testDedicatedManagerIsUsedEverywhereAndTheHostManagerIsNeverFlushed(): void
    {
        $this->boot($this->dedicatedDoctrine(), ['doctrine' => ['entity_manager' => 'dashboard_menu']]);
        [$host, $bundle] = $this->managersWithSchema();

        self::assertSame('default', $this->container()->getParameter('doctrine.default_entity_manager'));
        self::assertTrue($host->getMetadataFactory()->isTransient(Menu::class), 'not mapped on the auto-mapped host manager');
        self::assertSame($bundle, $this->registry()->getManagerForClass(Menu::class));
        foreach ([MenuDashboardController::class => 'entityManager', MenuImporter::class => 'entityManager', GenerateDashboardMenuMigrationCommand::class => 'em'] as $service => $property) {
            $object = $this->container()->get($service);
            self::assertInstanceOf($service, $object);
            self::assertSame($bundle, (new ReflectionProperty($service, $property))->getValue($object), $service . ' uses the bundle manager');
        }

        $note = new HostNote('pending host change');
        $host->persist($note);

        $importer = $this->container()->get(MenuImporter::class);
        self::assertInstanceOf(MenuImporter::class, $importer);
        $result = $importer->import(['menu' => ['code' => 'sidebar', 'name' => 'Sidebar'], 'items' => [['label' => 'Home', 'position' => 0]]]);

        self::assertSame(1, $result['created']);
        self::assertSame(1, (int) $bundle->getConnection()->fetchOne('SELECT COUNT(*) FROM dashboard_menu_item'));
        self::assertSame(0, (int) $host->getConnection()->fetchOne('SELECT COUNT(*) FROM host_note'), 'the host manager is never flushed');
        self::assertTrue($host->contains($note));

        // DTO read path (rules 1 and 4): render configuration and an event that may add domain-driven entries.
        $dispatcher = $this->container()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(MenuTreeBuiltEvent::class, static function (MenuTreeBuiltEvent $event): void {
            $first = $event->getNodes()[0];
            $event->setNodes([...$event->getNodes(), new MenuNodeView(new MenuItemView(
                id: null,
                menuId: $first->item->menuId,
                menuCode: $event->menuCode,
                parentId: null,
                itemType: $first->item->itemType,
                label: 'Projects',
                translations: [],
                position: 99,
                linkType: $first->item->linkType,
                routeName: null,
                routeParams: null,
                externalUrl: null,
                linkResolver: null,
                runtimeHref: '/projects',
                permissionKeys: [],
                unanimous: true,
                icon: null,
                targetBlank: false,
            ))]);
        });

        $loader = $this->container()->get(MenuTreeLoader::class);
        self::assertInstanceOf(MenuTreeLoader::class, $loader);
        $nodes = $loader->loadTreeView('sidebar', 'en');
        self::assertSame(['Home', 'Projects'], array_map(static fn (MenuNodeView $node): string => $node->item->label, $nodes));

        $view = $loader->loadMenuView('sidebar', 'en');
        self::assertInstanceOf(MenuView::class, $view);
        self::assertSame('sidebar', $view->code);
        self::assertSame('Sidebar', $view->name);
        self::assertArrayHasKey('menu', $view->classes);
        self::assertNull($loader->loadMenuView('missing', 'en'));
    }

    /**
     * DoctrineBundle 3.4 regression: `auto_discover_entities` (on by default for a manager without `mappings` and
     * `auto_mapping`) gives the host manager's chain a default attribute driver, which claims every `#[ORM\Entity]`
     * class, so `getManagerForClass()` answered the host manager and the repositories queried its database. The
     * explicit catch-all default driver reproduces it on every DoctrineBundle version.
     */
    #[DataProvider('hostManagersWithADefaultDriver')]
    public function testDedicatedManagerWinsOverADefaultDriverOfTheHostManager(bool $explicitDefaultDriver): void
    {
        $doctrine = $this->dedicatedDoctrine();
        unset($doctrine['orm']['entity_managers']['default']['auto_mapping'], $doctrine['orm']['entity_managers']['default']['mappings']);
        $this->boot($doctrine, ['doctrine' => ['entity_manager' => 'dashboard_menu']], $explicitDefaultDriver);
        $bundle = $this->registry()->getManager('dashboard_menu');

        // Neither manager is initialized yet: DoctrineBundle 3.4 asks the metadata drivers.
        self::assertSame($bundle, $this->registry()->getManagerForClass(Menu::class));
        self::assertSame($bundle, $this->registry()->getManagerForClass(MenuItem::class));

        [$host, $bundle] = $this->managersWithSchema();
        self::assertTrue($host->getMetadataFactory()->isTransient(Menu::class), 'not mapped on the host manager');
        self::assertSame($bundle, $this->registry()->getManagerForClass(Menu::class));

        $importer = $this->container()->get(MenuImporter::class);
        self::assertInstanceOf(MenuImporter::class, $importer);
        self::assertSame(1, $importer->import(['menu' => ['code' => 'sidebar', 'name' => 'Sidebar'], 'items' => [['label' => 'Home', 'position' => 0]]])['created']);
        $repository = $this->container()->get(MenuRepository::class);
        self::assertInstanceOf(MenuRepository::class, $repository);
        self::assertInstanceOf(Menu::class, $repository->findOneBy(['code' => 'sidebar']), 'the repository reads the bundle database');
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function hostManagersWithADefaultDriver(): iterable
    {
        yield 'host manager without mappings (DoctrineBundle 3.4 auto-discovery)' => [false];
        yield 'host manager with a catch-all default driver' => [true];
    }

    public function testHostWithoutADefaultManagerNameKeepsItsOwnDefault(): void
    {
        $this->boot($this->dedicatedDoctrine('main', false), ['doctrine' => ['entity_manager' => 'dashboard_menu']]);

        self::assertSame(['main', 'dashboard_menu'], array_keys((array) $this->container()->getParameter('doctrine.entity_managers')));
        self::assertSame('main', $this->container()->getParameter('doctrine.default_entity_manager'));
        $main = $this->registry()->getManager('main');
        self::assertInstanceOf(EntityManagerInterface::class, $main);
        self::assertTrue($main->getMetadataFactory()->isTransient(Menu::class));
        self::assertSame($this->registry()->getManager('dashboard_menu'), $this->registry()->getManagerForClass(Menu::class));
    }

    public function testUnknownManagerFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('nowo_dashboard_menu.doctrine.entity_manager is "reports"');

        $this->boot($this->dedicatedDoctrine(), ['doctrine' => ['entity_manager' => 'reports']]);
    }

    /**
     * Kept-alive kernel, no services resetter: only the bundle's closed manager is reset; the host keeps its entities.
     */
    public function testRecoveryResetsOnlyTheBundleManager(): void
    {
        $this->boot($this->dedicatedDoctrine(), ['doctrine' => ['entity_manager' => 'dashboard_menu']]);
        [$host, $bundle] = $this->managersWithSchema();
        $note            = new HostNote('managed by the host');
        $host->persist($note);
        $host->flush();

        $bundle->close();
        $resolver = $this->container()->get(DashboardMenuEntityManagerResolver::class);
        self::assertInstanceOf(DashboardMenuEntityManagerResolver::class, $resolver);
        $resolver->resetIfClosed();

        self::assertTrue($bundle->isOpen());
        self::assertTrue($host->contains($note));
    }

    /**
     * @return array{EntityManagerInterface, EntityManagerInterface}
     */
    private function managersWithSchema(): array
    {
        $host   = $this->registry()->getManager('default');
        $bundle = $this->registry()->getManager('dashboard_menu');
        self::assertInstanceOf(EntityManagerInterface::class, $host);
        self::assertInstanceOf(EntityManagerInterface::class, $bundle);
        (new SchemaTool($host))->createSchema($host->getMetadataFactory()->getAllMetadata());
        (new SchemaTool($bundle))->createSchema($bundle->getMetadataFactory()->getAllMetadata());

        return [$host, $bundle];
    }

    /**
     * @param array<string, mixed> $doctrine
     * @param array<string, mixed> $menuConfig
     */
    private function boot(array $doctrine, array $menuConfig = [], bool $hostDefaultDriver = false): void
    {
        $this->kernel = new EntityManagerTestKernel($doctrine, $menuConfig, $hostDefaultDriver);
        $this->kernel->boot();
    }

    private function container(): ContainerInterface
    {
        self::assertInstanceOf(EntityManagerTestKernel::class, $this->kernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function registry(): ManagerRegistry
    {
        $registry = $this->container()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        return $registry;
    }

    /**
     * @return array<string, mixed>
     */
    private function dedicatedDoctrine(string $hostManager = 'default', bool $withDefaultName = true): array
    {
        $orm = ['entity_managers' => [
            $hostManager => [
                'connection'      => 'default',
                'auto_mapping'    => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'mappings'        => ['HostApp' => [
                    'type'      => 'attribute',
                    'is_bundle' => false,
                    'dir'       => __DIR__ . '/Entity',
                    'prefix'    => 'Nowo\\DashboardMenuBundle\\Tests\\Integration\\EntityManager\\Entity',
                ]],
            ],
            // The Flex default naming strategy (the bundle's raw read path selects snake_case columns).
            'dashboard_menu' => ['connection' => 'dashboard_menu', 'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware'],
        ]];
        if ($withDefaultName) {
            $orm = ['default_entity_manager' => $hostManager] + $orm;
        }

        return [
            'dbal' => [
                'default_connection' => 'default',
                'connections'        => [
                    'default'        => ['url' => 'sqlite:///' . $this->database('host')],
                    'dashboard_menu' => ['url' => 'sqlite:///' . $this->database('bundle')],
                ],
            ],
            'orm' => $orm,
        ];
    }

    private function database(string $name): string
    {
        $file = sprintf('%s/nowo_dashboard_menu_em_%s_%d.sqlite', sys_get_temp_dir(), $name, (int) getmypid());
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;

        return $file;
    }
}
