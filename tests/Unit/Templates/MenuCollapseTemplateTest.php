<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Bootstrap-free nested collapse (2.6.0): menu.html.twig emits the data-dashboard-menu-collapse* attributes
 * only when menu_collapse.enabled (or the menuCollapse include variable) asks for it; the script partial is an
 * external deferred script carrying the CSP nonce.
 */
final class MenuCollapseTemplateTest extends TestCase
{
    private const MENU   = '@NowoDashboardMenuBundle/menu.html.twig';
    private const SCRIPT = '@NowoDashboardMenuBundle/_menu_collapse_script.html.twig';

    public function testDefaultMarkupHasNoCollapseAttributes(): void
    {
        $html = (new DashboardTemplateRenderer())->render(self::MENU, $this->context());

        self::assertStringNotContainsString('data-dashboard-menu-collapse', $html);
        self::assertStringContainsString('<ul class="dashboard-menu sidebar dashboard-menu-tree">', $html);
        self::assertStringContainsString('data-bs-toggle="collapse" data-bs-target="#sidebar-item-1" aria-expanded="true" aria-controls="sidebar-item-1"', $html);
    }

    public function testEnabledConfigMarksTheMenuRoot(): void
    {
        $renderer = new DashboardTemplateRenderer(menuCollapse: ['enabled' => true, 'mode' => 'always', 'storage_key' => 'beacon.navCollapse']);
        $html     = $renderer->render(self::MENU, $this->context(['class_branch_expanded' => 'is-branch-current extra']));

        self::assertStringContainsString(
            '<ul class="dashboard-menu sidebar dashboard-menu-tree" data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-mode="always" data-dashboard-menu-collapse-storage-key="beacon.navCollapse" data-dashboard-menu-collapse-branch-class="is-branch-current">',
            $html,
        );
        self::assertSame(1, substr_count($html, 'data-dashboard-menu-collapse="'));
    }

    public function testIncludeVariablesOverrideTheConfig(): void
    {
        $renderer = new DashboardTemplateRenderer(menuCollapse: ['enabled' => true]);

        $off = $renderer->render(self::MENU, $this->context() + ['menuCollapse' => false]);
        self::assertStringNotContainsString('data-dashboard-menu-collapse', $off);

        $on = (new DashboardTemplateRenderer())->render(self::MENU, $this->context(['class_branch_expanded' => '']) + [
            'menuCollapse'           => true,
            'menuCollapseMode'       => 'auto',
            'menuCollapseStorageKey' => '',
        ]);
        self::assertStringContainsString(
            'data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-mode="auto" data-dashboard-menu-collapse-storage-key="">',
            $on,
        );
        self::assertStringNotContainsString('data-dashboard-menu-collapse-branch-class', $on);
    }

    public function testConfigDefaultsAndDefaultBranchClass(): void
    {
        $html = (new DashboardTemplateRenderer(menuCollapse: ['enabled' => true]))->render(self::MENU, $this->context());

        self::assertStringContainsString(
            'data-dashboard-menu-collapse="sidebar" data-dashboard-menu-collapse-mode="auto" data-dashboard-menu-collapse-storage-key="nowo.dashboardMenu.navCollapse" data-dashboard-menu-collapse-branch-class="active-branch"',
            $html,
        );
    }

    public function testCollapsibleMenuMarksTheWrapper(): void
    {
        $context                              = $this->context();
        $context['menuConfig']['collapsible'] = true;
        $context['menuConfig']['menu_name']   = 'Sidebar';
        $html                                 = (new DashboardTemplateRenderer(menuCollapse: ['enabled' => true]))->render(self::MENU, $context);

        self::assertStringContainsString('<div class="dashboard-menu-collapsible" data-dashboard-menu-collapse="sidebar"', $html);
        self::assertStringContainsString('<ul class="dashboard-menu sidebar dashboard-menu-tree">', $html);
        self::assertSame(1, substr_count($html, 'data-dashboard-menu-collapse="'));
    }

    public function testScriptPartialIsExternalAndCarriesTheNonce(): void
    {
        $renderer = new DashboardTemplateRenderer();
        self::assertSame(
            '<script src="js/menu-collapse.js" defer></script>',
            trim($renderer->render(self::SCRIPT)),
        );

        $request = Request::create('/');
        $request->attributes->set('csp_nonce', 'n0nce');
        $stack = new RequestStack();
        $stack->push($request);
        $app = new AppVariable();
        $app->setRequestStack($stack);

        self::assertSame(
            '<script src="js/menu-collapse.js" defer nonce="n0nce"></script>',
            trim($renderer->render(self::SCRIPT, ['app' => $app])),
        );
    }

    /**
     * Two parents with children (nested_collapsible), the first in the current branch.
     *
     * @param array<string, string> $classes
     *
     * @return array<string, mixed>
     */
    private function context(array $classes = []): array
    {
        $node = (static fn (int $id, string $label, array $children = [], bool $branch = false): array => [
            'item'               => DashboardTemplateRenderer::item($id, $label, true),
            'children'           => $children,
            'href'               => '/' . $id,
            'isCurrent'          => false,
            'hasCurrentInBranch' => $branch,
        ]);

        return [
            'menuCode' => 'sidebar',
            'menuTree' => [
                $node(1, 'One', [$node(11, 'One.1')], true),
                $node(2, 'Two', [$node(21, 'Two.1')]),
            ],
            'menuConfig' => [
                'classes'                     => $classes,
                'ul_id'                       => null,
                'depth_limit'                 => null,
                'icons'                       => ['enabled' => false, 'use_ux_icons' => false, 'default' => null],
                'collapsible'                 => false,
                'collapsible_expanded'        => true,
                'nested_collapsible'          => true,
                'nested_collapsible_sections' => true,
                'menu_name'                   => null,
            ],
        ];
    }
}
