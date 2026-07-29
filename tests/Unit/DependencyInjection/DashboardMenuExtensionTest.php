<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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
        if (!class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class)) {
            eval('namespace Symfony\\UX\\LiveComponent\\Attribute; class AsLiveComponent {}');
        }

        self::assertTrue(class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class));

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
            \Symfony\UX\Autocomplete\AutocompleteBundle::class => new stdClass(),
        ]);

        $extension = new DashboardMenuExtension();
        $extension->load([], $container);

        self::assertTrue($container->getParameter(Configuration::ALIAS . '.ux_autocomplete_available'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testDLoadSetsStimulusScriptUrlWhenMissingAndLiveComponentEnabled(): void
    {
        if (!class_exists(\Symfony\UX\LiveComponent\Attribute\AsLiveComponent::class)) {
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
        self::assertFalse($container->hasDefinition(\Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber::class));
        self::assertTrue($container->hasAlias(\Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface::class));
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

    public function testUsesCustomAccessCheckerServiceId(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setDefinition('app.custom_access_checker', new \Symfony\Component\DependencyInjection\Definition(
            \Nowo\DashboardMenuBundle\Security\AllowAllDashboardMenuAccessChecker::class,
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
            (string) $container->getAlias(\Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface::class),
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
            (string) $container->getAlias(\Nowo\DashboardMenuBundle\Security\DashboardMenuAccessCheckerInterface::class),
        );
    }

    public function testDefaultAccessCheckerWiresAuthorizationCheckerWhenPresent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setDefinition('security.authorization_checker', new \Symfony\Component\DependencyInjection\Definition());
        $container->setDefinition('logger', new \Symfony\Component\DependencyInjection\Definition());

        $extension = new DashboardMenuExtension();
        $extension->load([[]], $container);

        $definition = $container->getDefinition('nowo_dashboard_menu.access_checker.default');
        self::assertSame(
            \Nowo\DashboardMenuBundle\Security\ConfigurableDashboardMenuAccessChecker::class,
            $definition->getClass(),
        );
        self::assertArrayHasKey('$authorizationChecker', $definition->getArguments());
        $limiter = $container->getDefinition(\Nowo\DashboardMenuBundle\Service\ImportExportRateLimiter::class);
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
        self::assertSame(\Nowo\DashboardMenuBundle\DataCollector\DashboardMenuDataCollector::class, (string) $ref);
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
        $container->registerExtension(new class extends \Symfony\Component\DependencyInjection\Extension\Extension {
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
}
