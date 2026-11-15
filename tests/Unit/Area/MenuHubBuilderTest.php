<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Area;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuHubBlock;
use Nowo\DashboardMenuBundle\Model\MenuHubCard;
use Nowo\DashboardMenuBundle\Model\MenuHubCardExtras;
use Nowo\DashboardMenuBundle\Model\MenuHubSection;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Service\MenuAreaResolver;
use Nowo\DashboardMenuBundle\Service\MenuHubBuilder;
use Nowo\DashboardMenuBundle\Service\MenuHubCardExtrasProviderInterface;
use Nowo\DashboardMenuBundle\Service\MenuUrlResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

use function array_map;
use function count;

/**
 * The Beacon REQ-ADMIN-003 hub walk (AdministrationHub) as bundle behaviour.
 */
final class MenuHubBuilderTest extends TestCase
{
    public function testWalksSectionsGroupsAndLinksInTreeOrder(): void
    {
        $tree = [
            $this->node($this->section('Overview'), [
                $this->node($this->link('Hub', 'admin_hub')),
                $this->node($this->link('Ops overview', 'admin_ops_overview')),
            ]),
            $this->node($this->section('Look & content'), [
                $this->node($this->link('Appearance', 'admin_appearance')),
                $this->node($this->section('Legal & privacy'), [
                    $this->node($this->link('Legal pages', 'nowo_legal_kit_admin_index')),
                    $this->node($this->section('Deeper'), [$this->node($this->link('Skipped', 'deep'))]),
                ]),
                $this->node($this->section('Empty group'), [$this->node($this->link('Hidden hub', 'organization_hub'))]),
                $this->node($this->link('Menus', 'nowo_dashboard_menu_dashboard_index'), [
                    $this->node($this->link('Menu child', 'menu_child')),
                ]),
                $this->node($this->divider(), [$this->node($this->link('Under divider', 'under_divider'))]),
                $this->node($this->link('Dead link', 'dead'), [], '#'),
                ['item' => 'not an item'],
                $this->node($this->section('Email'), [$this->node($this->link('Templates', 'admin_email_templates'))]),
            ]),
            $this->node($this->section('Only the hub'), [$this->node($this->link('Hub again', 'admin_hub'))]),
        ];

        $sections = $this->builder()->build($tree);

        self::assertSame([
            'Overview'       => ['' => ['admin_ops_overview']],
            'Look & content' => [
                0 => ['' => ['admin_appearance']],
                1 => ['Legal & privacy' => ['nowo_legal_kit_admin_index']],
                2 => ['' => ['nowo_dashboard_menu_dashboard_index', 'menu_child', 'under_divider']],
                3 => ['Email' => ['admin_email_templates']],
            ],
        ], $this->shape($sections));

        $ops = $sections[0]->blocks[0]->cards[0];
        self::assertSame('Ops overview', $ops->label());
        self::assertSame('/developer/ops', $ops->href);
        self::assertSame('admin.hub.ops', $ops->intro);
        self::assertSame('admin-ops', $ops->testid);
        self::assertSame('admin-ops', $ops->tour);
        self::assertNull($ops->badge);
        self::assertTrue($ops->current);
        self::assertSame('administration', $ops->item->menuCode);

        $legal = $sections[1]->blocks[1]->cards[0];
        self::assertSame('legal_outdated', $legal->badge);
        self::assertNull($legal->intro);
        self::assertSame('admin-nowo-legal-kit-admin-index', $legal->testid);
        self::assertFalse($legal->current);
    }

    public function testTopLevelLinksBecomeAnUnlabelledSectionAndProvidersComeFirst(): void
    {
        $provider = new class implements MenuHubCardExtrasProviderInterface {
            public function extrasFor(MenuItemView $item): ?MenuHubCardExtras
            {
                return $item->routeName === 'admin_ops_overview' ? new MenuHubCardExtras(intro: 'from.provider') : null;
            }
        };
        $external = $this->link('Docs', null);
        $external->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        $external->setExternalUrl('https://example.com/docs');

        $tree = [
            ['item' => null],
            $this->node($this->link('Ops overview', 'admin_ops_overview')),
            ['item' => $external, 'children' => 'not a list'],
            $this->node($this->section('Section'), [$this->node($this->link('Users', 'admin_users'))]),
            $this->node($this->link('Trailing', 'trailing')),
            $this->node($this->section('Empty'), []),
            $this->node($this->link('Hub', 'admin_hub')),
        ];

        $sections = $this->builder([$provider])->build($tree);

        self::assertCount(3, $sections);
        self::assertNull($sections[0]->label);
        self::assertSame(['admin_ops_overview', null], array_map(static fn (MenuHubCard $card): ?string => $card->route(), $sections[0]->blocks[0]->cards));
        self::assertSame('Section', $sections[1]->label);
        self::assertNull($sections[2]->label);
        self::assertSame('trailing', $sections[2]->blocks[0]->cards[0]->route());

        $ops = $sections[0]->blocks[0]->cards[0];
        self::assertSame('from.provider', $ops->intro);
        self::assertSame('admin-admin-ops-overview', $ops->testid);
        self::assertNull($ops->tour);
        $docs = $sections[0]->blocks[0]->cards[1];
        self::assertSame('https://example.com/docs', $docs->href);
        self::assertSame('admin-item-0', $docs->testid);
    }

    public function testDefaultsWithoutAreasOrConfiguration(): void
    {
        $builder = new MenuHubBuilder($this->urls(), new MenuAreaResolver());
        $tree    = [$this->node($this->section('S'), [$this->node($this->link('Hub', 'admin_hub'), [], '/developer')])];

        $card = $builder->build($tree)[0]->blocks[0]->cards[0];
        self::assertSame('dashboard-menu-hub-admin-hub', $card->testid);
        self::assertNull($card->intro);
        self::assertSame([], $builder->build([]));
    }

    /**
     * @param list<MenuHubCardExtrasProviderInterface> $providers
     */
    private function builder(array $providers = []): MenuHubBuilder
    {
        $areas = new MenuAreaResolver([
            'development'  => ['menu' => 'administration', 'home_route' => 'admin_hub'],
            'organization' => ['home_route' => 'organization_hub'],
            'dashboard'    => [],
        ]);

        return new MenuHubBuilder(
            $this->urls(),
            $areas,
            $providers,
            [
                'admin_ops_overview'         => ['intro' => 'admin.hub.ops', 'testid' => 'admin-ops', 'tour' => 'admin-ops', 'badge' => ''],
                'nowo_legal_kit_admin_index' => ['badge' => 'legal_outdated'],
            ],
            ['deep'],
            'admin-',
        );
    }

    private function urls(): MenuUrlResolver
    {
        return new MenuUrlResolver(
            self::createStub(UrlGeneratorInterface::class),
            new RequestStack(),
            self::createStub(RouterInterface::class),
            self::createStub(ContainerInterface::class),
        );
    }

    /**
     * @param list<MenuHubSection> $sections
     *
     * @return array<string, array<int|string, mixed>>
     */
    private function shape(array $sections): array
    {
        $shape = [];
        foreach ($sections as $section) {
            $blocks = array_map(
                static fn (MenuHubBlock $block): array => [$block->group ?? '' => array_map(static fn (MenuHubCard $card): ?string => $card->route(), $block->cards)],
                $section->blocks,
            );
            $shape[$section->label ?? ''] = count($blocks) === 1 ? $blocks[0] : $blocks;
        }

        return $shape;
    }

    private function menu(): Menu
    {
        $menu = new Menu();
        $menu->setCode('administration');

        return $menu;
    }

    private function section(string $label): MenuItem
    {
        $item = new MenuItem();
        $item->setMenu($this->menu());
        $item->setLabel($label);
        $item->setItemType(MenuItem::ITEM_TYPE_SECTION);

        return $item;
    }

    private function divider(): MenuItem
    {
        $item = new MenuItem();
        $item->setMenu($this->menu());
        $item->setItemType(MenuItem::ITEM_TYPE_DIVIDER);

        return $item;
    }

    private function link(string $label, ?string $route): MenuItem
    {
        $item = new MenuItem();
        $item->setMenu($this->menu());
        $item->setLabel($label);
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setRouteName($route);

        return $item;
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private function node(MenuItem $item, array $children = [], ?string $href = null): array
    {
        $node  = ['item' => $item, 'children' => $children, 'hasCurrentInBranch' => $item->getRouteName() === 'admin_ops_overview'];
        $route = $item->getRouteName();
        if ($item->getItemType() === MenuItem::ITEM_TYPE_LINK) {
            $node['href'] = $href ?? '/developer/' . ($route === 'admin_ops_overview' ? 'ops' : (string) $route);
        }

        return $node;
    }
}
