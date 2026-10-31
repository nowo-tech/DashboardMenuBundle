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
 * menu.html.twig render options (2.15.0): nested_group_display, group_children_marker, toggle_icon, group_classes.
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
