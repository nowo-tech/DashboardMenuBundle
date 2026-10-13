<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Service;

use Nowo\DashboardMenuBundle\DependencyInjection\Configuration;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class DashboardUiConfigTest extends TestCase
{
    public function testDefaultsKeepBundleChrome(): void
    {
        $ui = new DashboardUiConfig();

        self::assertSame('fallback', $ui->cssClass('button_primary', 'fallback'));
        self::assertSame('', $ui->cssClass('table'));
        self::assertSame(DashboardUiConfig::DEFAULT_TEMPLATES['index'], $ui->template('index'));
        self::assertSame(DashboardUiConfig::DEFAULT_PARTIALS['modal'], $ui->partial('modal'));
        self::assertSame('', $ui->template('unknown'));
        self::assertSame('', $ui->partial('unknown'));
        self::assertSame([
            'classes'                  => [],
            'partials'                 => DashboardUiConfig::DEFAULT_PARTIALS,
            'templates'                => DashboardUiConfig::DEFAULT_TEMPLATES,
            'form_themes'              => [],
            'include_ui_kit_css'       => true,
            'header_actions_in_layout' => false,
            'index_view'               => 'list',
            'actions_column'           => 'first',
            'row_action_display'       => null,
            'region_attribute'         => null,
        ], $ui->toArray());
    }

    public function testConfiguredValues(): void
    {
        $ui = new DashboardUiConfig([
            'include_ui_kit_css'       => false,
            'header_actions_in_layout' => true,
            'index_view'               => 'table',
            'actions_column'           => 'last',
            'row_action_display'       => 'icon_text',
            'region_attribute'         => 'data-testid',
            'form_themes'              => ['theme.html.twig', '', 42],
            'classes'                  => ['button_primary' => 'btn-primary', 'empty' => '', 'table' => null, 'not_a_key' => 'x'],
            'partials'                 => ['pagination' => 'host/_pagination.html.twig', 'modal' => ''],
            'templates'                => ['show' => 'admin/menus/show.html.twig', 'bogus' => 'x.html.twig'],
        ]);

        self::assertSame('btn-primary', $ui->cssClass('button_primary', 'fallback'));
        self::assertSame('', $ui->cssClass('empty', 'fallback'));
        self::assertSame('fallback', $ui->cssClass('table', 'fallback'));
        self::assertSame('host/_pagination.html.twig', $ui->partial('pagination'));
        self::assertSame(DashboardUiConfig::DEFAULT_PARTIALS['modal'], $ui->partial('modal'));
        self::assertSame('admin/menus/show.html.twig', $ui->template('show'));
        self::assertSame('', $ui->template('bogus'));

        $array = $ui->toArray();
        self::assertSame(['button_primary' => 'btn-primary', 'empty' => ''], $array['classes']);
        self::assertSame(['theme.html.twig'], $array['form_themes']);
        self::assertFalse($array['include_ui_kit_css']);
        self::assertTrue($array['header_actions_in_layout']);
        self::assertSame('table', $array['index_view']);
        self::assertSame('last', $array['actions_column']);
        self::assertSame('icon_text', $array['row_action_display']);
        self::assertSame('data-testid', $array['region_attribute']);
    }

    public function testInvalidRawValuesFallBackToDefaults(): void
    {
        $array = (new DashboardUiConfig([
            'classes'            => 'nope',
            'form_themes'        => 'nope',
            'index_view'         => 'grid',
            'row_action_display' => 'emoji',
            'region_attribute'   => '',
            'include_ui_kit_css' => 'yes',
        ]))->toArray();

        self::assertSame([], $array['classes']);
        self::assertSame([], $array['form_themes']);
        self::assertSame('list', $array['index_view']);
        self::assertNull($array['row_action_display']);
        self::assertNull($array['region_attribute']);
        self::assertTrue($array['include_ui_kit_css']);
    }

    public function testConfigurationTreeDefaultsAndValidation(): void
    {
        $processor = new Processor();
        $ui        = $processor->processConfiguration(new Configuration(), [[]])['dashboard']['ui'];

        self::assertTrue($ui['include_ui_kit_css']);
        self::assertFalse($ui['header_actions_in_layout']);
        self::assertSame('list', $ui['index_view']);
        self::assertSame('first', $ui['actions_column']);
        self::assertNull($ui['row_action_display']);
        self::assertNull($ui['region_attribute']);
        self::assertSame([], $ui['form_themes']);
        self::assertSame(DashboardUiConfig::CLASS_KEYS, array_keys($ui['classes']));
        self::assertNull($ui['classes']['button_primary']);
        self::assertSame(DashboardUiConfig::DEFAULT_PARTIALS, $ui['partials']);
        self::assertSame(DashboardUiConfig::DEFAULT_TEMPLATES, $ui['templates']);
        self::assertSame((new DashboardUiConfig())->toArray(), (new DashboardUiConfig($ui))->toArray());

        $custom = $processor->processConfiguration(new Configuration(), [['dashboard' => ['ui' => [
            'index_view'         => 'table',
            'row_action_display' => 'text',
            'classes'            => ['button_primary' => 'btn-primary'],
            'templates'          => ['index' => 'admin/menus/index.html.twig'],
        ]]]])['dashboard']['ui'];
        self::assertSame('table', $custom['index_view']);
        self::assertSame('btn-primary', $custom['classes']['button_primary']);
        self::assertSame('admin/menus/index.html.twig', $custom['templates']['index']);
        self::assertSame(DashboardUiConfig::DEFAULT_TEMPLATES['show'], $custom['templates']['show']);

        $this->expectException(InvalidConfigurationException::class);
        $processor->processConfiguration(new Configuration(), [['dashboard' => ['ui' => ['classes' => ['typo_key' => 'x']]]]]);
    }
}
