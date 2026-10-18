<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Integration\Kernel;

use Nowo\DashboardMenuBundle\Form\MenuItemBasicType;
use Nowo\DashboardMenuBundle\Form\MenuItemConfigType;
use Nowo\DashboardMenuBundle\Service\MenuLocaleResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

use function json_encode;
use function md5;
use function serialize;
use function str_contains;
use function sys_get_temp_dir;

/**
 * Real kernel: nowo_dashboard_menu.locales given as %app.locales%, %kernel.enabled_locales% or an env var
 * (the host keeps one locale list) must compile and reach the locale resolver, the item form locale tabs and the
 * item config form default locale as the resolved list.
 */
final class LocaleParameterKernelTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-dashboard-menu-locale-kernel');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, string}>
     */
    public static function provideLocaleConfigs(): iterable
    {
        yield 'app parameter' => [['locales' => '%app.locales%'], ['es', 'en', 'fr'], 'es'];
        yield 'kernel.enabled_locales' => [['locales' => '%kernel.enabled_locales%'], ['de', 'en'], 'de'];
        yield 'parameter + explicit default_locale' => [['locales' => '%app.locales%', 'default_locale' => 'fr'], ['es', 'en', 'fr'], 'fr'];
        yield 'env var (json)' => [['locales' => '%env(json:' . LocaleEnvVarLoader::JSON . ')%'], ['pt', 'en'], 'pt'];
        yield 'env var (csv)' => [['locales' => '%env(csv:' . LocaleEnvVarLoader::CSV . ')%'], ['it', 'en'], 'it'];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string> $expectedLocales
     */
    #[DataProvider('provideLocaleConfigs')]
    public function testLocaleListFromParameterCompilesAndResolves(array $config, array $expectedLocales, string $expectedDefault): void
    {
        $kernel = new LocaleParameterTestKernel(md5(serialize($config)), $config);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);

            $resolver = $container->get(MenuLocaleResolver::class);
            self::assertInstanceOf(MenuLocaleResolver::class, $resolver);
            self::assertSame($expectedLocales, $resolver->getLocales());
            self::assertSame('en', $resolver->resolveLocale('en'));
            self::assertSame($expectedDefault, $resolver->resolveLocale('zz'));

            $basicType = $container->get(MenuItemBasicType::class);
            self::assertInstanceOf(MenuItemBasicType::class, $basicType);
            self::assertSame($expectedLocales, (new ReflectionProperty(MenuItemBasicType::class, 'availableLocales'))->getValue($basicType));

            $configType = $container->get(MenuItemConfigType::class);
            self::assertInstanceOf(MenuItemConfigType::class, $configType);
            self::assertSame($expectedDefault, (new ReflectionProperty(MenuItemConfigType::class, 'defaultLocale'))->getValue($configType));

            if (!str_contains((string) json_encode($config), 'env(')) {
                self::assertSame($expectedLocales, $container->getParameter('nowo_dashboard_menu.locales'));
                self::assertSame($expectedDefault, $container->getParameter('nowo_dashboard_menu.default_locale_resolved'));
            }
        } finally {
            $kernel->shutdown();
        }
    }
}
