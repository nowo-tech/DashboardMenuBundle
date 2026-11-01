<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function substr_count;

/**
 * menu.html.twig render options (2.15.0): nested_group_display, group_children_marker, toggle_icon, group_classes;
 * nested_default (2.16.0, menu_collapse.nested_default): initial state of the nested collapsible groups.
 * With the defaults (or the options absent from menuConfig) the markup is byte-identical to the 2.14.2 template,
 * kept as tests/Fixtures/menu-2.14.2.html.twig: DashboardMenu is pinned by host apps that assert on that markup.
 */
final class MenuRenderOptionsTemplateTest extends TestCase
{
    private const MENU   = '@NowoDashboardMenuBundle/menu.html.twig';
    private const LEGACY = 'legacy/menu-2.14.2.html.twig';

    private const DEFAULTS = [
        'nested_group_display'  => 'collapsible',
        'group_children_marker' => 'none',
        'toggle_icon'           => null,
        'group_classes'         => ['toggle' => 'menu-group-toggle', 'label' => 'menu-group-label', 'chevron' => 'menu-group-chevron', 'dot' => 'menu-group-dot'],
        'nested_default'        => 'expanded',
    ];

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function defaultContexts(): iterable
    {
        foreach ([false, true] as $nestedCollapsible) {
            foreach ([false, true] as $itemSpan) {
                foreach ([false, true] as $icons) {
                    $name = ($nestedCollapsible ? 'nested' : 'flat') . ($itemSpan ? '+span' : '') . ($icons ? '+icons' : '');
                    yield $name => [self::context($nestedCollapsible, $itemSpan, $icons)];
                    yield $name . ' (explicit defaults)' => [self::context($nestedCollapsible, $itemSpan, $icons, self::DEFAULTS)];
                }
            }
        }
        $collapsibleMenu                              = self::context(true, false, true);
        $collapsibleMenu['menuConfig']['collapsible'] = true;
        yield 'collapsible menu' => [$collapsibleMenu];
        $depthLimit                              = self::context(true, false, true);
        $depthLimit['menuConfig']['depth_limit'] = 2;
        yield 'depth limit' => [$depthLimit];
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('defaultContexts')]
    public function testDefaultsRenderTheSameBytesAsTheLegacyTemplate(array $context): void
    {
        $renderer = $this->renderer();

        $html = $renderer->render(self::MENU, $context);

        self::assertSame($renderer->render(self::LEGACY, $context), $html);
        self::assertStringNotContainsString('menu-group-', $html);
    }

    public function testLinkRowRendersNestedSectionGroupsAsToggleRows(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, ['nested_group_display' => 'link_row']));

        // The nested group "Reports" (a section in the section "Work") is one <button> in the link style, in the branch.
        self::assertStringContainsString(
            '<button type="button" class="nav-link ps-0 menu-group-toggle is-branch-current" data-bs-toggle="collapse" data-bs-target="#sidebar-item-12" aria-expanded="true" aria-controls="sidebar-item-12">',
            $html,
        );
        self::assertStringContainsString('<svg data-icon="bi:bar-chart"></svg>', $html);
        self::assertStringContainsString('<span class="menu-group-label">Reports</span>', $html);
        self::assertStringContainsString('<svg class="menu-group-chevron" xmlns="http://www.w3.org/2000/svg"', $html);
        self::assertStringNotContainsString('menu-section-label-link min-w-0 d-inline-flex align-items-center text-decoration-none" tabindex="-1">Reports</a>', $html);
        // A group with a legacy icon name and outside the current branch.
        self::assertStringContainsString('<button type="button" class="nav-link ps-0 menu-group-toggle" data-bs-toggle="collapse" data-bs-target="#sidebar-item-13"', $html);
        self::assertStringContainsString('<span class="menu-item-icon" data-icon="archive"', $html);
        // Top-level sections keep their heading; the collapse wrapper of the group is unchanged.
        self::assertStringContainsString('tabindex="-1">Work</a>', $html);
        self::assertStringContainsString('<div id="sidebar-item-12" class="collapse show">', $html);
        self::assertSame(2, substr_count($html, 'menu-group-toggle'));
        // Links keep their icons (no marker).
        self::assertStringNotContainsString('menu-group-dot', $html);
    }

    public function testLinkRowWithoutIconsAndWithoutBranchClass(): void
    {
        $context                                                   = self::context(true, false, false, ['nested_group_display' => 'link_row']);
        $context['menuConfig']['classes']['class_branch_expanded'] = '';
        $html                                                      = $this->renderer()->render(self::MENU, $context);

        self::assertStringContainsString('<button type="button" class="nav-link ps-0 menu-group-toggle" data-bs-toggle="collapse" data-bs-target="#sidebar-item-12"', $html);
        self::assertStringNotContainsString('data-icon="bi:bar-chart"', $html);
    }

    public function testDotMarkerReplacesTheIconOfLinksInsideSections(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, ['group_children_marker' => 'dot', 'group_classes' => ['dot' => 'app-nav__dot']]));

        // Links whose parent is a section (Inbox, Weekly, Monthly, Old) get the dot; the top-level link keeps its icon.
        self::assertSame(4, substr_count($html, '<span class="app-nav__dot" aria-hidden="true"></span>'));
        self::assertStringNotContainsString('data-icon="bi:graph-up"', $html);
        self::assertStringContainsString('<svg data-icon="bi:house"></svg>', $html);
    }

    public function testDotMarkerSkipsChildrenOfFlattenedSections(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(false, false, true, ['group_children_marker' => 'dot']));

        // "Help" has sectionCollapsible false: its children are flattened siblings (parent type ''), no dot.
        self::assertStringContainsString('data-icon="bi:question"', $html);
        self::assertSame(4, substr_count($html, '<span class="menu-group-dot" aria-hidden="true"></span>'));
    }

    public function testToggleIconRendersUxIconsForEveryChevron(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, ['toggle_icon' => 'tabler:chevron-down']));

        self::assertStringNotContainsString('<path d="M2.5 5.5L8 11l5.5-5.5H2.5z"/>', $html);
        self::assertSame(4, substr_count($html, '<span class="menu-nested-toggle-icon" aria-hidden="true"><svg data-icon="tabler:chevron-down"></svg></span>'));

        // In link_row mode the group chevron uses the icon too (Beacon's sidebar: every option together).
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, [
            'nested_group_display'  => 'link_row',
            'group_children_marker' => 'dot',
            'toggle_icon'           => 'tabler:chevron-down',
        ]));
        self::assertStringNotContainsString('<path d="M2.5 5.5L8 11l5.5-5.5H2.5z"/>', $html);
        self::assertSame(4, substr_count($html, '<svg data-icon="tabler:chevron-down"></svg>'));
    }

    public function testExpandedGlobalDefaultKeepsTheLegacyBytes(): void
    {
        $renderer = new DashboardTemplateRenderer(hostTemplates: [
            self::LEGACY => (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/menu-2.14.2.html.twig'),
        ], menuCollapse: ['nested_default' => 'expanded']);
        $context = self::context(true, false, true);

        self::assertSame($renderer->render(self::LEGACY, $context), $renderer->render(self::MENU, $context));
    }

    public function testCollapsedRendersEveryNestedGroupClosed(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, ['nested_default' => 'collapsed']));

        self::assertStringNotContainsString('class="collapse show"', $html);
        self::assertStringNotContainsString('aria-expanded="true"', $html);
        foreach ([10, 12, 13, 2] as $id) {
            self::assertStringContainsString('<div id="sidebar-item-' . $id . '" class="collapse">', $html);
            self::assertStringContainsString(
                'class="btn btn-link btn-sm p-0 ms-1 menu-nested-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#sidebar-item-' . $id . '" aria-expanded="false" aria-controls="sidebar-item-' . $id . '"',
                $html,
            );
        }
        self::assertSame(4, substr_count($html, 'aria-expanded="false"'));
        // No script markers unless menu_collapse is enabled.
        self::assertStringNotContainsString('data-dashboard-menu-collapse', $html);
    }

    public function testCurrentOpensOnlyTheBranchOfTheCurrentItem(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, ['nested_default' => 'current']));

        // "Work" > "Reports" > "Weekly" (current): both groups open, toggles expanded without "collapsed".
        foreach ([10, 12] as $id) {
            self::assertStringContainsString('<div id="sidebar-item-' . $id . '" class="collapse show">', $html);
            self::assertStringContainsString('menu-nested-toggle" data-bs-toggle="collapse" data-bs-target="#sidebar-item-' . $id . '" aria-expanded="true" aria-controls="sidebar-item-' . $id . '"', $html);
        }
        // "Archive" (sibling group) and "Settings" (link with children) are outside the branch: closed.
        foreach ([13, 2] as $id) {
            self::assertStringContainsString('<div id="sidebar-item-' . $id . '" class="collapse">', $html);
            self::assertStringContainsString('menu-nested-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#sidebar-item-' . $id . '" aria-expanded="false" aria-controls="sidebar-item-' . $id . '"', $html);
        }
    }

    public function testLinkRowGroupsFollowTheNestedDefault(): void
    {
        $html = $this->renderer()->render(self::MENU, self::context(true, false, true, ['nested_group_display' => 'link_row', 'nested_default' => 'current']));

        self::assertStringContainsString(
            '<button type="button" class="nav-link ps-0 menu-group-toggle is-branch-current" data-bs-toggle="collapse" data-bs-target="#sidebar-item-12" aria-expanded="true" aria-controls="sidebar-item-12">',
            $html,
        );
        self::assertStringContainsString(
            '<button type="button" class="nav-link ps-0 menu-group-toggle collapsed" data-bs-toggle="collapse" data-bs-target="#sidebar-item-13" aria-expanded="false" aria-controls="sidebar-item-13">',
            $html,
        );
        self::assertStringContainsString('<div id="sidebar-item-13" class="collapse">', $html);
    }

    public function testCurrentWithDeepNesting(): void
    {
        $item = static fn (int $id, string $type = 'link'): MenuItem => DashboardTemplateRenderer::item($id, 'Item ' . $id, true, $type);
        $node = static fn (int $id, array $children = [], bool $current = false, bool $branch = false, string $type = 'link'): array => [
            'item'               => $item($id, $type),
            'children'           => $children,
            'href'               => '/' . $id,
            'isCurrent'          => $current,
            'hasCurrentInBranch' => $branch || $current,
        ];
        $context             = self::context(true, false, false, ['nested_default' => 'current']);
        $context['menuTree'] = [
            // Level 1 > 2 > 3 > 4 (current at level 4): every ancestor group open.
            $node(1, [$node(11, [$node(111, [$node(1111, [], true)], false, true)], false, true), $node(12, [$node(121)])], false, true),
            // A deep branch without the current item: every group closed.
            $node(2, [$node(21, [$node(211, [$node(2111)])])]),
            // The current item itself has children: its own group opens (its branch contains the current item).
            $node(3, [$node(31)], true),
        ];

        $html = $this->renderer()->render(self::MENU, $context);

        foreach ([1, 11, 111, 3] as $id) {
            self::assertStringContainsString('<div id="sidebar-item-' . $id . '" class="collapse show">', $html);
        }
        foreach ([12, 2, 21, 211] as $id) {
            self::assertStringContainsString('<div id="sidebar-item-' . $id . '" class="collapse">', $html);
            self::assertStringContainsString('data-bs-target="#sidebar-item-' . $id . '" aria-expanded="false" aria-controls="sidebar-item-' . $id . '"', $html);
        }
    }

    public function testPrecedenceIncludeVariableThenMenuConfigThenGlobal(): void
    {
        $global = new DashboardTemplateRenderer(menuCollapse: ['nested_default' => 'collapsed']);

        // Global only.
        self::assertStringNotContainsString('class="collapse show"', $global->render(self::MENU, self::context(true, false, false)));
        // menuConfig.nested_default (e.g. dashboard_menu_config(...)|merge) beats the global value.
        self::assertStringContainsString('class="collapse show"', $global->render(self::MENU, self::context(true, false, false, ['nested_default' => 'expanded'])));
        // The include variable beats both; null falls through.
        $html = $global->render(self::MENU, self::context(true, false, false, ['nested_default' => 'expanded']) + ['menuCollapseNestedDefault' => 'current']);
        self::assertStringContainsString('<div id="sidebar-item-12" class="collapse show">', $html);
        self::assertStringContainsString('<div id="sidebar-item-13" class="collapse">', $html);
        $html = $global->render(self::MENU, self::context(true, false, false) + ['menuCollapseNestedDefault' => null]);
        self::assertStringNotContainsString('class="collapse show"', $html);
        // Unknown values render expanded (the config tree validates the global value).
        self::assertStringNotContainsString('aria-expanded="false"', $global->render(self::MENU, self::context(true, false, false) + ['menuCollapseNestedDefault' => 'bogus']));
    }

    public function testCurrentBranchPanelsAreMarkedForTheScriptWhenMenuCollapseIsOn(): void
    {
        $renderer = new DashboardTemplateRenderer(menuCollapse: ['enabled' => true, 'storage_key' => '', 'nested_default' => 'current']);
        $html     = $renderer->render(self::MENU, self::context(true, false, false));

        self::assertStringContainsString('data-dashboard-menu-collapse-storage-key=""', $html);
        self::assertStringContainsString('<div id="sidebar-item-10" class="collapse show" data-dashboard-menu-collapse-current>', $html);
        self::assertStringContainsString('<div id="sidebar-item-12" class="collapse show" data-dashboard-menu-collapse-current>', $html);
        self::assertStringContainsString('<div id="sidebar-item-13" class="collapse">', $html);
        self::assertSame(2, substr_count($html, 'data-dashboard-menu-collapse-current'));

        // Off per include: no markers.
        self::assertStringNotContainsString('data-dashboard-menu-collapse', $renderer->render(self::MENU, self::context(true, false, false) + ['menuCollapse' => false]));
    }

    private function renderer(): DashboardTemplateRenderer
    {
        return new DashboardTemplateRenderer(hostTemplates: [
            self::LEGACY => (string) file_get_contents(dirname(__DIR__, 2) . '/Fixtures/menu-2.14.2.html.twig'),
        ]);
    }

    /**
     * Tree: a link, a section "Work" (two nested section groups with links, one in the current branch), a link with
     * children, a divider and a section "Help" whose children are flattened (sectionCollapsible false).
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function context(bool $nestedCollapsible, bool $itemSpan, bool $icons, array $options = []): array
    {
        $item = static function (int $id, string $label, string $type = 'link', ?string $icon = null, ?bool $sectionCollapsible = null): MenuItem {
            $item = DashboardTemplateRenderer::item($id, $label, true, $type);
            $item->setIcon($icon);
            $item->setSectionCollapsible($sectionCollapsible);

            return $item;
        };
        $node = static fn (MenuItem $item, array $children = [], bool $current = false, bool $branch = false): array => [
            'item'               => $item,
            'children'           => $children,
            'href'               => '/' . $item->getId(),
            'isCurrent'          => $current,
            'hasCurrentInBranch' => $branch,
        ];

        $tree = [
            $node($item(1, 'Home', 'link', 'bi:house')),
            $node($item(10, 'Work', 'section', null, true), [
                $node($item(11, 'Inbox', 'link', 'bi:inbox')),
                $node($item(12, 'Reports', 'section', 'bi:bar-chart', true), [
                    $node($item(121, 'Weekly', 'link', 'bi:graph-up'), [], true, true),
                    $node($item(122, 'Monthly', 'link', 'calendar')),
                ], false, true),
                $node($item(13, 'Archive', 'section', 'archive'), [
                    $node($item(131, 'Old', 'link')),
                ]),
            ], false, true),
            $node($item(2, 'Settings', 'link', 'bi:gear'), [$node($item(21, 'Profile', 'link', 'bi:person'))]),
            $node($item(3, '', 'divider')),
            $node($item(30, 'Help', 'section', null, false), [
                $node($item(31, 'Docs', 'link', 'bi:question')),
            ]),
        ];

        return [
            'menuCode'   => 'sidebar',
            'menuTree'   => $tree,
            'menuConfig' => [
                'classes' => [
                    'menu'                  => 'dashboard-menu',
                    'item'                  => 'nav-item',
                    'link'                  => 'nav-link',
                    'children'              => 'nav flex-column ms-2',
                    'section_child_link'    => 'nav-link ps-0',
                    'section_label'         => 'menu-section-label',
                    'class_current'         => 'active',
                    'class_branch_expanded' => 'is-branch-current',
                ],
                'ul_id'                       => null,
                'depth_limit'                 => null,
                'icons'                       => ['enabled' => $icons, 'use_ux_icons' => false, 'default' => null],
                'icon_size'                   => '1em',
                'item_span_active'            => $itemSpan,
                'item_span_class'             => 'd-flex align-items-center',
                'collapsible'                 => false,
                'collapsible_expanded'        => true,
                'nested_collapsible'          => $nestedCollapsible,
                'nested_collapsible_sections' => true,
                'menu_name'                   => null,
            ] + $options,
        ];
    }
}
