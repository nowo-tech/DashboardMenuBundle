<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Database;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\TransactionDoctrine\Symfony\NowoTransactionDoctrineBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

use function dirname;
use function getmypid;
use function hash;
use function serialize;
use function sys_get_temp_dir;

/**
 * Host kernel of the real-database concurrency tests (2.23, REQ-RUNTIME-003): the dashboard routes on a MySQL,
 * MariaDB or PostgreSQL server. `standard` maps the menu entities on the host's default manager; `dedicated` on the
 * bundle's own `dashboard_menu` manager (its own connection to the same database). Two kernels on one database are two
 * connections, each running the real bundle code.
 */
final class DatabaseTestKernel extends Kernel
{
    use MicroKernelTrait;

    public const STANDARD  = 'standard';
    public const DEDICATED = 'dedicated';

    /**
     * @param string $instance distinguishes two kernels with the same options (two connections)
     * @param bool $transactionDoctrine registers NowoTransactionDoctrineBundle (request commit watermark)
     * @param array<string, mixed> $conflictResponse nowo_dashboard_menu.conflict_response
     */
    public function __construct(
        private readonly string $databaseUrl,
        private readonly string $mode = self::STANDARD,
        private readonly string $instance = 'a',
        private readonly bool $transactionDoctrine = false,
        private readonly array $conflictResponse = [],
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
        // The item form uses UX Autocomplete's `autocomplete` option when the package is installed.
        yield new StimulusBundle();
        yield new TwigComponentBundle();
        yield new AutocompleteBundle();
        yield new NowoDashboardMenuBundle();
        if ($this->transactionDoctrine) {
            yield new NowoTransactionDoctrineBundle();
        }
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/nowo-dashboard-menu-db/' . getmypid() . '/' . hash('xxh128', serialize([$this->databaseUrl, $this->mode, $this->instance, $this->transactionDoctrine, $this->conflictResponse])) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir() . '/../log';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoDashboardMenuBundle/Resources/config/routes_dashboard.yaml')->prefix('/admin/menus');
    }

    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            'cache'                => ['app' => 'cache.adapter.array'],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'validation'           => true,
        ]);
        $naming = 'doctrine.orm.naming_strategy.underscore_number_aware';
        if ($this->mode === self::STANDARD) {
            $container->loadFromExtension('doctrine', [
                'dbal' => ['url' => $this->databaseUrl],
                'orm'  => ['auto_mapping' => true, 'naming_strategy' => $naming],
            ]);
            $menuConfig = [];
        } else {
            $container->loadFromExtension('doctrine', [
                'dbal' => ['default_connection' => 'default', 'connections' => [
                    'default'        => ['url' => $this->databaseUrl],
                    'dashboard_menu' => ['url' => $this->databaseUrl],
                ]],
                'orm' => ['default_entity_manager' => 'default', 'entity_managers' => [
                    'default'        => ['connection' => 'default', 'naming_strategy' => $naming],
                    'dashboard_menu' => ['connection' => 'dashboard_menu', 'naming_strategy' => $naming],
                ]],
            ]);
            $menuConfig = ['doctrine' => ['entity_manager' => 'dashboard_menu']];
        }

        $container->loadFromExtension('nowo_dashboard_menu', $menuConfig + [
            'locales'           => ['en'],
            'web_ui'            => ['enabled' => true],
            'security'          => ['access_checker' => DatabaseTestAccessChecker::class, 'allow_unauthenticated' => true],
            'conflict_response' => $this->conflictResponse,
        ]);
        if ($this->transactionDoctrine) {
            $container->loadFromExtension('nowo_transaction_doctrine', []);
        }

        $container->register(DatabaseTestAccessChecker::class, DatabaseTestAccessChecker::class)->setPublic(true);
        // Real form CSRF extension, with a token manager that accepts every token (the form is posted directly).
        $container->register('security.csrf.token_manager', AcceptingCsrfTokenManager::class)->setPublic(true);
        $container->register(EarlierCommitListener::class, EarlierCommitListener::class)
            ->setArguments([new Reference('doctrine.dbal.default_connection')])
            ->addTag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'priority' => -64])
            ->setPublic(true);
    }
}
