<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCountMiddleware;
use Nowo\DashboardMenuBundle\Enum\CssFramework;
use Nowo\DashboardMenuBundle\Enum\IconSet;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\ConfigurableDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuIconNameResolver;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use Nowo\DashboardMenuBundle\Service\MenuTreeCacheInvalidator;
use Nowo\DashboardMenuBundle\Service\MenuTreeLoader;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use Psr\Clock\ClockInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Throwable;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Loads bundle configuration and services.
 *
 * Twig views are registered by TwigPathsPass (app overrides first). This extension
 * prepends the named assets package and optional Live Component defaults.
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class DashboardMenuExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Prepend twig_component.defaults so the bundle's Live Component has a matching namespace
     * (avoids "Could not generate a component name ... no matching namespace found").
     * Also registers the named assets package for serving bundle public files via asset().
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework') && class_exists(Package::class)) {
            $container->prependExtensionConfig('framework', [
                'assets' => [
                    'packages' => [
                        Configuration::ALIAS => [
                            'base_path' => '/bundles/nowodashboardmenu',
                        ],
                    ],
                ],
            ]);
        }

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
                $uxAutocompleteAvailable = array_key_exists(\Symfony\UX\Autocomplete\AutocompleteBundle::class, $bundles);
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
            'project'                    => $config['project'] ?? null,
            'locales'                    => $config['locales'] ?? [],
            'default_locale'             => $config['default_locale'] ?? null,
            'doctrine'                   => $config['doctrine'] ?? [],
            'cache'                      => $config['cache'] ?? [],
            'icon_library_prefix_map'    => $config['icon_library_prefix_map'] ?? [],
            'permission_checker_choices' => $config['permission_checker_choices'] ?? [],
            'menu_link_resolver_choices' => $config['menu_link_resolver_choices'] ?? [],
            'api'                        => $config['api'] ?? [],
            'dashboard'                  => $config['dashboard'] ?? [],
            'security'                   => $config['security'] ?? [],
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
        $container->setParameter(Configuration::ALIAS . '.menu_link_resolver_choices', $config['menu_link_resolver_choices'] ?? []);

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
        $cssFrameworkRaw = (string) ($config['dashboard']['css_framework'] ?? CssFramework::Bootstrap5->value);
        $cssFramework    = CssFramework::from($cssFrameworkRaw)->normalized()->value;
        $container->setParameter(Configuration::ALIAS . '.dashboard.css_framework', $cssFramework);
        $iconSet = IconSet::from((string) ($config['dashboard']['icon_set'] ?? IconSet::BootstrapIcons->value))->value;
        $container->setParameter(Configuration::ALIAS . '.dashboard.icon_set', $iconSet);
        $container->setParameter(Configuration::ALIAS . '.dashboard.route_name_exclude_patterns', $config['dashboard']['route_name_exclude_patterns'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.pagination.enabled', $config['dashboard']['pagination']['enabled'] ?? true);
        $container->setParameter(Configuration::ALIAS . '.dashboard.pagination.per_page', $config['dashboard']['pagination']['per_page'] ?? 20);
        $container->setParameter(Configuration::ALIAS . '.dashboard.id_options', $config['dashboard']['id_options'] ?? []);
        $container->setParameter(
            Configuration::ALIAS . '.dashboard.icon_size',
            $config['dashboard']['icon_size'] ?? '1em',
        );
        $container->setParameter(Configuration::ALIAS . '.dashboard.css_class_options', $config['dashboard']['css_class_options'] ?? []);
        $container->setParameter(
            Configuration::ALIAS . '.dashboard.item_span_active',
            $config['dashboard']['item_span_active'] ?? false,
        );
        // `css_class_options.span` is configured as a list (same format as the other CSS dropdowns).
        // For the front-end wrapper class we use the first non-empty choice.
        $spanOptions   = $config['dashboard']['css_class_options']['span'] ?? [];
        $itemSpanClass = '';
        if (is_array($spanOptions)) {
            foreach ($spanOptions as $choice) {
                if (is_string($choice) && $choice !== '') {
                    $itemSpanClass = $choice;
                    break;
                }
            }
        }
        $container->setParameter(Configuration::ALIAS . '.dashboard.item_span_class', $itemSpanClass);
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
        $container->setParameter(Configuration::ALIAS . '.dashboard.position_step', $config['dashboard']['position_step'] ?? 100);
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
        $poolName            = $cacheConfig['pool'] ?? null;
        $cacheInvalidatorDef = $container->getDefinition(MenuTreeCacheInvalidator::class);
        if ($poolName !== null && $poolName !== '') {
            $menuTreeLoaderDef->setArgument('$cachePool', new Reference($poolName));
            $cacheInvalidatorDef->setArgument('$cachePool', new Reference($poolName));
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

        $security = $this->resolveSecurityConfig($config);
        $container->setParameter(Configuration::ALIAS . '.security.access_roles', $security['access_roles']);
        $container->setParameter(Configuration::ALIAS . '.security.allow_unauthenticated', $security['allow_unauthenticated']);
        $container->setParameter(Configuration::ALIAS . '.security.access_checker', $security['access_checker']);
        // BC parameter for profiler / older docs: first role or null when empty.
        $legacyRole = $security['access_roles'][0] ?? null;
        $container->setParameter(Configuration::ALIAS . '.dashboard.required_role', $legacyRole);
        $this->registerAccessChecker($container, $security);

        if (!$container->has('clock')) {
            $container->register('clock', NativeClock::class);
        }
        if (!$container->hasAlias(ClockInterface::class) && !$container->hasDefinition(ClockInterface::class)) {
            $container->setAlias(ClockInterface::class, 'clock');
        }

        $cachePoolName  = $cacheConfig['pool'] ?? 'cache.app';
        $rateLimiterDef = $container->register(ImportExportRateLimiter::class, ImportExportRateLimiter::class)
            ->setArguments([
                new Reference($cachePoolName),
                '%' . Configuration::ALIAS . '.dashboard.import_export_rate_limit_limit%',
                '%' . Configuration::ALIAS . '.dashboard.import_export_rate_limit_interval%',
                new Reference('clock'),
            ])
            ->setPublic(false);
        if ($container->hasDefinition('logger') || $container->hasAlias('logger')) {
            $rateLimiterDef->setArgument('$logger', new Reference('logger'));
        }
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool}
     */
    private function resolveSecurityConfig(array $config): array
    {
        /** @var array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security */
        $security = $config['security'] ?? [
            'access_checker'        => null,
            'access_roles'          => ['ROLE_ADMIN'],
            'allow_unauthenticated' => false,
        ];

        $legacyRole = $config['dashboard']['required_role'] ?? null;
        // Prefer explicit non-default security.access_roles; otherwise map legacy scalar.
        if (is_string($legacyRole) && $legacyRole !== '' && $security['access_roles'] === ['ROLE_ADMIN']) {
            $security['access_roles'] = [$legacyRole];
        }

        return $security;
    }

    /**
     * @param array{access_checker: ?string, access_roles: list<string>, allow_unauthenticated: bool} $security
     */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        $accessCheckerId = $security['access_checker'] ?? null;
        if (is_string($accessCheckerId) && $accessCheckerId !== '') {
            $container->setAlias(DashboardMenuAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $hasAuthorizationChecker = $container->hasDefinition('security.authorization_checker')
            || $container->hasAlias('security.authorization_checker');

        if ($security['allow_unauthenticated'] && !$hasAuthorizationChecker) {
            $accessCheckerId = 'nowo_dashboard_menu.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllDashboardMenuAccessChecker::class));
            $container->setAlias(DashboardMenuAccessCheckerInterface::class, $accessCheckerId);

            return;
        }

        $accessCheckerId = 'nowo_dashboard_menu.access_checker.default';
        $definition      = new Definition(ConfigurableDashboardMenuAccessChecker::class);
        $definition->setArgument('$accessRoles', $security['access_roles']);
        if ($hasAuthorizationChecker) {
            $definition->setArgument('$authorizationChecker', new Reference('security.authorization_checker'));
        } else {
            $definition->setAutowired(true);
        }
        $container->setDefinition($accessCheckerId, $definition);
        $container->setAlias(DashboardMenuAccessCheckerInterface::class, $accessCheckerId);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }
}
