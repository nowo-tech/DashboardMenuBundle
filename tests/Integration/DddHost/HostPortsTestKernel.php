<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\DddHost;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

use function dirname;
use function getmypid;
use function sys_get_temp_dir;

/**
 * Host kernel wired either with the deprecated ports (`legacy`: entity + Request + `mixed`, the default entity
 * manager) or as a DDD host (`context`: value-object ports only, a dedicated `dashboard_menu` entity manager).
 */
final class HostPortsTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const LEGACY  = 'legacy';
    public const CONTEXT = 'context';

    public function __construct(
        private readonly string $mode,
        private readonly string $database,
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
        return sys_get_temp_dir() . '/nowo-dashboard-menu-ports/' . getmypid() . '/' . $this->mode . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/nowo-dashboard-menu-ports/' . getmypid() . '/' . $this->mode . '/log';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret'               => 'test',
                'test'                 => true,
                'http_method_override' => false,
                'router'               => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            ]);
            $naming = 'doctrine.orm.naming_strategy.underscore_number_aware';
            if ($this->mode === self::CONTEXT) {
                $container->loadFromExtension('doctrine', [
                    'dbal' => ['default_connection' => 'default', 'connections' => [
                        'default'        => ['url' => 'sqlite:///:memory:'],
                        'dashboard_menu' => ['url' => 'sqlite:///' . $this->database],
                    ]],
                    'orm' => ['default_entity_manager' => 'default', 'entity_managers' => [
                        'default'        => ['connection' => 'default', 'naming_strategy' => $naming],
                        'dashboard_menu' => ['connection' => 'dashboard_menu', 'naming_strategy' => $naming],
                    ]],
                ]);
                $container->loadFromExtension('nowo_dashboard_menu', ['locales' => ['en'], 'doctrine' => ['entity_manager' => 'dashboard_menu']]);
                $classes = [ContextHostChecker::class, ContextHostMatcher::class, ContextHostLinkResolver::class, ContextHostCodeResolver::class];
            } else {
                $container->loadFromExtension('doctrine', [
                    'dbal' => ['url' => 'sqlite:///' . $this->database],
                    'orm'  => ['auto_mapping' => true, 'naming_strategy' => $naming],
                ]);
                $container->loadFromExtension('nowo_dashboard_menu', ['locales' => ['en']]);
                $classes = [LegacyHostChecker::class, LegacyHostMatcher::class, LegacyHostLinkResolver::class, LegacyHostCodeResolver::class];
            }
            foreach ($classes as $class) {
                $definition = $container->register($class, $class)->setAutoconfigured(true)->setPublic(true);
                if ($class === ContextHostLinkResolver::class || $class === LegacyHostLinkResolver::class) {
                    // Auto-tagging only covers resolvers registered under an id other than their class.
                    $definition->addTag('nowo_dashboard_menu.menu_link_resolver');
                }
            }
            if ($this->mode === self::CONTEXT) {
                $container->setAlias(MenuCodeContextResolverInterface::class, ContextHostCodeResolver::class);
            } else {
                $container->setAlias(MenuCodeResolverInterface::class, LegacyHostCodeResolver::class);
            }
        });
    }
}
