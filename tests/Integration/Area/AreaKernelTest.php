<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Area;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DashboardMenuBundle\Model\MenuArea;
use Nowo\DashboardMenuBundle\Model\MenuHubSection;
use Nowo\DashboardMenuBundle\Service\MenuAreaResolver;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\DashboardMenuBundle\Twig\MenuAreaExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

use function array_map;
use function dirname;
use function gc_collect_cycles;
use function getmypid;
use function memory_get_usage;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Areas, hub pages and configured current-item rules through a booted host kernel (2.22), including the
 * FrankenPHP worker shape: one kept-alive kernel serves alternating requests without any reset.
 */
final class AreaKernelTest extends TestCase
{
    private const ITERATIONS = 1000;

    private const MEMORY_MARGIN = 4 * 1024 * 1024;

    private ?AreaTestKernel $kernel = null;

    private string $directory = '';

    protected function tearDown(): void
    {
        $directories = [$this->directory];
        if ($this->kernel instanceof AreaTestKernel) {
            $directories[] = dirname($this->kernel->getCacheDir());
            $this->kernel->shutdown();
        }
        $this->kernel = null;
        (new Filesystem())->remove($directories);
    }

    public function testHubRendersTheAreaMenuWithExtrasBadgesAndCurrentCard(): void
    {
        $container = $this->boot();
        $this->push($container, '/developer/storage/test', 'admin_storage_test', 'ROLE_DEVELOPER');

        $html    = $this->twig($container)->render('@NowoDashboardMenuBundle/hub.html.twig', ['menu_code' => 'administration', 'testid' => 'admin-hub-sections']);
        $crawler = new Crawler($html);

        self::assertCount(1, $crawler->filterXPath('//*[@data-testid="admin-hub-sections"]'));
        self::assertSame(['Settings', 'Observability'], $crawler->filterXPath('//*[@data-hub-section]')->each(static fn (Crawler $n): string => (string) $n->attr('data-hub-section')));
        self::assertSame(['Email'], $crawler->filterXPath('//*[@data-hub-group]')->each(static fn (Crawler $n): string => (string) $n->attr('data-hub-group')));
        // The hub itself (admin_hub, the area home route) is never a card.
        self::assertSame(['admin_storage', 'admin_appearance', 'admin_mail_log'], $crawler->filterXPath('//*[@data-hub-route]')->each(static fn (Crawler $n): string => (string) $n->attr('data-hub-route')));

        $storage = $crawler->filterXPath('//*[@data-testid="admin-storage"]');
        self::assertSame('/developer/storage', $storage->attr('href'));
        self::assertSame('admin-storage', $storage->attr('data-tour'));
        self::assertSame('page', $storage->attr('aria-current'));
        self::assertStringContainsString('is-current', (string) $storage->attr('class'));
        self::assertStringContainsString('Buckets for uploads', $storage->text());

        $appearance = $crawler->filterXPath('//*[@data-testid="admin-admin-appearance"]');
        self::assertStringContainsString('Brand and colours', $appearance->text());
        self::assertCount(0, $crawler->filterXPath('//*[@data-testid="admin-admin-appearance-badge"]'));
        self::assertNull($appearance->attr('aria-current'));

        self::assertSame('3', $crawler->filterXPath('//*[@data-testid="admin-admin-mail-log-badge"]')->text());
        self::assertSame('_blank', $crawler->filterXPath('//*[@data-testid="admin-admin-mail-log"]')->attr('target'));
    }

    public function testSidebarKeepsTheConfiguredLinkCurrentOnNestedRoutes(): void
    {
        $container = $this->boot();
        $this->push($container, '/organization/users/7', 'admin_users_show', 'ROLE_MANAGER');

        $tree = $this->extension($container)->hub('organization');
        self::assertCount(1, $tree);
        self::assertTrue($tree[0]->blocks[0]->cards[0]->current);
        self::assertSame(0, $this->extension($container)->badge($tree[0]->blocks[0]->cards[0]));
        self::assertSame('organization', $this->extension($container)->currentArea()?->name);

        // The same route prefix gives no current link in another area's menu.
        $this->push($container, '/developer/other', 'admin_users_show', 'ROLE_MANAGER');
        $dev = $this->extension($container)->hub('administration');
        self::assertFalse($dev[0]->blocks[0]->cards[0]->current);
    }

    public function testAreaSwitcherFunctions(): void
    {
        $container = $this->boot();
        $twig      = $this->twig($container);
        $template  = $twig->createTemplate('{{ dashboard_menu_area().name }}|{% for a in dashboard_menu_areas() %}{{ a.name }},{% endfor %}|{% for a in dashboard_menu_areas(false) %}{{ a.name }},{% endfor %}|{{ dashboard_menu_area_granted("development") ? "y" : "n" }}');

        $this->push($container, '/en/developer/appearance', 'admin_appearance', 'ROLE_MANAGER');
        self::assertSame('development|dashboard,preferences,organization,|dashboard,preferences,organization,development,|n', $template->render());

        $this->push($container, '/kit/page', 'nowo_kit_page', 'ROLE_DEVELOPER,ROLE_MANAGER');
        self::assertSame('development|dashboard,preferences,organization,development,|dashboard,preferences,organization,development,|y', $template->render());

        $this->push($container, '/account', 'account_profile', '');
        self::assertSame('preferences|dashboard,preferences,|dashboard,preferences,organization,development,|n', $template->render());

        $this->push($container, '/nowhere', null, '');
        self::assertSame('dashboard', $this->extension($container)->currentArea()?->name);
    }

    /** The USAGE sidebar snippet: the area menu is rendered only when the area is granted. */
    public function testSidebarSnippetGuardsWithAreaGranted(): void
    {
        $container = $this->boot();
        $template  = $this->twig($container)->createTemplate('{% set area = dashboard_menu_area() %}{% if area and dashboard_menu_area_granted(area) %}sidebar:{{ area.menu }}{% else %}none{% endif %}');

        $this->push($container, '/developer/storage', 'admin_storage', 'ROLE_MANAGER');
        self::assertSame('none', $template->render());
        $this->push($container, '/developer/storage', 'admin_storage', 'ROLE_DEVELOPER');
        self::assertSame('sidebar:administration', $template->render());
    }

    public function testAreaFallbackWhenDeniedOption(): void
    {
        $container = $this->boot(true);

        // The user is not a developer: the 403 page of /developer renders in the default area, not the denied one.
        $this->push($container, '/developer/storage', 'admin_storage', 'ROLE_MANAGER');
        self::assertSame('dashboard', $this->extension($container)->currentArea()?->name);
        $this->push($container, '/developer/storage', 'admin_storage', 'ROLE_DEVELOPER');
        self::assertSame('development', $this->extension($container)->currentArea()?->name);
    }

    public function testOutsideARequestTheDefaultAreaApplies(): void
    {
        $container = $this->boot();

        self::assertSame('dashboard', $this->extension($container)->currentArea()?->name);
        self::assertSame(['dashboard', 'preferences'], array_map(static fn (MenuArea $a): string => $a->name, $this->extension($container)->areaList()));
    }

    /**
     * REQ-RUNTIME-002: one kept-alive container, two users in two areas alternating; each request sees only its own
     * area, current card and grants, and memory stays flat (nothing is memoised between requests).
     */
    public function testWorkerAlternatingRequestsKeepNoStateAndFlatMemory(): void
    {
        $container = $this->boot();
        $extension = $this->extension($container);
        $resolver  = $container->get(MenuAreaResolver::class);
        self::assertInstanceOf(MenuAreaResolver::class, $resolver);
        $baseline = null;
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $developer = $i % 2 === 0;
            $this->push($container, $developer ? '/developer/storage/test' : '/organization/users/3', $developer ? 'admin_storage_test' : 'admin_users_show', $developer ? 'ROLE_DEVELOPER' : 'ROLE_MANAGER');

            self::assertSame($developer ? 'development' : 'organization', $extension->currentArea()?->name);
            self::assertSame($developer, $resolver->isGranted('development'));
            self::assertSame(!$developer, $resolver->isGranted('organization'));
            $hub = $extension->hub($developer ? 'administration' : 'organization');
            self::assertContainsOnlyInstancesOf(MenuHubSection::class, $hub);
            self::assertTrue($hub[0]->blocks[0]->cards[0]->current);

            if ($i === 100) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertNotNull($baseline);
        self::assertLessThan($baseline + self::MEMORY_MARGIN, memory_get_usage());
    }

    private function boot(bool $fallbackWhenDenied = false): ContainerInterface
    {
        $this->directory = sys_get_temp_dir() . '/nowo-dashboard-menu-area-db/' . getmypid() . '/' . uniqid('', true);
        (new Filesystem())->mkdir($this->directory);
        $this->kernel = new AreaTestKernel($this->directory, $fallbackWhenDenied);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $manager = $container->get('nowo_dashboard_menu.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $importer = $container->get(MenuImporter::class);
        self::assertInstanceOf(MenuImporter::class, $importer);
        $importer->import(['menu' => ['code' => 'administration', 'name' => 'Development'], 'items' => [
            ['label' => 'Settings', 'position' => 0, 'itemType' => 'section', 'children' => [
                ['label' => 'Overview', 'position' => 0, 'routeName' => 'admin_hub'],
                ['label' => 'Storage', 'position' => 1, 'routeName' => 'admin_storage'],
                ['label' => 'Appearance', 'position' => 2, 'routeName' => 'admin_appearance'],
            ]],
            ['label' => 'Observability', 'position' => 1, 'itemType' => 'section', 'children' => [
                ['label' => 'Email', 'position' => 0, 'itemType' => 'section', 'children' => [
                    ['label' => 'Mail log', 'position' => 0, 'routeName' => 'admin_mail_log', 'targetBlank' => true],
                ]],
            ]],
        ]]);
        $importer->import(['menu' => ['code' => 'organization', 'name' => 'Organization'], 'items' => [
            ['label' => 'People', 'position' => 0, 'itemType' => 'section', 'children' => [
                ['label' => 'Users', 'position' => 0, 'routeName' => 'admin_users'],
            ]],
        ]]);
        $manager->clear();

        return $container;
    }

    private function push(ContainerInterface $container, string $path, ?string $route, string $roles): void
    {
        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        while ($stack->pop() instanceof Request) {
            // Empty the stack: one main request per simulated request.
        }
        $request = Request::create($path);
        if ($route !== null) {
            $request->attributes->set('_route', $route);
            $request->attributes->set('_route_params', []);
        }
        $request->headers->set('X-Roles', $roles);
        $stack->push($request);
    }

    private function twig(ContainerInterface $container): Environment
    {
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    private function extension(ContainerInterface $container): MenuAreaExtension
    {
        return $this->twig($container)->getExtension(MenuAreaExtension::class);
    }
}
