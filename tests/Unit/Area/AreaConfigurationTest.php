<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Area;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\DependencyInjection\DashboardMenuExtension;
use Nowo\DashboardMenuBundle\Service\ConfiguredRouteMenuCurrentMatcher;
use Nowo\DashboardMenuBundle\Service\MenuAreaAccessCheckerInterface;
use Nowo\DashboardMenuBundle\Service\MenuHubBadgeProviderInterface;
use Nowo\DashboardMenuBundle\Service\MenuHubCardExtrasProviderInterface;
use Nowo\DashboardMenuBundle\Service\RoleMenuAreaAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AreaConfigurationTest extends TestCase
{
    public function testDefaultsKeepTodaysBehaviour(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame([], $config['areas']);
        self::assertNull($config['area_default']);
        self::assertSame('/^[a-z]{2}(?:[_-][A-Za-z]{2})?$/D', $config['area_locale_pattern']);
        self::assertNull($config['area_access_checker']);
        self::assertSame([], $config['current_routes']);
        self::assertSame(['exclude_routes' => [], 'testid_prefix' => 'dashboard-menu-hub-', 'cards' => []], $config['hub']);

        $container = $this->load([]);
        self::assertFalse($container->hasDefinition(ConfiguredRouteMenuCurrentMatcher::class));
        self::assertSame(RoleMenuAreaAccessChecker::class, (string) $container->getAlias(MenuAreaAccessCheckerInterface::class));
        self::assertSame([], $container->getParameter(Configuration::ALIAS . '.areas'));
    }

    public function testAreasHubAndRules(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'areas' => [
                'development' => [
                    'menu'           => 'administration',
                    'roles'          => ['ROLE_DEVELOPER'],
                    'path_prefixes'  => ['/developer'],
                    'current_routes' => ['admin_storage' => 'admin_storage', 'admin_ui_kit' => ['admin_ui_kit_buttons', 'admin_ui_kit_forms']],
                ],
            ],
            'current_routes' => ['nowo_manual_index' => ['nowo_manual_']],
            'hub'            => ['cards' => ['admin_users' => ['intro' => 'admin.hub.users', 'testid' => 'admin-users']]],
        ]]);

        $development = $config['areas']['development'];
        self::assertSame('administration', $development['menu']);
        self::assertNull($development['label']);
        self::assertSame([], $development['route_prefixes']);
        self::assertSame(['admin_storage' => ['admin_storage'], 'admin_ui_kit' => ['admin_ui_kit_buttons', 'admin_ui_kit_forms']], $development['current_routes']);
        self::assertSame(['nowo_manual_index' => ['nowo_manual_']], $config['current_routes']);
        self::assertSame(['intro' => 'admin.hub.users', 'testid' => 'admin-users', 'tour' => null, 'badge' => null], $config['hub']['cards']['admin_users']);
    }

    public function testMatcherRegisteredWhenAnyRuleExistsAndCustomAccessChecker(): void
    {
        $container = $this->load([['areas' => ['org' => ['current_routes' => ['admin_users' => ['admin_users_']]]], 'area_access_checker' => 'app.area_policy', 'area_default' => 'org']]);
        self::assertTrue($container->hasDefinition(ConfiguredRouteMenuCurrentMatcher::class));
        self::assertSame('app.area_policy', (string) $container->getAlias(MenuAreaAccessCheckerInterface::class));
        self::assertSame('org', $container->getParameter(Configuration::ALIAS . '.area_default'));

        self::assertTrue($this->load([['current_routes' => ['a' => ['a_']]]])->hasDefinition(ConfiguredRouteMenuCurrentMatcher::class));
        self::assertFalse($this->load([['areas' => ['org' => []]]])->hasDefinition(ConfiguredRouteMenuCurrentMatcher::class));
    }

    public function testHubPortsAreAutoconfigured(): void
    {
        $instanceof = $this->load([])->getAutoconfiguredInstanceof();

        self::assertArrayHasKey('nowo_dashboard_menu.hub_card_extras_provider', $instanceof[MenuHubCardExtrasProviderInterface::class]->getTags());
        self::assertArrayHasKey('nowo_dashboard_menu.hub_badge_provider', $instanceof[MenuHubBadgeProviderInterface::class]->getTags());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');
        $container->setParameter('kernel.bundles', []);
        (new DashboardMenuExtension())->load($configs, $container);

        return $container;
    }
}
