<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\DashboardMenuBundle\Service\MenuImporter;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function array_map;
use function getmypid;
use function is_file;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * REQ-ARCH-001 rule 3, old vs new: the same host policy written against the deprecated ports (entity, Request,
 * `mixed` context; standard host) and against the value-object ports (DDD host with a dedicated entity manager) gives
 * the same rendered tree: same visible items (unknown permission keys denied), same hrefs, same current flags, same
 * resolved menu code, same dynamic children.
 */
final class HostPortsEquivalenceKernelTest extends TestCase
{
    private ?HostPortsTestKernel $kernel = null;

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

    /**
     * @return iterable<string, array{0: string, 1: array<string, string>, 2: list<array<string, mixed>>, 3?: mixed}>
     */
    public static function requests(): iterable
    {
        $admin = [
            ['label' => 'Home', 'href' => '/', 'current' => false, 'children' => []],
            ['label' => 'Admin', 'href' => '/admin', 'current' => true, 'children' => []],
            ['label' => 'Reports', 'href' => '/reports', 'current' => false, 'children' => []],
            ['label' => 'Any of reports or unknown', 'href' => '/any', 'current' => false, 'children' => []],
            ['label' => 'Projects', 'href' => '#', 'current' => false, 'children' => ['Projects of acme']],
        ];

        yield 'admin page: keyed items by the policy, unknown keys denied' => ['/admin/users', ['_route' => 'admin_users', 'tenant' => 'acme'], $admin];
        yield 'public page: every keyed item denied, menu code not remapped' => ['/', ['_route' => 'home'], []];
        // An object passed as the context: the legacy checker receives it, the value-object checker an empty context.
        // Neither sees a route, so every keyed item is denied in both.
        yield 'admin page, object as permission context: keyed items denied' => ['/admin/users', ['_route' => 'admin_users', 'tenant' => 'acme'], [$admin[0], $admin[1], $admin[4]], new stdClass()];
    }

    /**
     * @param array<string, string> $attributes
     * @param list<array<string, mixed>> $expected
     */
    #[DataProvider('requests')]
    public function testLegacyAndDddHostsRenderTheSameTree(string $path, array $attributes, array $expected, mixed $permissionContext = null): void
    {
        $legacy  = $this->render(HostPortsTestKernel::LEGACY, $path, $attributes, $permissionContext);
        $context = $this->render(HostPortsTestKernel::CONTEXT, $path, $attributes, $permissionContext);
        $multi   = $this->render(HostPortsTestKernel::MULTI, $path, $attributes, $permissionContext);

        self::assertSame($expected, $legacy);
        self::assertSame($legacy, $context);
        self::assertSame($legacy, $multi, 'a checker implementing both variants is called through the deprecated one');
    }

    /**
     * @param array<string, string> $attributes
     *
     * @return list<array<string, mixed>>
     */
    private function render(string $mode, string $path, array $attributes, mixed $permissionContext = null): array
    {
        $this->kernel?->shutdown();
        $this->kernel = new HostPortsTestKernel($mode, $this->database($mode));
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $manager = $container->get('nowo_dashboard_menu.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $manager);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());

        $checker = match ($mode) {
            HostPortsTestKernel::CONTEXT => ContextHostChecker::class,
            HostPortsTestKernel::MULTI   => MultiVariantHostChecker::class,
            default                      => LegacyHostChecker::class,
        };
        $resolver = $mode === HostPortsTestKernel::CONTEXT ? ContextHostLinkResolver::class : LegacyHostLinkResolver::class;
        $importer = $container->get(MenuImporter::class);
        self::assertInstanceOf(MenuImporter::class, $importer);
        $importer->import(['menu' => ['code' => 'sidebar', 'name' => 'Sidebar', 'permissionChecker' => $checker], 'items' => [
            ['label' => 'Home', 'position' => 0, 'linkType' => 'external', 'externalUrl' => '/'],
            ['label' => 'Admin', 'position' => 1, 'linkType' => 'external', 'externalUrl' => '/admin', 'routeName' => 'admin_index'],
            ['label' => 'Reports', 'position' => 2, 'linkType' => 'external', 'externalUrl' => '/reports', 'permissionKeys' => ['reports']],
            ['label' => 'Unknown key', 'position' => 3, 'linkType' => 'external', 'externalUrl' => '/unknown', 'permissionKeys' => ['unknown.key']],
            ['label' => 'All of reports and unknown', 'position' => 4, 'linkType' => 'external', 'externalUrl' => '/all', 'permissionKeys' => ['reports', 'unknown.key'], 'isUnanimous' => true],
            ['label' => 'Any of reports or unknown', 'position' => 5, 'linkType' => 'external', 'externalUrl' => '/any', 'permissionKeys' => ['reports', 'unknown.key'], 'isUnanimous' => false],
            ['label' => 'Projects', 'position' => 6, 'itemType' => 'service', 'linkResolver' => $resolver],
        ]]);
        // A second menu: what `nav` falls back to when the code resolver does not remap it.
        $importer->import(['menu' => ['code' => 'nav', 'name' => 'Nav', 'permissionChecker' => $checker], 'items' => [
            ['label' => 'Keyed', 'position' => 0, 'linkType' => 'external', 'externalUrl' => '/keyed', 'permissionKeys' => ['reports']],
        ]]);

        $request = Request::create($path);
        $request->attributes->set('_route', $attributes['_route']);
        $request->attributes->set('_route_params', isset($attributes['tenant']) ? ['tenant' => $attributes['tenant']] : []);
        $stack = $container->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $stack);
        $stack->push($request);

        $twig = $container->get(MenuExtension::class);
        self::assertInstanceOf(MenuExtension::class, $twig);

        return array_map(static fn (array $node): array => [
            'label'    => $node['item']->getLabel(),
            'href'     => $node['href'] ?? '#',
            'current'  => $node['isCurrent'],
            'children' => array_map(static fn (array $child): string => $child['item']->getLabel(), $node['children']),
        ], $twig->getMenuTree('nav', $permissionContext));
    }

    private function database(string $mode): string
    {
        $file = sprintf('%s/nowo_dashboard_menu_ports_%s_%d.sqlite', sys_get_temp_dir(), $mode, (int) getmypid());
        if (is_file($file)) {
            unlink($file);
        }
        $this->files[] = $file;

        return $file;
    }
}
