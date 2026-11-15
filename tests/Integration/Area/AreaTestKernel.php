<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Area;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

use function dirname;
use function getmypid;
use function hash;
use function sys_get_temp_dir;

/**
 * Host kernel of the area / hub tests (2.22): the Beacon split (dashboard, preferences, organization, development) as
 * `nowo_dashboard_menu.areas`, a request-attribute authorization checker, one badge provider and one extras provider.
 */
final class AreaTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        private readonly string $directory,
        private readonly bool $fallbackWhenDenied = false,
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
        return sys_get_temp_dir() . '/nowo-dashboard-menu-area/' . getmypid() . '/' . hash('xxh128', $this->directory . ($this->fallbackWhenDenied ? 'f' : '')) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir() . '/../log';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        foreach ([
            'dashboard_home'     => '/',
            'admin_hub'          => '/developer',
            'admin_storage'      => '/developer/storage',
            'admin_storage_test' => '/developer/storage/test',
            'admin_appearance'   => '/developer/appearance',
            'admin_mail_log'     => '/developer/mail-log',
            'organization_hub'   => '/organization',
            'admin_users'        => '/organization/users',
            'admin_users_show'   => '/organization/users/{id}',
            'account_profile'    => '/account',
            'nowo_kit_page'      => '/kit/page',
        ] as $name => $path) {
            $routes->add($name, $path);
        }
    }

    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'translator'           => ['enabled' => true, 'fallbacks' => ['en']],
            'router'               => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            'cache'                => ['app' => 'cache.adapter.array'],
        ]);
        $container->loadFromExtension('doctrine', [
            'dbal' => ['url' => 'sqlite:///' . $this->directory . '/menu.sqlite'],
            'orm'  => ['auto_mapping' => true, 'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware'],
        ]);
        $container->loadFromExtension('nowo_dashboard_menu', [
            'locales'                   => ['en'],
            'area_default'              => 'dashboard',
            'area_fallback_when_denied' => $this->fallbackWhenDenied,
            'areas'                     => [
                'dashboard'    => ['label' => 'nav.dashboard', 'home_route' => 'dashboard_home'],
                'preferences'  => ['label' => 'nav.preferences', 'home_route' => 'account_profile', 'path_prefixes' => ['/account']],
                'organization' => [
                    'label'          => 'nav.organization',
                    'roles'          => ['ROLE_MANAGER'],
                    'home_route'     => 'organization_hub',
                    'path_prefixes'  => ['/organization'],
                    'current_routes' => ['admin_users' => ['admin_users_']],
                ],
                'development' => [
                    'menu'           => 'administration',
                    'label'          => 'nav.admin',
                    'icon'           => 'tabler:shield-cog',
                    'roles'          => ['ROLE_DEVELOPER'],
                    'home_route'     => 'admin_hub',
                    'path_prefixes'  => ['/developer'],
                    'route_prefixes' => ['nowo_kit_'],
                    'current_routes' => ['admin_storage' => ['admin_storage']],
                ],
            ],
            'hub' => [
                'testid_prefix' => 'admin-',
                'cards'         => [
                    'admin_storage'  => ['intro' => 'Buckets for uploads', 'testid' => 'admin-storage', 'tour' => 'admin-storage'],
                    'admin_mail_log' => ['badge' => 'mail_failed'],
                ],
            ],
        ]);

        $container->register('security.authorization_checker', AreaTestAuthorizationChecker::class)
            ->setAutowired(true);
        $container->register(AreaTestBadgeProvider::class)->setAutoconfigured(true);
        $container->register(AreaTestExtrasProvider::class)->setAutoconfigured(true);
    }
}
