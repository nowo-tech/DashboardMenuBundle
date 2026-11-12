<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Worker;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function dirname;
use function getmypid;
use function hash;
use function serialize;
use function sys_get_temp_dir;

/**
 * Kept-alive host kernel of the worker tests (2.21.0): the `http_kernel` service is driven directly, the services
 * resetter never runs. `dedicated` maps the menu entities on their own `dashboard_menu` manager; `standard` keeps them
 * on the host's default manager; `default-named` sets `doctrine.entity_manager: default` (the host default).
 */
final class WorkerTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const DEDICATED     = 'dedicated';
    public const STANDARD      = 'standard';
    public const DEFAULT_NAMED = 'default-named';

    public function __construct(
        private readonly string $mode,
        private readonly string $directory,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoDashboardMenuBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-dashboard-menu-worker/' . getmypid() . '/' . hash('xxh128', serialize([$this->mode, $this->directory])) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir() . '/../log';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('worker_menu', '/w/menu')->controller([WorkerMenuController::class, 'menu']);
        $routes->add('worker_boom', '/w/boom')->controller([WorkerMenuController::class, 'boom']);
    }

    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            'cache'                => ['app' => 'cache.adapter.array'],
        ]);
        $naming  = 'doctrine.orm.naming_strategy.underscore_number_aware';
        $hostApp = ['HostApp' => [
            'type'      => 'attribute',
            'is_bundle' => false,
            'dir'       => __DIR__ . '/../EntityManager/Entity',
            'prefix'    => 'Nowo\\DashboardMenuBundle\\Tests\\Integration\\EntityManager\\Entity',
        ]];
        if ($this->mode === self::STANDARD) {
            $container->loadFromExtension('doctrine', [
                'dbal' => ['url' => 'sqlite:///' . $this->directory . '/host.sqlite'],
                'orm'  => ['auto_mapping' => true, 'naming_strategy' => $naming, 'mappings' => $hostApp],
            ]);
            $container->loadFromExtension('nowo_dashboard_menu', ['locales' => ['en']]);
        } else {
            $managers = ['default' => ['connection' => 'default', 'naming_strategy' => $naming, 'mappings' => $hostApp]];
            if ($this->mode === self::DEDICATED) {
                $managers['dashboard_menu'] = ['connection' => 'dashboard_menu', 'naming_strategy' => $naming];
            }
            $container->loadFromExtension('doctrine', [
                'dbal' => ['default_connection' => 'default', 'connections' => [
                    'default'        => ['url' => 'sqlite:///' . $this->directory . '/host.sqlite'],
                    'dashboard_menu' => ['url' => 'sqlite:///' . $this->directory . '/menu.sqlite'],
                ]],
                'orm' => ['default_entity_manager' => 'default', 'entity_managers' => $managers],
            ]);
            $container->loadFromExtension('nowo_dashboard_menu', [
                'locales'  => ['en'],
                'doctrine' => ['entity_manager' => $this->mode === self::DEDICATED ? 'dashboard_menu' : 'default'],
            ]);
        }

        $container->register(WorkerUserChecker::class, WorkerUserChecker::class)->setAutoconfigured(true)->setPublic(true);
        $container->register(WorkerMenuController::class, WorkerMenuController::class)
            ->setArguments([new Reference(MenuExtension::class), new Reference(MenuRepository::class)])
            ->setPublic(true)
            ->addTag('controller.service_arguments');
    }
}
