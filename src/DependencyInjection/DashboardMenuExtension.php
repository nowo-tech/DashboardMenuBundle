<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\DependencyInjection;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCounter;
use Nowo\DashboardMenuBundle\DataCollector\MenuQueryCountMiddleware;
use Nowo\DashboardMenuBundle\Enum\CssFramework;
use Nowo\DashboardMenuBundle\Enum\IconSet;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\ConfigurableDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use Nowo\DashboardMenuBundle\Service\ConfiguredLocales;
use Nowo\DashboardMenuBundle\Service\DefaultMenuCodeResolver;
use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use Nowo\DashboardMenuBundle\Service\MenuCurrentMatcherInterface;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Throwable;

use function array_key_exists;
use function array_reverse;
use function in_array;
use function is_array;
use function is_string;
use function trim;

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
     * Also registers the named assets package for serving bundle public files via asset(),
     * seeds UiKit from the deprecated dashboard css_framework / icon_set when the host set them here only, and
     * bridges the deprecated dashboard.ui chrome hooks into nowo_ui_kit.theme.bundles (REQ-UI-003).
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

        if (class_exists(AsLiveComponent::class)) {
            $container->prependExtensionConfig('twig_component', [
                'defaults' => [
                    'Nowo\\DashboardMenuBundle\\LiveComponent\\' => 'components/',
                ],
            ]);
        }

        $this->prependUiKitDefaults($container);
        $this->prependFormKitDefaults($container);
    }

    /**
     * When FormKit is installed, register the {@code dashboard_menu} profile (labels domain + field classes for the
     * resolved CSS stack: UiKit's {@code css_framework}, else the deprecated {@code dashboard.css_framework}). Forms
     * select it via {@code #[FormKitConfig]}. Does not change the host {@code default_profile}.
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $hostHasCssFramework     = false;
        $hostHasDashboardProfile = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists('dashboard_menu', $profiles)) {
                $hostHasDashboardProfile = true;
            }
        }

        $fw   = $this->resolvedCssFramework($container);
        $seed = [];

        if (!$hostHasCssFramework) {
            // FormKit accepts only bootstrap|tailwind|foundation|none (not bootstrap5 / tabler / …).
            $seed['css_framework'] = match ($fw) {
                CssFramework::Tailwind                   => 'tailwind',
                CssFramework::Foundation                 => 'foundation',
                CssFramework::None, CssFramework::Custom => 'none',
                default                                  => 'bootstrap',
            };
        }

        if (!$hostHasDashboardProfile) {
            $bootstrap        = in_array($fw, [CssFramework::Bootstrap5, CssFramework::Bootstrap4, CssFramework::Tabler], true);
            $seed['profiles'] = [
                'dashboard_menu' => [
                    'alias'              => 'dashboard_menu',
                    'translation_domain' => 'NowoDashboardMenuBundle',
                    // Fields without a {form}.{field}.help / .placeholder translation keep no convention key.
                    'convention_requires_translation' => true,
                    'defaults'                        => [
                        'attr'     => ['class' => $bootstrap ? 'nowo-ui-input form-control' : 'nowo-ui-input'],
                        'row_attr' => ['class' => $bootstrap ? 'mb-2' : ''],
                    ],
                    'field_types' => $bootstrap ? [
                        'checkbox' => [
                            'attr'     => ['class' => 'form-check-input'],
                            'row_attr' => ['class' => 'form-check mb-2'],
                        ],
                        'choice' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'entity' => [
                            'attr' => ['class' => 'form-select'],
                        ],
                        'file' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                        'textarea' => [
                            'attr' => ['class' => 'nowo-ui-input form-control'],
                        ],
                    ] : [
                        'checkbox' => [
                            'attr'     => ['class' => ''],
                            'row_attr' => ['class' => ''],
                        ],
                    ],
                ],
            ];
        }

        if ($seed !== []) {
            $container->prependExtensionConfig('nowo_form_kit', $seed);
        }
    }

    /**
     * When UiKit is installed:
     * - seed nowo_ui_kit.css_framework / icon_set from the deprecated dashboard.css_framework / icon_set when the
     *   host set them here and not under nowo_ui_kit (REQ-UI-003: the CSS stack is inherited from UiKit);
     * - bridge the deprecated dashboard.ui chrome hooks into nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle
     *   (prepended, so the host's own nowo_ui_kit config still wins).
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet      = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            /** @var array<string, mixed> $cfg */
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        $raw = $this->rawDashboardConfig($container);
        // Only the dashboard node is processed here: prepend() sees the raw host config, where other keys may still be
        // "%parameter%" / env placeholders (e.g. locales: '%kernel.enabled_locales%', defined by FrameworkExtension::load()).
        $config   = $this->processConfiguration(new Configuration(), [['dashboard' => $raw]]);
        $defaults = [];

        if (!$hostHasCssFramework && is_string($raw['css_framework'] ?? null)) {
            $defaults['css_framework'] = CssFramework::from($raw['css_framework'])->normalized()->value;
        }
        if (!$hostHasIconSet && is_string($raw['icon_set'] ?? null)) {
            $defaults['icon_set'] = IconSet::from($raw['icon_set'])->value;
        }

        /** @var array<string, mixed> $ui */
        $ui    = is_array($config['dashboard']['ui'] ?? null) ? $config['dashboard']['ui'] : [];
        $theme = LegacyThemeBridge::themeException($ui);
        if ($theme !== []) {
            $defaults['theme'] = ['bundles' => [LegacyThemeBridge::BUNDLE => $theme]];
        }

        if ($defaults !== []) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
    }

    /**
     * CSS stack the dashboard forms are themed for: nowo_ui_kit.css_framework (host config), else the deprecated
     * dashboard.css_framework, else bootstrap5 (UiKit's default).
     */
    private function resolvedCssFramework(ContainerBuilder $container): CssFramework
    {
        foreach (array_reverse($container->getExtensionConfig('nowo_ui_kit')) as $cfg) {
            if (is_string($cfg['css_framework'] ?? null)) {
                return CssFramework::tryFrom($cfg['css_framework'])?->normalized() ?? CssFramework::Bootstrap5;
            }
        }
        $raw = $this->rawDashboardConfig($container);

        return is_string($raw['css_framework'] ?? null)
            ? (CssFramework::tryFrom($raw['css_framework'])?->normalized() ?? CssFramework::Bootstrap5)
            : CssFramework::Bootstrap5;
    }

    /**
     * The dashboard node exactly as the host wrote it (last config wins per key), to tell explicit values from defaults.
     *
     * @return array<string, mixed>
     */
    private function rawDashboardConfig(ContainerBuilder $container): array
    {
        $raw = [];
        foreach ($container->getExtensionConfig(Configuration::ALIAS) as $cfg) {
            if (is_array($cfg['dashboard'] ?? null)) {
                $raw = $cfg['dashboard'] + $raw;
            }
        }

        return $raw;
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(MenuCurrentMatcherInterface::class)
            ->addTag('nowo_dashboard_menu.current_matcher');

        $container->setParameter(
            Configuration::ALIAS . '.dashboard.item_form_live_component_enabled',
            class_exists(AsLiveComponent::class),
        );
        $uxAutocompleteAvailable = false;
        if (class_exists(AutocompleteBundle::class)) {
            try {
                /** @var array<string, mixed> $bundles */
                $bundles                 = $container->getParameter('kernel.bundles');
                $uxAutocompleteAvailable = array_key_exists(AutocompleteBundle::class, $bundles);
            } catch (Throwable) {
                $uxAutocompleteAvailable = false;
            }
        }
        $container->setParameter(Configuration::ALIAS . '.ux_autocomplete_available', $uxAutocompleteAvailable);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        if (class_exists(AsLiveComponent::class)) {
            $loader->load('services_live_component.yaml');
        }

        $config         = $this->processConfiguration(new Configuration(), $configs);
        $connectionName = $config['doctrine']['connection'] ?? 'default';

        if ($container->getParameter('kernel.environment') === 'dev') {
            $loader->load('services_dev.yaml');
            $menuExtensionDef = $container->getDefinition(MenuExtension::class);
            // Optional: DataCollectorProfilerPass removes the collector when the profiler is disabled.
            $menuExtensionDef->setArgument('$dataCollector', new Reference(DashboardMenuDataCollector::class, ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $menuExtensionDef->setArgument('$menuQueryCounter', new Reference(MenuQueryCounter::class));
            $menuExtensionDef->setArgument('$connection', new Reference('doctrine.dbal.' . $connectionName . '_connection'));
            // Register query-count middleware when DBAL exposes Middleware (3.3+); DBAL 4 has no SQLLogger.
            if (class_exists(AbstractDriverMiddleware::class)) {
                $container->register(MenuQueryCountMiddleware::class, MenuQueryCountMiddleware::class)
                    ->setArguments([new Reference(MenuQueryCounter::class)])
                    ->addTag('doctrine.middleware', ['connection' => $connectionName, 'priority' => 20]);
            }
        }

        // locales may be a parameter (%app.locales%, %kernel.enabled_locales%): the kernel resolves it before load(),
        // a direct load() caller may not. An env var (%env(json:APP_LOCALES)%) stays a placeholder until runtime.
        $locales  = $container->getParameterBag()->resolveValue($config['locales'] ?? []);
        $usedEnvs = [];
        $container->resolveEnvPlaceholders($locales, null, $usedEnvs);
        $runtimeLocales = $usedEnvs !== [];
        if (!$runtimeLocales) {
            $locales = ConfiguredLocales::normalize($locales);
        }

        $fullConfig = [
            'project'                    => $config['project'] ?? null,
            'locales'                    => $locales,
            'default_locale'             => $config['default_locale'] ?? null,
            'doctrine'                   => $config['doctrine'] ?? [],
            'cache'                      => $config['cache'] ?? [],
            'icon_library_prefix_map'    => $config['icon_library_prefix_map'] ?? [],
            'permission_checker_choices' => $config['permission_checker_choices'] ?? [],
            'menu_link_resolver_choices' => $config['menu_link_resolver_choices'] ?? [],
            'api'                        => $config['api'] ?? [],
            'dashboard'                  => $config['dashboard'] ?? [],
            'web_ui'                     => $config['web_ui'] ?? [],
            'security'                   => $config['security'] ?? [],
        ];

        $container->setParameter(Configuration::ALIAS . '.config', $fullConfig);
        $container->setParameter(Configuration::ALIAS . '.icon_library_prefix_map', $config['icon_library_prefix_map'] ?? ['bootstrap-icons' => 'bi']);
        $container->register(MenuIconNameResolver::class, MenuIconNameResolver::class)
            ->setArguments(['%' . Configuration::ALIAS . '.icon_library_prefix_map%'])
            ->setPublic(false);
        $defaultLocale = is_string($config['default_locale'] ?? null) ? $config['default_locale'] : null;
        $container->setParameter(Configuration::ALIAS . '.locales', $locales);
        $container->setParameter(Configuration::ALIAS . '.default_locale', $defaultLocale);
        // With runtime (env) locales and no default_locale this holds the fallback; services get the first env locale
        // from a factory (wireRuntimeLocales()).
        $container->setParameter(Configuration::ALIAS . '.default_locale_resolved', ConfiguredLocales::defaultLocale($runtimeLocales ? [] : $locales, $defaultLocale));
        $container->setParameter(Configuration::ALIAS . '.permission_checker_choices', $config['permission_checker_choices'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.menu_link_resolver_choices', $config['menu_link_resolver_choices'] ?? []);

        $container->register(MenuLocaleResolver::class, MenuLocaleResolver::class)
            ->setArguments([
                '%' . Configuration::ALIAS . '.locales%',
                '%' . Configuration::ALIAS . '.default_locale%',
            ])
            ->setPublic(false);

        $container->setParameter(Configuration::ALIAS . '.menu_collapse', $config['menu_collapse'] ?? [
            'enabled'     => false,
            'mode'        => 'auto',
            'storage_key' => 'nowo.dashboardMenu.navCollapse',
        ]);
        $container->setParameter(Configuration::ALIAS . '.api.enabled', $config['api']['enabled']);
        $container->setParameter(Configuration::ALIAS . '.api.path_prefix', $config['api']['path_prefix']);
        $webUi = $config['web_ui'] ?? [];
        $container->setParameter(Configuration::ALIAS . '.web_ui.enabled', (bool) ($webUi['enabled'] ?? false));
        $container->setParameter(Configuration::ALIAS . '.web_ui.path_prefix', $webUi['path_prefix'] ?? '/admin/menus');
        $container->setParameter(Configuration::ALIAS . '.web_ui.layout_template', $webUi['layout_template'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.web_ui.templates', $webUi['templates'] ?? []);
        $indexIntro = $webUi['index']['intro'] ?? null;
        $container->setParameter(Configuration::ALIAS . '.web_ui.index.intro', is_string($indexIntro) && trim($indexIntro) !== '' ? $indexIntro : null);
        $container->setParameter(Configuration::ALIAS . '.web_ui.index.intro_domain', $webUi['index']['intro_domain'] ?? NowoDashboardMenuBundle::TRANSLATION_DOMAIN);
        // 1.x parameter names (kept for hosts / compiler passes reading them).
        $container->setParameter(Configuration::ALIAS . '.dashboard.enabled', (bool) ($webUi['enabled'] ?? false));
        $container->setParameter(Configuration::ALIAS . '.dashboard.layout_template', $webUi['layout_template'] ?? null);
        $container->setParameter(Configuration::ALIAS . '.dashboard.path_prefix', $webUi['path_prefix'] ?? '/admin/menus');
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
        $container->setParameter(
            Configuration::ALIAS . '.dashboard.autocomplete_bootstrap',
            (bool) ($config['dashboard']['autocomplete_bootstrap'] ?? true),
        );
        $container->setParameter(Configuration::ALIAS . '.dashboard.css_class_options', $config['dashboard']['css_class_options'] ?? []);
        $container->setParameter(
            Configuration::ALIAS . '.dashboard.item_span_active',
            $config['dashboard']['item_span_active'] ?? false,
        );
        $container->setParameter(Configuration::ALIAS . '.dashboard.menu_render', [
            // Defaults come from the config tree (Configuration::dashboard).
            'nested_group_display'  => $config['dashboard']['nested_group_display'],
            'group_children_marker' => $config['dashboard']['group_children_marker'],
            'toggle_icon'           => $config['dashboard']['toggle_icon'],
            'group_classes'         => $config['dashboard']['group_classes'],
        ]);
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
        $container->setParameter(Configuration::ALIAS . '.dashboard.ui', $config['dashboard']['ui'] ?? []);
        $container->setParameter(Configuration::ALIAS . '.dashboard.icon_selector_script_url', $config['dashboard']['icon_selector_script_url'] ?? null);
        $dashboardConfig = $config['dashboard'] ?? [];
        $stimulusUrl     = $dashboardConfig['stimulus_script_url'] ?? null;
        $liveEnabled     = class_exists(AsLiveComponent::class);
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
        $container->setParameter(
            Configuration::ALIAS . '.security.custom_access_checker',
            is_string($security['access_checker'] ?? null) && $security['access_checker'] !== '',
        );
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
            // @igor-ignore - Justified false positive for FrankenPHP worker audit
            $rateLimiterDef->setArgument('$logger', new Reference('logger'));
        }

        if ($runtimeLocales) {
            $this->wireRuntimeLocales($container, $defaultLocale);
        }
    }

    /**
     * Locales from an env var are only known at runtime: the services that take %nowo_dashboard_menu.locales% /
     * %nowo_dashboard_menu.default_locale_resolved% get them through {@see ConfiguredLocales} factories instead
     * (normalized list; first env locale as default when default_locale is null).
     */
    private function wireRuntimeLocales(ContainerBuilder $container, ?string $defaultLocale): void
    {
        $localesParameter = '%' . Configuration::ALIAS . '.locales%';
        $locales          = (new Definition('array'))
            ->setFactory([ConfiguredLocales::class, 'normalize'])
            ->setArguments([$localesParameter]);
        $replacements = [$localesParameter => $locales];
        if ($defaultLocale === null) {
            $replacements['%' . Configuration::ALIAS . '.default_locale_resolved%'] = (new Definition('string'))
                ->setFactory([ConfiguredLocales::class, 'defaultLocale'])
                ->setArguments([$localesParameter]);
        }

        foreach ($container->getDefinitions() as $definition) {
            foreach ($definition->getArguments() as $key => $argument) {
                if (is_string($argument) && isset($replacements[$argument])) {
                    $definition->setArgument($key, $replacements[$argument]);
                }
            }
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
        // Prefer explicit security.access_roles (incl. BC map from dashboard.required_role via Configuration::beforeNormalization).
        if (
            !array_key_exists('access_roles', $config['security'] ?? [])
            && is_string($legacyRole)
            && $legacyRole !== ''
            && $security['access_roles'] === ['ROLE_ADMIN']
        ) {
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
