<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;

use function substr_count;

/**
 * menu_layout (2.26.0): footer slot (menu_footer.html.twig) and collapsible sections (menu.html.twig).
 * Without a menu_layout entry the markup is untouched (MenuRenderOptionsTemplateTest pins that against 2.14.2).
 */
final class MenuLayoutTemplateTest extends TestCase
{
    private const MENU   = '@NowoDashboardMenuBundle/menu.html.twig';
    private const FOOTER = '@NowoDashboardMenuBundle/menu_footer.html.twig';

    private const LAYOUT = [
        'sidebar' => [
            'storage_key' => 'app.sections',
            'footer'      => [['id' => null, 'route' => 'app_help', 'label' => null]],
            'sections'    => [
                ['id' => null, 'route' => null, 'label' => 'Tools', 'collapsible' => true, 'collapsed' => true],
                ['id' => null, 'route' => null, 'label' => 'Work', 'collapsible' => true, 'collapsed' => true],
                ['id' => null, 'route' => null, 'label' => 'Open', 'collapsible' => true, 'collapsed' => false],
            ],
        ],
    ];

    public function testFooterItemsLeaveTheMainMenuAndRenderInTheirOwnNavLandmark(): void
    {
        $renderer = new DashboardTemplateRenderer(menuLayout: self::LAYOUT);
        $context  = $this->context();

        $main   = $renderer->render(self::MENU, $context);
        $footer = $renderer->render(self::FOOTER, $context);

        self::assertStringNotContainsString('Help', $main);
        self::assertStringContainsString('Home', $main);
        self::assertStringContainsString('<nav class="dashboard-menu-footer" aria-label="Menu footer" data-dashboard-menu-footer="sidebar">', $footer);
        self::assertStringContainsString('<ul class="dashboard-menu sidebar dashboard-menu-footer-list">', $footer);
        self::assertMatchesRegularExpression('/>\s*Help\s*<\/a>/', $footer);
        self::assertStringNotContainsString('Home', $footer);
    }

    public function testFooterLabelCanBeOverridden(): void
    {
        $footer = (new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::FOOTER, $this->context() + ['menuFooterLabel' => 'Support']);

        self::assertStringContainsString('aria-label="Support"', $footer);
    }

    public function testFooterIsEmptyWithoutConfigurationOrTree(): void
    {
        $renderer = new DashboardTemplateRenderer();
        $context  = $this->context();

        self::assertSame('', trim($renderer->render(self::FOOTER, $context)));
        self::assertSame('', trim((new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::FOOTER, ['menuCode' => 'sidebar'])));
        self::assertStringContainsString('Help', $renderer->render(self::MENU, $context));
    }

    public function testFooterUsesTheGivenMenuConfig(): void
    {
        $context                                  = $this->context();
        $context['menuConfig']['classes']['menu'] = 'my-menu';
        $footer                                   = (new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::FOOTER, $context);

        self::assertStringContainsString('<ul class="my-menu sidebar dashboard-menu-footer-list">', $footer);
    }

    public function testCollapsedSectionRendersAButtonWithAriaAndAHiddenPanel(): void
    {
        $html = (new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::MENU, $this->context());

        self::assertStringContainsString(
            '<button type="button" class="menu-section-label menu-section-toggle is-collapsed" aria-expanded="false" aria-controls="sidebar-item-10-section" data-dashboard-menu-section="sidebar:10" data-dashboard-menu-section-default="collapsed" data-dashboard-menu-section-storage-key="app.sections">',
            $html,
        );
        self::assertStringContainsString('<span class="menu-section-toggle-label">Tools</span>', $html);
        self::assertStringContainsString('<ul id="sidebar-item-10-section" class="nav flex-column ms-2" hidden>', $html);
    }

    public function testSectionWithTheCurrentItemAlwaysOpens(): void
    {
        $html = (new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::MENU, $this->context());

        // "Work" is configured collapsed but holds the current item.
        self::assertStringContainsString('aria-expanded="true" aria-controls="sidebar-item-20-section" data-dashboard-menu-section="sidebar:20" data-dashboard-menu-section-default="collapsed" data-dashboard-menu-section-storage-key="app.sections" data-dashboard-menu-section-current>', $html);
        self::assertStringContainsString('<ul id="sidebar-item-20-section" class="nav flex-column ms-2">', $html);
    }

    public function testExpandedByDefaultSectionAndPlainSectionsKeepTheirMarkup(): void
    {
        $html = (new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::MENU, $this->context());

        self::assertStringContainsString('aria-expanded="true" aria-controls="sidebar-item-30-section" data-dashboard-menu-section="sidebar:30" data-dashboard-menu-section-default="expanded"', $html);
        // "Plain" is not listed: the existing label markup, no toggle.
        self::assertStringContainsString('<span class="menu-section-label">Plain</span>', $html);
        // Tools, Work and Open get a toggle; Plain (not listed) and Empty (no children) do not.
        self::assertSame(3, substr_count($html, 'class="menu-section-label menu-section-toggle'));
        self::assertStringContainsString('<span class="menu-section-label">Empty</span>', $html);
    }

    public function testOtherMenusAreUntouched(): void
    {
        $other = (new DashboardTemplateRenderer(menuLayout: self::LAYOUT))->render(self::MENU, ['menuCode' => 'other'] + $this->context());

        self::assertStringNotContainsString('data-dashboard-menu-section', $other);
        self::assertStringContainsString('Help', $other);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        $item = static function (int $id, string $label, string $type = 'link', ?string $route = null): MenuItem {
            $item = DashboardTemplateRenderer::item($id, $label, true, $type);
            $item->setRouteName($route);

            return $item;
        };
        $node = static fn (MenuItem $item, array $children = [], bool $current = false, bool $branch = false): array => [
            'item'               => $item,
            'children'           => $children,
            'href'               => '/' . $item->getId(),
            'isCurrent'          => $current,
            'hasCurrentInBranch' => $branch,
        ];

        return [
            'menuCode' => 'sidebar',
            'menuTree' => [
                $node($item(1, 'Home')),
                $node($item(10, 'Tools', 'section'), [$node($item(11, 'Calc'))]),
                $node($item(20, 'Work', 'section'), [$node($item(21, 'Inbox'), [], true, true)], false, true),
                $node($item(30, 'Open', 'section'), [$node($item(31, 'Docs'))]),
                $node($item(40, 'Plain', 'section'), [$node($item(41, 'Misc'))]),
                $node($item(50, 'Empty', 'section')),
                $node($item(2, 'Help', 'link', 'app_help')),
            ],
            'menuConfig' => [
                'classes' => [
                    'menu'                  => 'dashboard-menu',
                    'item'                  => 'nav-item',
                    'link'                  => 'nav-link',
                    'children'              => 'nav flex-column ms-2',
                    'section_label'         => 'menu-section-label',
                    'class_current'         => 'active',
                    'class_branch_expanded' => 'is-branch-current',
                ],
                'ul_id'                       => null,
                'depth_limit'                 => null,
                'icons'                       => ['enabled' => false, 'use_ux_icons' => false, 'default' => null],
                'icon_size'                   => '1em',
                'item_span_active'            => false,
                'item_span_class'             => '',
                'collapsible'                 => false,
                'collapsible_expanded'        => true,
                'nested_collapsible'          => false,
                'nested_collapsible_sections' => true,
                'menu_name'                   => null,
            ],
        ];
    }
}
