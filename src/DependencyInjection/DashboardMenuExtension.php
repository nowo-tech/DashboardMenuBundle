<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCountMiddleware;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

use function array_key_exists;
use function is_array;

/**
 * Loads bundle configuration and services.
 *
 * Twig views are not registered here (no prepend). They are added at the end of the
 * native loader by TwigPathsPass so that app overrides in templates/bundles/NowoDashboardMenuBundle/
 * are consulted first.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DashboardMenuExtension extends Extension
{
    /**
     * Prepend twig_component.defaults so the bundle's Live Component has a matching namespace
     * (avoids "Could not generate a component name ... no matching namespace found").
     */
    public function prepend(ContainerBuilder $container): void
    {
        if (class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class)) {
            $container->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'Nowo\\DashboardMenuBundle\\LiveComponent\\' => 'components/',
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->setParameter(
            Configuration::ALIAS . '.dashboard.item_form_live_component_enabled',
            class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class),
        );
        $uxAutocompleteAvailable = false;
        if (class_exists(\Symfony\UX\Autocomplete\AutocompleteBundle::class)) {
            try {
                /** @var array<string, mixed> $bundles */
                $bundles                 = $container->getParameter('kernel.bundles');
                $uxAutocompleteAvailable = is_array($bundles) && array_key_exists(\Symfony\UX\Autocomplete\AutocompleteBundle::class, $bundles);
            } catch (Throwable) {
                $uxAutocompleteAvailable = false;
            }
        }
        $container->setParameter(Configuration::ALIAS . '.ux_autocomplete_available', $uxAutocompleteAvailable);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class)) {
            $loader->load('services_live_component.yaml');
        }

        $config         = $this->processConfiguration(new Configuration(), $configs);
        $connectionName = $config['doctrine']['connection'] ?? 'default';

        if ($container->getParameter('kernel.environment') === 'dev') {
            $loader->load('services_dev.yaml');
            $menuExtensionDef = $container->getDefinition(MenuExtension::class);
            $menuExtensionDef->setArgument('$dataCollector', new Reference(DashboardMenuDataCollector::class));
            $menuExtensionDef->setArgument('$menuQueryCounter', new Reference(MenuQueryCounter::class));
            $menuExtensionDef->setArgument('$connection', new Reference('doctrine.dbal.' . $connectionName . '_connection'));
            // Register query-count middleware when DBAL exposes Middleware (3.3+); DBAL 4 has no SQLLogger.
            if (class_exists(\Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware::class)) {
                $container->register(MenuQueryCountMiddleware::class, MenuQueryCountMiddleware::class)
                    ->setArguments([new Reference(MenuQueryCounter::class)])
                    ->addTag('doctrine.middleware', ['connection' => $connectionName, 'priority' => 20]);
            }
        }

        $fullConfig = [
            'project' => $config['project'] ?? null,
        ];

        $container->setParameter(Configuration::ALIAS . '.config', $fullConfig);
        $container->setParameter(Configuration::ALIAS . '.icon_library_prefix_map', $config['icon_library_prefix_map'] ?? ['bootstrap-icons' => 'bi']);
        $container->register(MenuIconNameResolver::class, MenuIconNameResolver::class)
            ->setArguments(['%' . Configuration::ALIAS . '.icon_library_prefix_map%'])
            ->setPublic(false);
        $locales       = $config['locales'] ?? [];
        $locales       = is_array($locales) ? $locales : [];
        $defaultLocale = $config['default_locale'] ?? null;
        $container->setParameter(Configuration::ALIAS . '.locales', $locales);
        $container->setParameter(Configuration::ALIAS . '.default_locale', $defaultLocale);
        $container->setParameter(Configuration::ALIAS . '.default_locale_resolved', $defaultLocale ?? ($locales[0] ?? 'en'));
        $container->setParameter(Configuration::ALIAS . '.permission_checker_choices', $config['permission_checker_choices'] ?? []);

        $container->register(MenuLocaleResolver::class, MenuLocaleResolver::class)
            ->setArguments([
                '%' . Configuration::ALIAS . '.locales%',
                '%' . Configuration::ALIAS . '.default_locale%',
            ])
            ->setPublic(false);

        $container->setParameter(Configuration::ALIAS . '.api.enabled', $config['api']['enabled']);
        $container->setParameter(Configuration::ALIAS . '.api.path_prefix', $config['api']['path_prefix']);
        $container->setParameter(Configuration::ALIAS . '.dashboard.enabled', $config['dashboard']['enabled'] ?? false);
        $container->setParameter(Configuration::ALIAS . '.dashboard.layout_template', $config['dashboard']['layout_template'] ?? '@NowoDashboardMenuBundle/dashboard/layout.html.twig');
        $container->setParameter(Configuration::ALIAS . '.dashboard.path_prefix', $config['dashboard']['path_prefix'] ?? '/admin/menus');
        $container->setParameter(Configuration::ALIAS . '.dashboard.route_name_exclude_patterns', $config['dashboard']['route_name_exclude_patterns'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.pagination.enabled', $config['dashboard']['pagination']['enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.dashboard.pagination.per_page', $config['dashboard']['pagination']['per_page'] ?? 20);
        $container->setParameter(Configuration::ALIAS . '.dashboard.id_options', $config['dashboard']['id_options'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.css_class_options', $config['dashboard']['css_class_options'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.modals', $config['dashboard']['modals'] ?? [
            'menu_form' => 'normal',
            'copy'      => 'normal',
            'item_form' => 'lg',
            'delete'    => 'normal',
        ]);
        $container->setParameter(Configuration::ALIAS . '.dashboard.icon_selector_script_url', $config['dashboard']['icon_selector_script_url'] ?? null);
        $dashboardConfig = $config['dashboard'] ?? [];
        $stimulusUrl     = $dashboardConfig['stimulus_script_url'] ?? null;
        $liveEnabled     = class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class);
        // If UX LiveComponent is available and the user didn't configure a custom stimulus URL,
        // fall back to the bundle default that exposes window.Stimulus.
        if ($stimulusUrl === null && $liveEnabled) {
            $stimulusUrl = 'bundles/nowodashboardmenu/js/stimulus-live.js';
        }
        $container->setParameter(Configuration::ALIAS . '.dashboard.stimulus_script_url', $stimulusUrl);
        $container->setParameter(Configuration::ALIAS . '.dashboard.import_max_bytes', $config['dashboard']['import_max_bytes'] ?? 2097152);
        $container->setParameter(Configuration::ALIAS . '.dashboard.required_role', $config['dashboard']['required_role'] ?? null);
        $rateLimitConfig   = $config['dashboard']['import_export_rate_limit'] ?? false;
        $rateLimitLimit    = is_array($rateLimitConfig) ? ($rateLimitConfig['limit'] ?? 10) : 0;
        $rateLimitInterval = is_array($rateLimitConfig) ? ($rateLimitConfig['interval'] ?? 60) : 60;
        $container->setParameter(Configuration::ALIAS . '.dashboard.import_export_rate_limit_limit', $rateLimitLimit);
        $container->setParameter(Configuration::ALIAS . '.dashboard.import_export_rate_limit_interval', $rateLimitInterval);
        $container->setParameter(Configuration::ALIAS . '.dashboard.permission_key_choices', $config['dashboard']['permission_key_choices'] ?? []);
        $cacheConfig = $config['cache'] ?? ['ttl' => 60, 'pool' => 'cache.app'];
        $container->setParameter(Configuration::ALIAS . '.cache.ttl', $cacheConfig['ttl'] ?? 60);
        $container->setParameter(Configuration::ALIAS . '.cache.pool', $cacheConfig['pool'] ?? 'cache.app');
        $menuTreeLoaderDef = $container->getDefinition(MenuTreeLoader::class);
        $menuTreeLoaderDef->setArgument('$cacheTtl', $container->getParameter(Configuration::ALIAS . '.cache.ttl'));
        $poolName = $cacheConfig['pool'] ?? null;
        if ($poolName !== null && $poolName !== '') {
            $menuTreeLoaderDef->setArgument('$cachePool', new Reference($poolName));
        }
        $container->setParameter(Configuration::ALIAS . '.doctrine.connection', $config['doctrine']['connection'] ?? 'default');
        $container->setParameter(Configuration::ALIAS . '.doctrine.table_prefix', $config['doctrine']['table_prefix'] ?? '');
        $container->setParameter(Configuration::ALIAS . '.table_prefix', $config['doctrine']['table_prefix'] ?? '');

        $container->register(MenuConfigResolver::class, MenuConfigResolver::class)
            ->setArguments([
                '%' . Configuration::ALIAS . '.config%',
                new Reference(MenuRepository::class),
                '%' . Configuration::ALIAS . '.doctrine.connection%',
                '%' . Configuration::ALIAS . '.doctrine.table_prefix%',
            ])
            ->setPublic(false);

        $container->register(DefaultMenuCodeResolver::class, DefaultMenuCodeResolver::class)
            ->setPublic(false);
        $container->setAlias(MenuCodeResolverInterface::class, DefaultMenuCodeResolver::class)
            ->setPublic(false);

        $requiredRole = $config['dashboard']['required_role'] ?? null;
        if ($requiredRole !== null && $requiredRole !== '') {
            $container->register(DashboardAccessSubscriber::class, DashboardAccessSubscriber::class)
                ->setArguments([
                    $requiredRole,
                    new Reference('security.authorization_checker'),
                ])
                ->addTag('kernel.event_subscriber');
        }

        $cachePoolName = $cacheConfig['pool'] ?? 'cache.app';
        $container->register(ImportExportRateLimiter::class, ImportExportRateLimiter::class)
            ->setArguments([
                new Reference($cachePoolName),
                '%' . Configuration::ALIAS . '.dashboard.import_export_rate_limit_limit%',
                '%' . Configuration::ALIAS . '.dashboard.import_export_rate_limit_interval%',
            ])
            ->setPublic(false);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
