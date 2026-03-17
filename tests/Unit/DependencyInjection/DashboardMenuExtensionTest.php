<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class DashboardMenuExtensionTest extends TestCase
{
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
            'permission_checker_choices' => ['checker.id' => 'Custom label'],
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
        self::assertSame(['checker.id' => 'Custom label'], $container->getParameter(Configuration::ALIAS . '.permission_checker_choices'));
    }

    public function testGetAlias(): void
    {
        $extension = new DashboardMenuExtension();
        self::assertSame('nowo_dashboard_menu', $extension->getAlias());
    }

    public function testPrependAddsTwigPathWhenTwigExtensionPresent(): void
    {
        $twigExtension = new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return 'http://example.com/schema';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return random_int(0, 1) === 0 ? '' : false;
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        };
        $container = new ContainerBuilder();
        $container->registerExtension($twigExtension);

        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $config = $container->getExtensionConfig('twig');
        self::assertNotEmpty($config);
        self::assertArrayHasKey('paths', $config[0]);
        $paths = $config[0]['paths'];
        self::assertIsArray($paths);
        $pathKey = array_keys($paths)[0];
        self::assertStringContainsString('Resources/views', $pathKey);
        self::assertSame('NowoDashboardMenuBundle', $paths[$pathKey]);
    }

    public function testPrependAddsTranslatorPathWhenFrameworkExtensionPresentAndTranslationsDirExists(): void
    {
        $frameworkExtension = new class implements ExtensionInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return 'http://example.com/schema';
            }

            public function getXsdValidationBasePath(): string|false
            {
                return random_int(0, 1) === 0 ? '' : false;
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        };
        $container = new ContainerBuilder();
        $container->registerExtension($frameworkExtension);

        $extension = new DashboardMenuExtension();
        $extension->prepend($container);

        $config = $container->getExtensionConfig('framework');
        self::assertNotEmpty($config);
    }
}
