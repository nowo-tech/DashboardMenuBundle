<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuItemBadgeResolver;
use Nowo\DashboardMenuBundle\Tests\Support\RecordingBadgeProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function substr_count;

/**
 * Item badges (2.27.0) in menu.html.twig and menu_footer.html.twig.
 */
final class MenuItemBadgeTemplateTest extends TestCase
{
    private const MENU   = '@NowoDashboardMenuBundle/menu.html.twig';
    private const FOOTER = '@NowoDashboardMenuBundle/menu_footer.html.twig';

    private const LAYOUT = [
        'sidebar' => [
            'footer'   => [['id' => null, 'route' => 'app_help', 'label' => null]],
            'sections' => [['id' => null, 'route' => null, 'label' => 'Hidden', 'collapsible' => true, 'collapsed' => true]],
        ],
    ];

    public function testBadgeRendersAfterTheLabelWithAriaLabelAndTheHubTokens(): void
    {
        $provider = $this->provider(['customers_questions' => 7]);
        $html     = $this->renderer($provider)->render(self::MENU, $this->context());

        self::assertMatchesRegularExpression(
            '/Questions\s*<span class="nowo-ui-badge nowo-ui-badge--warning [^"]*nowo-dashboard-menu__badge" role="img" aria-label="Pending: 7" data-testid="menu-badge-customers_questions">7<\/span>\s*<\/a>/',
            $html,
        );
        self::assertSame(1, substr_count($html, 'nowo-dashboard-menu__badge'));
    }

    public function testNullAndZeroRenderNoBadge(): void
    {
        foreach ([null, 0] as $count) {
            $html = $this->renderer($this->provider(['customers_questions' => $count]))->render(self::MENU, $this->context());

            self::assertStringNotContainsString('nowo-dashboard-menu__badge', $html);
        }
    }

    public function testFailingProviderHidesTheBadgeButTheMenuRenders(): void
    {
        $provider = $this->provider(['customers_questions' => new RuntimeException('down')]);
        $html     = $this->renderer($provider)->render(self::MENU, $this->context());

        self::assertStringNotContainsString('nowo-dashboard-menu__badge', $html);
        self::assertStringContainsString('Questions', $html);
    }

    public function testWithoutProvidersTheMarkupIsUnchanged(): void
    {
        $without = (new DashboardTemplateRenderer())->render(self::MENU, $this->context());
        $empty   = $this->renderer($this->provider([]))->render(self::MENU, $this->context());

        self::assertSame($without, $empty);
        self::assertStringNotContainsString('badge', $without);
    }

    public function testProviderIsNotCalledForItemsInsideACollapsedSection(): void
    {
        $provider = $this->provider(['hidden_item' => 4, 'customers_questions' => 1]);
        $html     = $this->renderer($provider, self::LAYOUT)->render(self::MENU, $this->context());

        self::assertSame(['customers_questions'], $provider->keys);
        self::assertStringNotContainsString('menu-badge-hidden_item', $html);
    }

    public function testItemsAbsentFromTheTreeAreNotAsked(): void
    {
        $provider = $this->provider(['secret' => 3]);
        $this->renderer($provider)->render(self::MENU, $this->context());

        self::assertNotContains('secret', $provider->keys);
    }

    public function testSidebarAndFooterShareOneProviderCallPerItem(): void
    {
        $provider = $this->provider(['customers_questions' => 2, 'app_help' => 5]);
        $renderer = $this->renderer($provider, self::LAYOUT);
        $context  = $this->context();

        $main   = $renderer->render(self::MENU, $context);
        $footer = $renderer->render(self::FOOTER, $context);
        $renderer->render(self::MENU, $context);

        self::assertStringContainsString('menu-badge-customers_questions', $main);
        self::assertStringContainsString('aria-label="Pending: 5" data-testid="menu-badge-app_help">5</span>', $footer);
        self::assertSame(['customers_questions', 'app_help'], $provider->keys);
    }

    public function testTranslatedAriaLabelFollowsTheLocale(): void
    {
        $renderer = new DashboardTemplateRenderer(locale: 'es', itemBadges: $this->resolver($this->provider(['customers_questions' => 3])));

        self::assertStringContainsString('aria-label="Pendientes: 3"', $renderer->render(self::MENU, $this->context()));
    }

    /**
     * @param array<string, int|RuntimeException|null> $values
     */
    private function provider(array $values): RecordingBadgeProvider
    {
        return new RecordingBadgeProvider($values);
    }

    private function resolver(RecordingBadgeProvider $provider): MenuItemBadgeResolver
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        return new MenuItemBadgeResolver(
            new ServiceLocator([
                'customers_questions' => static fn (): object => $provider,
                'hidden_item'         => static fn (): object => $provider,
                'app_help'            => static fn (): object => $provider,
                'secret'              => static fn (): object => $provider,
            ]),
            $stack,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $layout
     */
    private function renderer(RecordingBadgeProvider $provider, array $layout = []): DashboardTemplateRenderer
    {
        return new DashboardTemplateRenderer(menuLayout: $layout, itemBadges: $this->resolver($provider));
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
        $node = static fn (MenuItem $item, array $children = []): array => [
            'item'               => $item,
            'children'           => $children,
            'href'               => '/' . $item->getId(),
            'isCurrent'          => false,
            'hasCurrentInBranch' => false,
        ];

        return [
            'menuCode' => 'sidebar',
            'menuTree' => [
                $node($item(1, 'Questions', 'link', 'customers_questions')),
                $node($item(10, 'Hidden', 'section'), [$node($item(11, 'Inside', 'link', 'hidden_item'))]),
                $node($item(2, 'Help', 'link', 'app_help')),
                $node($item(3, 'Divider', 'divider')),
            ],
            'menuConfig' => [
                'classes' => [
                    'menu'          => 'dashboard-menu',
                    'item'          => 'nav-item',
                    'link'          => 'nav-link',
                    'children'      => 'nav flex-column ms-2',
                    'section_label' => 'menu-section-label',
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
