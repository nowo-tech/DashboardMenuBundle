<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\DependencyInjection;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

use function strlen;

final class ConfigurationTest extends TestCase
{
    public function testGetConfigTreeBuilderReturnsTreeBuilder(): void
    {
        $config      = new Configuration();
        $treeBuilder = $config->getConfigTreeBuilder();
        self::assertSame(Configuration::ALIAS, $treeBuilder->buildTree()->getName());
    }

    public function testProcessConfigurationWithEmptyConfigUsesDefaults(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [[]]);

        self::assertNull($config['project']);
        self::assertSame([], $config['locales']);
        self::assertNull($config['default_locale']);
        self::assertTrue($config['api']['enabled']);
        self::assertSame('/api/menu', $config['api']['path_prefix']);
        self::assertFalse($config['dashboard']['enabled']);
        self::assertSame('/admin/menus', $config['dashboard']['path_prefix']);
        self::assertSame([], $config['dashboard']['route_name_exclude_patterns']);
        self::assertTrue($config['dashboard']['pagination']['enabled']);
        self::assertSame(20, $config['dashboard']['pagination']['per_page']);
        self::assertSame('normal', $config['dashboard']['modals']['menu_form']);
        self::assertSame('normal', $config['dashboard']['modals']['copy']);
        self::assertSame('lg', $config['dashboard']['modals']['item_form']);
        self::assertSame('normal', $config['dashboard']['modals']['delete']);
        self::assertSame(100, $config['dashboard']['position_step']);
        self::assertArrayHasKey('menu', $config['dashboard']['css_class_options']);
        self::assertArrayHasKey('item', $config['dashboard']['css_class_options']);
        self::assertArrayHasKey('section_children', $config['dashboard']['css_class_options']);
        self::assertArrayHasKey('section_child_item', $config['dashboard']['css_class_options']);
        self::assertArrayHasKey('section_child_link', $config['dashboard']['css_class_options']);
        self::assertSame('bootstrap5', $config['dashboard']['css_framework']);
        self::assertSame('bootstrap-icons', $config['dashboard']['icon_set']);
        self::assertSame([], $config['permission_checker_choices']);
        self::assertSame([], $config['menu_link_resolver_choices']);
        self::assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        self::assertFalse($config['security']['allow_unauthenticated']);
        self::assertNull($config['security']['access_checker']);
    }

    public function testProcessConfigurationMergesCustomConfig(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            [
                'project'        => 'my_app',
                'locales'        => ['en', 'es'],
                'default_locale' => 'en',
                'api'            => ['enabled' => false, 'path_prefix' => '/menus/api'],
                'dashboard'      => [
                    'enabled'                     => true,
                    'route_name_exclude_patterns' => ['^_'],
                    'pagination'                  => ['enabled' => false, 'per_page' => 50],
                    'modals'                      => ['menu_form' => 'lg', 'item_form' => 'xl'],
                ],
                'permission_checker_choices' => ['app.my_checker'],
            ],
        ]);

        self::assertSame('my_app', $config['project']);
        self::assertSame(['en', 'es'], $config['locales']);
        self::assertSame('en', $config['default_locale']);
        self::assertFalse($config['api']['enabled']);
        self::assertSame('/menus/api', $config['api']['path_prefix']);
        self::assertTrue($config['dashboard']['enabled']);
        self::assertSame(['^_'], $config['dashboard']['route_name_exclude_patterns']);
        self::assertFalse($config['dashboard']['pagination']['enabled']);
        self::assertSame(50, $config['dashboard']['pagination']['per_page']);
        self::assertSame('lg', $config['dashboard']['modals']['menu_form']);
        self::assertSame('xl', $config['dashboard']['modals']['item_form']);
        self::assertSame(['app.my_checker'], $config['permission_checker_choices']);
    }

    public function testProcessConfigurationNormalizesPermissionCheckerChoicesList(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            [
                'permission_checker_choices' => [
                    \Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker::class,
                    'App\Service\DemoMenuPermissionChecker',
                ],
            ],
        ]);

        self::assertSame(
            [\Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker::class, 'App\Service\DemoMenuPermissionChecker'],
            $config['permission_checker_choices'],
        );
    }

    public function testProcessConfigurationNormalizesLegacyOrderAndIgnoresLabels(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            [
                'permission_checker_choices' => [
                    'order'  => ['app.a', 'app.b'],
                    'labels' => ['app.a' => 'Should be ignored'],
                ],
            ],
        ]);

        self::assertSame(['app.a', 'app.b'], $config['permission_checker_choices']);
    }

    public function testProcessConfigurationValidatesModalValues(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);
        $this->expectExceptionMessage('Must be normal, lg, or xl');

        $processor = new Processor();
        $processor->processConfiguration(new Configuration(), [
            ['dashboard' => ['modals' => ['menu_form' => 'invalid']]],
        ]);
    }

    public function testProcessConfigurationAcceptsValidCssFramework(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            ['dashboard' => ['css_framework' => 'tailwind']],
        ]);

        self::assertSame('tailwind', $config['dashboard']['css_framework']);
    }

    public function testProcessConfigurationAcceptsCssFrameworkBootstrapAlias(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            ['dashboard' => ['css_framework' => 'bootstrap']],
        ]);

        self::assertSame('bootstrap', $config['dashboard']['css_framework']);
    }

    public function testProcessConfigurationRejectsInvalidCssFramework(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $processor = new Processor();
        $processor->processConfiguration(new Configuration(), [
            ['dashboard' => ['css_framework' => 'invalid_framework']],
        ]);
    }

    public function testProcessConfigurationAcceptsValidIconSet(): void
    {
        $processor = new Processor();
        $config    = $processor->processConfiguration(new Configuration(), [
            ['dashboard' => ['icon_set' => 'tabler-icons']],
        ]);

        self::assertSame('tabler-icons', $config['dashboard']['icon_set']);
    }

    public function testProcessConfigurationRejectsInvalidIconSet(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $processor = new Processor();
        $processor->processConfiguration(new Configuration(), [
            ['dashboard' => ['icon_set' => 'invalid_icons']],
        ]);
    }

    public function testAliasConstant(): void
    {
        self::assertGreaterThan(0, strlen(Configuration::ALIAS));
    }
}
