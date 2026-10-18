<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\DependencyInjection;

use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Form\MenuItemBasicType;
use Nowo\DashboardMenuBundle\Form\MenuItemConfigType;
use Nowo\DashboardMenuBundle\Service\ConfiguredLocales;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * nowo_dashboard_menu.locales as a literal list, a parameter or an env placeholder (direct load() callers).
 */
final class LocalesConfigTest extends TestCase
{
    public function testParameterStringIsResolvedInLoad(): void
    {
        $container = $this->container();
        $container->setParameter('app.locales', ['es', 'en']);
        (new DashboardMenuExtension())->load([['locales' => '%app.locales%']], $container);

        self::assertSame(['es', 'en'], $container->getParameter('nowo_dashboard_menu.locales'));
        self::assertSame('es', $container->getParameter('nowo_dashboard_menu.default_locale_resolved'));
        $bundleConfig = $container->getParameter('nowo_dashboard_menu.config');
        self::assertIsArray($bundleConfig);
        self::assertSame(['es', 'en'], $bundleConfig['locales']);
        self::assertSame('%nowo_dashboard_menu.locales%', $container->getDefinition(MenuItemBasicType::class)->getArgument('$availableLocales'));
    }

    public function testEnvPlaceholderIsResolvedAtRuntimeByFactories(): void
    {
        $container = $this->container();
        (new DashboardMenuExtension())->load([['locales' => '%env(json:APP_LOCALES)%']], $container);

        self::assertSame('%env(json:APP_LOCALES)%', $container->resolveEnvPlaceholders($container->getParameter('nowo_dashboard_menu.locales')));
        self::assertSame(ConfiguredLocales::FALLBACK_LOCALE, $container->getParameter('nowo_dashboard_menu.default_locale_resolved'));

        foreach ([
            [MenuItemBasicType::class, '$availableLocales', 'normalize'],
            [MenuLocaleResolver::class, 0, 'normalize'],
            [MenuItemConfigType::class, '$defaultLocale', 'defaultLocale'],
        ] as [$serviceId, $argumentKey, $factoryMethod]) {
            $argument = $container->getDefinition($serviceId)->getArgument($argumentKey);
            self::assertInstanceOf(Definition::class, $argument, $serviceId);
            self::assertSame([ConfiguredLocales::class, $factoryMethod], $argument->getFactory());
            self::assertSame(['%nowo_dashboard_menu.locales%'], $argument->getArguments());
        }
    }

    public function testEnvPlaceholderWithExplicitDefaultLocaleKeepsTheParameter(): void
    {
        $container = $this->container();
        (new DashboardMenuExtension())->load([['locales' => '%env(json:APP_LOCALES)%', 'default_locale' => 'fr']], $container);

        self::assertSame('fr', $container->getParameter('nowo_dashboard_menu.default_locale_resolved'));
        self::assertSame('%nowo_dashboard_menu.default_locale_resolved%', $container->getDefinition(MenuItemConfigType::class)->getArgument('$defaultLocale'));
    }

    public function testInvalidLocalesAreRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid nowo_dashboard_menu.locales');

        (new DashboardMenuExtension())->load([['locales' => ['en', ['es']]]], $this->container());
    }

    public function testPrependIgnoresParameterStringsOutsideTheDashboardNode(): void
    {
        $container = $this->container();
        $container->registerExtension(new class extends \Symfony\Component\DependencyInjection\Extension\Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'nowo_ui_kit';
            }
        });
        $container->registerExtension($extension = new DashboardMenuExtension());
        $container->loadFromExtension('nowo_dashboard_menu', [
            'locales'   => '%kernel.enabled_locales%',
            'cache'     => ['ttl' => '%app.ttl%'],
            'dashboard' => ['css_framework' => 'tailwind'],
        ]);

        $extension->prepend($container);

        self::assertSame([['css_framework' => 'tailwind']], $container->getExtensionConfig('nowo_ui_kit'));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);

        return $container;
    }
}
