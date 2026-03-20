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

    public function testELoadRegistersDashboardAccessSubscriberWhenRequiredRoleProvided(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([
            [
                'doctrine'  => ['connection' => 'default'],
                'dashboard' => [
                    'enabled'       => true,
                    'required_role' => 'ROLE_ADMIN',
                ],
            ],
        ], $container);

        self::assertTrue($container->hasDefinition(\Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber::class));
        $def  = $container->getDefinition(\Nowo\DashboardMenuBundle\EventSubscriber\DashboardAccessSubscriber::class);
        $args = $def->getArguments();
        self::assertSame('ROLE_ADMIN', $args[0]);
        self::assertInstanceOf(Reference::class, $args[1]);
        self::assertSame('security.authorization_checker', (string) $args[1]);
    }

    public function testLoadRegistersParametersAndServicesInProd(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension = new DashboardMenuExtension();
        $extension->load([], $container);

        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.config'));
        self::assertSame(['project' => null], $container->getParameter(Configuration::ALIAS . '.config'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.locales'));
        self::assertSame([], $container->getParameter(Configuration::ALIAS . '.locales'));
        self::assertTrue($container->hasParameter(Configuration::ALIAS . '.permission_checker_choices'));
        self::assertSame(['order' => [], 'labels' => []], $container->getParameter(Configuration::ALIAS . '.permission_checker_choices'));
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
            'permission_checker_choices' => ['order' => ['checker.id'], 'labels' => ['checker.id' => 'Custom label']],
        ];

        $extension = new DashboardMenuExtension();
        $extension->load([$config], $container);

        self::assertSame(['project' => 'myapp'], $container->getParameter(Configuration::ALIAS . '.config'));
        self::assertSame(['en', 'es'], $container->getParameter(Configuration::ALIAS . '.locales'));
        self::assertSame('es', $container->getParameter(Configuration::ALIAS . '.default_locale'));
        self::assertSame('es', $container->getParameter(Configuration::ALIAS . '.default_locale_resolved'));
        self::assertFalse($container->getParameter(Configuration::ALIAS . '.api.enabled'));
        self::assertSame('/api/custom', $container->getParameter(Configuration::ALIAS . '.api.path_prefix'));
        self::assertTrue($container->getParameter(Configuration::ALIAS . '.dashboard.enabled'));
        self::assertFalse($container->getParameter(Configuration::ALIAS . '.dashboard.pagination.enabled'));
        self::assertSame(10, $container->getParameter(Configuration::ALIAS . '.dashboard.pagination.per_page'));
        self::assertSame(['order' => ['checker.id'], 'labels' => ['checker.id' => 'Custom label']], $container->getParameter(Configuration::ALIAS . '.permission_checker_choices'));
    }

    public function testGetAlias(): void
    {
        $extension = new DashboardMenuExtension();
        self::assertSame('nowo_dashboard_menu', $extension->getAlias());
    }
}
