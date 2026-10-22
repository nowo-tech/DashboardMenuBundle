<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection;

use Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector;
use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber;
use Nowo\DashboardMenuBundle\Form\SearchQueryType;
use Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\ConfigurableDashboardMenuAccessChecker;
use Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use Nowo\DashboardMenuBundle\View\DashboardPages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\Autocomplete\AutocompleteBundle;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

final class DashboardMenuExtensionTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testAPrependReturnsEarlyWhenLiveComponentClassMissing(): void
    {
        $container = new ContainerBuilder();
        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        self::assertFalse($container->hasExtension('twig_component'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testBPrependRegistersLiveComponentTwigComponentDefaultsWhenClassExists(): void
    {
        if (!class_exists(AsLiveComponent::class)) {
            eval('namespace Symfony\\UX\\LiveComponent\\Attribute; class AsLiveComponent {}');
        }

        self::assertTrue(class_exists(AsLiveComponent::class));

        $container = new ContainerBuilder();
        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $configs = $container->getExtensionConfig('twig_component');
        self::assertNotEmpty($configs);

        // We only validate the important default namespace mapping.
        $found = false;
        foreach ($configs as $cfg) {
            if (($cfg['defaults'] ?? []) === ['Nowo\\DashboardMenuBundle\\LiveComponent\\' => 'components/']) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found);
    }

    public function testCLoadDetectsUxAutocompleteAvailabilityFromKernelBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', [
            AutocompleteBundle::class => new stdClass(),
        ]);

        $extension = new DashboardMenuExtension();
        $extension->load([], $container);

        self::assertTrue($container->getParameter(Configuration::ALIAS . '.ux_autocomplete_available'));
    }

    public function testCLoadExposesMenuCollapseParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);

        (new DashboardMenuExtension())->load([['menu_collapse' => ['enabled' => true, 'storage_key' => 'beacon.navCollapse']]], $container);

        self::assertEquals(
            ['enabled' => true, 'mode' => 'auto', 'storage_key' => 'beacon.navCollapse'],
            $container->getParameter(Configuration::ALIAS . '.menu_collapse'),
        );
        $arguments = $container->getDefinition(MenuExtension::class)->getArguments();
        self::assertSame('%' . Configuration::ALIAS . '.menu_collapse%', $arguments['$menuCollapse']);
    }

    public function testCLoadExposesDashboardUiParameterAndService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);

        (new DashboardMenuExtension())->load([[
            'dashboard' => ['ui' => ['index_view' => 'table', 'classes' => ['table' => 'data-table']]],
        ]], $container);

        $ui = $container->getParameter(Configuration::ALIAS . '.dashboard.ui');
        self::assertIsArray($ui);
        self::assertSame('table', $ui['index_view']);
        self::assertSame('data-table', $ui['classes']['table']);
        self::assertTrue($container->hasDefinition(DashboardUiConfig::class));
    }

    /**
     * @runInSeparateProcess
     */
    public function testDLoadSetsStimulusScriptUrlWhenMissingAndLiveComponentEnabled(): void
    {
        if (!class_exists(AsLiveComponent::class)) {
            eval('namespace Symfony\\UX\\LiveComponent\\Attribute; class AsLiveComponent {}');
        }

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'doctrine'  => ['connection' => 'default'],
                'dashboard' => [
                    'enabled'    => true,
                    'pagination' => ['enabled' => true, 'per_page' => 20],
                ],
            ],
        ], $container);

        self::assertSame(
            'bundles/nowodashboardmenu/js/stimulus-live.js',
            $container->getParameter(Configuration::ALIAS . '.dashboard.stimulus_script_url'),
        );
    }

    public function testELoadSetsSecurityDefaultsAndDoesNotRegisterSubscriberInExtension(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'doctrine'  => ['connection' => 'default'],
                'dashboard' => [
                    'enabled' => true,
                ],
            ],
        ], $container);

        self::assertSame(['ROLE_ADMIN'], $container->getParameter(Configuration::ALIAS . '.security.access_roles'));
        self::assertFalse($container->getParameter(Configuration::ALIAS . '.security.allow_unauthenticated'));
        self::assertSame('ROLE_ADMIN', $container->getParameter(Configuration::ALIAS . '.dashboard.required_role'));
        self::assertFalse($container->hasDefinition(DashboardAccessSubscriber::class));
        self::assertTrue($container->hasAlias(DashboardMenuAccessCheckerInterface::class));
    }

    public function testLoadTagsSearchQueryTypeAsFormType(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        (new DashboardMenuExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(SearchQueryType::class));
        self::assertArrayHasKey('form.type', $container->getDefinition(SearchQueryType::class)->getTags());
    }

    public function testLoadMapsLegacyRequiredRoleToAccessRoles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'dashboard' => [
                    'enabled'       => true,
                    'required_role' => 'ROLE_SUPER_ADMIN',
                ],
            ],
        ], $container);

        self::assertSame(['ROLE_SUPER_ADMIN'], $container->getParameter(Configuration::ALIAS . '.security.access_roles'));
    }

    public function testLoadMapsNullLegacyRequiredRoleToEmptyAccessRoles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'dashboard' => [
                    'enabled'       => true,
                    'required_role' => null,
                ],
            ],
        ], $container);

        self::assertSame([], $container->getParameter(Configuration::ALIAS . '.security.access_roles'));
        self::assertNull($container->getParameter(Configuration::ALIAS . '.dashboard.required_role'));
    }

    public function testUsesCustomAccessCheckerServiceId(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setDefinition('app.custom_access_checker', new Definition(
            AllowAllDashboardMenuAccessChecker::class,
        ));

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'security' => [
                    'access_checker' => 'app.custom_access_checker',
                ],
            ],
        ], $container);

        self::assertSame(
            'app.custom_access_checker',
            (string) $container->getAlias(DashboardMenuAccessCheckerInterface::class),
        );
    }

    public function testAllowUnauthenticatedWithoutSecurityUsesAllowAllChecker(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'security' => [
                    'allow_unauthenticated' => true,
                ],
            ],
        ], $container);

        self::assertTrue($container->hasDefinition('nowo_dashboard_menu.access_checker.allow_all'));
        self::assertSame(
            'nowo_dashboard_menu.access_checker.allow_all',
            (string) $container->getAlias(DashboardMenuAccessCheckerInterface::class),
        );
    }

    public function testDefaultAccessCheckerWiresAuthorizationCheckerWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setDefinition('security.authorization_checker', new Definition());
        $container->setDefinition('logger', new Definition());

        $extension = new DashboardMenuExtension();
        $extension->load([[]], $container);

        $definition = $container->getDefinition('nowo_dashboard_menu.access_checker.default');
        self::assertSame(
            ConfigurableDashboardMenuAccessChecker::class,
            $definition->getClass(),
        );
        self::assertArrayHasKey('$authorizationChecker', $definition->getArguments());
        $limiter = $container->getDefinition(ImportExportRateLimiter::class);
        self::assertArrayHasKey('$logger', $limiter->getArguments());
    }

    public function testLoadRegistersParametersAndServicesInProd(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([], $container);

        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.config'));
        $configSnapshot = $container->getParameter(Configuration::ALIAS . '.config');
        self::assertIsArray($configSnapshot);
        self::assertArrayHasKey('project', $configSnapshot);
        self::assertArrayHasKey('dashboard', $configSnapshot);
        self::assertArrayHasKey('api', $configSnapshot);
        self::assertNull($configSnapshot['project']);
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.locales'));
        self::assertSame([], $container->getParameter(Configuration::ALIAS . '.locales'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.permission_checker_choices'));
        self::assertSame([], $container->getParameter(Configuration::ALIAS . '.permission_checker_choices'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.default_locale_resolved'));
        self::assertSame('en', $container->getParameter(Configuration::ALIAS . '.default_locale_resolved'));
        self::assertTrue($container->hasDefinition(MenuExtension::class));
        $def = $container->getDefinition(MenuExtension::class);
        self::assertNull($def->getArgument('$dataCollector'));
    }

    public function testLoadLoadsDevServicesAndInjectsDataCollectorWhenDev(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');

        $extension = new DashboardMenuExtension();
        $extension->load([], $container);

        $def = $container->getDefinition(MenuExtension::class);
        $ref = $def->getArgument('$dataCollector');
        self::assertInstanceOf(Reference::class, $ref);
        self::assertSame(DashboardMenuDataCollector::class, (string) $ref);
    }

    public function testLoadWithCustomConfigSetsParameters(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $config = [
            'project'        => 'myapp',
            'locales'        => ['en', 'es'],
            'default_locale' => 'es',
            'api'            => ['enabled' => false, 'path_prefix' => '/api/custom'],
            'dashboard'      => [
                'enabled'    => true,
                'pagination' => ['enabled' => false, 'per_page' => 10],
            ],
            'permission_checker_choices' => ['checker.id'],
        ];

        $extension = new DashboardMenuExtension();
        $extension->load([$config], $container);

        $configSnapshot = $container->getParameter(Configuration::ALIAS . '.config');
        self::assertIsArray($configSnapshot);
        self::assertSame('myapp', $configSnapshot['project'] ?? null);
        self::assertSame(['en', 'es'], $configSnapshot['locales'] ?? null);
        self::assertSame('es', $configSnapshot['default_locale'] ?? null);
        self::assertFalse($configSnapshot['api']['enabled'] ?? null);
        self::assertSame('/api/custom', $configSnapshot['api']['path_prefix'] ?? null);
        self::assertTrue($configSnapshot['dashboard']['enabled'] ?? null);
        self::assertSame(['en', 'es'], $container->getParameter(Configuration::ALIAS . '.locales'));
        self::assertSame('es', $container->getParameter(Configuration::ALIAS . '.default_locale'));
        self::assertSame('es', $container->getParameter(Configuration::ALIAS . '.default_locale_resolved'));
        self::assertFalse($container->getParameter(Configuration::ALIAS . '.api.enabled'));
        self::assertSame('/api/custom', $container->getParameter(Configuration::ALIAS . '.api.path_prefix'));
        self::assertTrue($container->getParameter(Configuration::ALIAS . '.dashboard.enabled'));
        self::assertFalse($container->getParameter(Configuration::ALIAS . '.dashboard.pagination.enabled'));
        self::assertSame(10, $container->getParameter(Configuration::ALIAS . '.dashboard.pagination.per_page'));
        self::assertSame(['checker.id'], $container->getParameter(Configuration::ALIAS . '.permission_checker_choices'));
    }

    public function testGetAlias(): void
    {
        $extension = new DashboardMenuExtension();
        self::assertSame('nowo_dashboard_menu', $extension->getAlias());
    }

    public function testLoadSetsCssFrameworkAndIconSetParameters(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([], $container);

        self::assertSame('bootstrap5', $container->getParameter(Configuration::ALIAS . '.dashboard.css_framework'));
        self::assertSame('bootstrap-icons', $container->getParameter(Configuration::ALIAS . '.dashboard.icon_set'));
    }

    public function testLoadSetsCssFrameworkTailwindWhenConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([['dashboard' => ['css_framework' => 'tailwind', 'icon_set' => 'tabler-icons']]], $container);

        self::assertSame('tailwind', $container->getParameter(Configuration::ALIAS . '.dashboard.css_framework'));
        self::assertSame('tabler-icons', $container->getParameter(Configuration::ALIAS . '.dashboard.icon_set'));
    }

    public function testLoadNormalizesCssFrameworkBootstrapAlias(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([['dashboard' => ['css_framework' => 'bootstrap']]], $container);

        // 'bootstrap' normalizes to 'bootstrap5'.
        self::assertSame('bootstrap5', $container->getParameter(Configuration::ALIAS . '.dashboard.css_framework'));
    }

    public function testPrependRegistersAssetsPackageWhenFrameworkExtensionExists(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $configs = $container->getExtensionConfig('framework');
        $found   = false;
        foreach ($configs as $cfg) {
            if (isset($cfg['assets']['packages'][Configuration::ALIAS]['base_path'])
                && $cfg['assets']['packages'][Configuration::ALIAS]['base_path'] === '/bundles/nowodashboardmenu'
            ) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected nowo_dashboard_menu asset package to be prepended.');
    }

    public function testPrependAlignsUiKitDefaultsFromDashboardWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'dashboard' => [
                'css_framework' => 'tailwind',
                'icon_set'      => 'tabler-icons',
            ],
        ]);

        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tailwind'
                && ($cfg['icon_set'] ?? null) === 'tabler-icons'
            ) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected nowo_ui_kit defaults from dashboard config.');
    }

    public function testPrependDoesNotOverrideExplicitUiKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig('nowo_ui_kit', [
            'css_framework' => 'custom',
            'icon_set'      => 'ux_icon',
        ]);
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'dashboard' => [
                'css_framework' => 'tailwind',
                'icon_set'      => 'tabler-icons',
            ],
        ]);

        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $uiKitConfigs = $container->getExtensionConfig('nowo_ui_kit');
        foreach ($uiKitConfigs as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tailwind'
                || ($cfg['icon_set'] ?? null) === 'tabler-icons'
            ) {
                self::fail('Dashboard must not prepend UiKit defaults when host set nowo_ui_kit explicitly.');
            }
        }
        self::assertSame('custom', $uiKitConfigs[0]['css_framework'] ?? null);
        self::assertSame('ux_icon', $uiKitConfigs[0]['icon_set'] ?? null);
    }

    public function testPrependSkipsUiKitWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_ui_kit'));
    }

    public function testPrependAlignsFormKitDefaultsFromDashboardWhenHostUnset(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'dashboard' => [
                'css_framework' => 'tailwind',
            ],
        ]);

        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $found = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tailwind'
                && isset($cfg['profiles']['dashboard_menu']['alias'])
                && $cfg['profiles']['dashboard_menu']['alias'] === 'dashboard_menu'
            ) {
                $found = true;
                self::assertSame('NowoDashboardMenuBundle', $cfg['profiles']['dashboard_menu']['translation_domain']);
                // Tailwind stack: no Bootstrap field classes (2.7).
                self::assertSame('nowo-ui-input', $cfg['profiles']['dashboard_menu']['defaults']['attr']['class']);
                break;
            }
        }
        self::assertTrue($found, 'Expected nowo_form_kit dashboard_menu profile and css_framework from dashboard.');
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function formKitCssFrameworkProvider(): array
    {
        return [
            ['tailwind', 'tailwind'],
            ['foundation', 'foundation'],
            ['none', 'none'],
            ['bootstrap5', 'bootstrap'],
            ['tabler', 'bootstrap'],
        ];
    }

    #[DataProvider('formKitCssFrameworkProvider')]
    public function testPrependMapsDashboardCssFrameworkToFormKit(string $dashboardFw, string $expectedFormKitFw): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'dashboard' => [
                'css_framework' => $dashboardFw,
            ],
        ]);

        (new DashboardMenuExtension())->prepend($container);

        $mapped = null;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (isset($cfg['css_framework'])) {
                $mapped = $cfg['css_framework'];
                break;
            }
        }
        self::assertSame($expectedFormKitFw, $mapped);
    }

    public function testPrependDoesNotOverrideExplicitFormKitHostConfig(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig('nowo_form_kit', [
            'css_framework' => 'none',
            'profiles'      => [
                'dashboard_menu' => [
                    'alias'              => 'dashboard_menu',
                    'translation_domain' => 'HostDomain',
                ],
            ],
        ]);
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'dashboard' => [
                'css_framework' => 'tailwind',
            ],
        ]);

        (new DashboardMenuExtension())->prepend($container);

        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'tailwind') {
                self::fail('Dashboard must not prepend FormKit css_framework when host already set it.');
            }
            if (isset($cfg['profiles']['dashboard_menu']['translation_domain'])
                && $cfg['profiles']['dashboard_menu']['translation_domain'] === 'NowoDashboardMenuBundle'
            ) {
                self::fail('Dashboard must not prepend dashboard_menu profile when host already defined it.');
            }
        }
        $first = $container->getExtensionConfig('nowo_form_kit')[0];
        self::assertSame('none', $first['css_framework'] ?? null);
        self::assertSame('HostDomain', $first['profiles']['dashboard_menu']['translation_domain'] ?? null);
    }

    public function testPrependSeedsOnlyFormKitProfileWhenHostCssFrameworkSet(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig('nowo_form_kit', [
            'css_framework' => 'foundation',
        ]);

        (new DashboardMenuExtension())->prepend($container);

        $profileSeeded = false;
        foreach ($container->getExtensionConfig('nowo_form_kit') as $cfg) {
            if (($cfg['css_framework'] ?? null) === 'bootstrap' || ($cfg['css_framework'] ?? null) === 'tailwind') {
                self::fail('Must not re-seed css_framework when host already set it.');
            }
            if (isset($cfg['profiles']['dashboard_menu'])) {
                $profileSeeded = true;
            }
        }
        self::assertTrue($profileSeeded);
    }

    public function testPrependSkipsFormKitWhenExtensionMissing(): void
    {
        $container = new ContainerBuilder();
        (new DashboardMenuExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('nowo_form_kit'));
    }

    #[IgnoreDeprecations]
    public function testPrependBridgesTheDeprecatedChromeIntoTheUiKitTheme(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $container->prependExtensionConfig(Configuration::ALIAS, [
            'dashboard' => ['ui' => ['classes' => ['button_primary' => 'btn btn-dark']]],
        ]);

        (new DashboardMenuExtension())->prepend($container);

        $configs = $container->getExtensionConfig('nowo_ui_kit');
        self::assertSame(['btn_primary' => ['value' => 'btn btn-dark', 'framework_defaults' => false]], $configs[0]['theme']['bundles']['NowoDashboardMenuBundle']['classes']);
        self::assertArrayNotHasKey('css_framework', $configs[0], 'Default dashboard.css_framework is not seeded (UiKit owns the stack).');
    }

    #[IgnoreDeprecations]
    public function testPrependSeedsUiKitOnlyFromExplicitDashboardStack(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_ui_kit');
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig(Configuration::ALIAS, ['dashboard' => ['css_framework' => 'bootstrap', 'icon_set' => 'tabler-icons']]);

        (new DashboardMenuExtension())->prepend($container);

        $uiKit = $container->getExtensionConfig('nowo_ui_kit')[0];
        self::assertSame('bootstrap5', $uiKit['css_framework']);
        self::assertSame('tabler-icons', $uiKit['icon_set']);
        $formKit = $container->getExtensionConfig('nowo_form_kit')[0];
        self::assertSame('bootstrap', $formKit['css_framework']);
        self::assertSame('form-check mb-2', $formKit['profiles']['dashboard_menu']['field_types']['checkbox']['row_attr']['class']);
    }

    public function testFormKitProfileFollowsTheHostUiKitStack(): void
    {
        $container = new ContainerBuilder();
        $this->registerStubExtension($container, 'nowo_form_kit');
        $container->prependExtensionConfig('nowo_ui_kit', ['css_framework' => 'none']);

        (new DashboardMenuExtension())->prepend($container);

        $formKit = $container->getExtensionConfig('nowo_form_kit')[0];
        self::assertSame('none', $formKit['css_framework']);
        self::assertSame('nowo-ui-input', $formKit['profiles']['dashboard_menu']['defaults']['attr']['class']);
        self::assertTrue($formKit['profiles']['dashboard_menu']['convention_requires_translation']);
    }

    #[IgnoreDeprecations]
    public function testLoadMapsTheDeprecatedDashboardKeysToWebUi(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([[
            'dashboard' => ['enabled' => true, 'layout_template' => '@NowoDashboardMenuBundle/dashboard/layout.html.twig', 'ui' => ['templates' => ['index' => 'admin/menus.html.twig']]],
            'web_ui'    => ['path_prefix' => '/backoffice/menus'],
        ]], $container);

        self::assertTrue($container->getParameter('nowo_dashboard_menu.web_ui.enabled'));
        self::assertTrue($container->getParameter('nowo_dashboard_menu.dashboard.enabled'));
        self::assertNull($container->getParameter('nowo_dashboard_menu.web_ui.layout_template'), 'The removed 1.x default layout means the UiKit layout.');
        self::assertSame('/backoffice/menus', $container->getParameter('nowo_dashboard_menu.web_ui.path_prefix'));
        self::assertSame(['index' => 'admin/menus.html.twig'], $container->getParameter('nowo_dashboard_menu.web_ui.templates'));
    }

    public function testLoadSetsTheIndexIntroParameters(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([[]], $container);

        self::assertNull($container->getParameter('nowo_dashboard_menu.web_ui.index.intro'));
        self::assertSame('NowoDashboardMenuBundle', $container->getParameter('nowo_dashboard_menu.web_ui.index.intro_domain'));
        self::assertSame('%nowo_dashboard_menu.web_ui.index.intro%', $container->getDefinition(DashboardPages::class)->getArgument('$indexIntro'));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([['web_ui' => ['index' => ['intro' => 'admin.hub.menus_hint', 'intro_domain' => 'messages']]]], $container);

        self::assertSame('admin.hub.menus_hint', $container->getParameter('nowo_dashboard_menu.web_ui.index.intro'));
        self::assertSame('messages', $container->getParameter('nowo_dashboard_menu.web_ui.index.intro_domain'));

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load([['web_ui' => ['index' => ['intro' => ' ']]]], $container);
        self::assertNull($container->getParameter('nowo_dashboard_menu.web_ui.index.intro'), 'A blank intro means none.');
    }

    private function registerStubExtension(ContainerBuilder $container, string $alias): void
    {
        $container->registerExtension(new class($alias) extends Extension {
            public function __construct(private readonly string $extensionAlias)
            {
            }

            public function getAlias(): string
            {
                return $this->extensionAlias;
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
    }
}
