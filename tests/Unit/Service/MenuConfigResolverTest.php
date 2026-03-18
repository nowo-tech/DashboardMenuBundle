<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Service;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuConfigResolver;
use PHPUnit\Framework\TestCase;

final class MenuConfigResolverTest extends TestCase
{
    public function testGetConfigUsesDefaultsWhenMenuNotFound(): void
    {
        $repo = $this->createMock(MenuRepository::class);
        $repo
            ->expects(self::once())
            ->method('findForCodeWithContextSets')
            ->with('main', [null, []])
            ->willReturn(null);

        $resolver = new MenuConfigResolver(['project' => null], $repo);

        $config = $resolver->getConfig('main');

        self::assertSame('default', $config['connection']);
        self::assertSame('', $config['table_prefix']);
        self::assertNull($config['menu_name']);
        self::assertNull($config['permission_checker']);
        self::assertNull($config['cache_pool']);
        self::assertSame(300, $config['cache_ttl']);

        self::assertSame(
            [
                'menu'                  => 'dashboard-menu',
                'item'                  => '',
                'link'                  => '',
                'children'              => '',
                'section_label'         => 'menu-section-label',
                'class_current'         => 'active',
                'class_branch_expanded' => 'active-branch',
                'class_has_children'    => '',
                'class_expanded'        => '',
                'class_collapsed'       => '',
            ],
            $config['classes'],
        );

        self::assertNull($config['depth_limit']);
        self::assertSame(
            [
                'enabled'      => true,
                'use_ux_icons' => false,
                'default'      => null,
            ],
            $config['icons'],
        );
        self::assertFalse($config['collapsible']);
        self::assertTrue($config['collapsible_expanded']);
        self::assertFalse($config['nested_collapsible']);
        self::assertTrue($config['nested_collapsible_sections']);
        self::assertSame([], $config['context']);
    }

    public function testGetConfigMergesMenuEntityOverrides(): void
    {
        $menu = new Menu();
        $menu->setName('Main menu');
        $menu->setPermissionChecker('app.permission_checker');
        $menu->setDepthLimit(3);
        $menu->setCollapsible(true);
        $menu->setCollapsibleExpanded(false);
        $menu->setNestedCollapsible(true);
        $menu->setNestedCollapsibleSections(false);
        $menu->setContext(['section' => 'admin']);

        $menu->setClassMenu('root-ul');
        $menu->setClassItem('li-item');
        $menu->setClassLink('a-link');
        $menu->setClassChildren('nav flex-column ms-2');
        $menu->setClassCurrent('is-current');
        $menu->setClassBranchExpanded('branch-open');
        $menu->setClassHasChildren('has-children');
        $menu->setClassExpanded('expanded');
        $menu->setClassCollapsed('collapsed');

        $repo = $this->createMock(MenuRepository::class);
        $repo
            ->expects(self::once())
            ->method('findForCodeWithContextSets')
            ->with('main', [['tenant' => 'acme'], null])
            ->willReturn($menu);

        $resolver = new MenuConfigResolver(['project' => 'dashboard'], $repo);

        $config = $resolver->getConfig('main', [['tenant' => 'acme'], null]);

        self::assertSame('Main menu', $config['menu_name']);
        self::assertSame('app.permission_checker', $config['permission_checker']);
        self::assertSame(3, $config['depth_limit']);
        self::assertTrue($config['collapsible']);
        self::assertFalse($config['collapsible_expanded']);
        self::assertTrue($config['nested_collapsible']);
        self::assertFalse($config['nested_collapsible_sections']);
        self::assertSame(['section' => 'admin'], $config['context']);

        self::assertSame(
            [
                'menu'                  => 'root-ul',
                'item'                  => 'li-item',
                'link'                  => 'a-link',
                'children'              => 'nav flex-column ms-2',
                'section_label'         => 'menu-section-label',
                'class_current'         => 'is-current',
                'class_branch_expanded' => 'branch-open',
                'class_has_children'    => 'has-children',
                'class_expanded'        => 'expanded',
                'class_collapsed'       => 'collapsed',
            ],
            $config['classes'],
        );
    }

    public function testGetProjectReturnsConfiguredProjectOrNull(): void
    {
        $repo     = $this->createMock(MenuRepository::class);
        $resolver = new MenuConfigResolver(['project' => 'test-app'], $repo);

        self::assertSame('test-app', $resolver->getProject());

        $resolverWithoutProject = new MenuConfigResolver(['project' => null], $repo);
        self::assertNull($resolverWithoutProject->getProject());
    }

    public function testGetMenuCodesReturnsCodesFromRepository(): void
    {
        $menu1 = new Menu();
        $menu1->setCode('main');
        $menu2 = new Menu();
        $menu2->setCode('footer');

        $repo = $this->createMock(MenuRepository::class);
        $repo
            ->expects(self::once())
            ->method('findAllOrderedByCode')
            ->willReturn([$menu1, $menu2]);

        $resolver = new MenuConfigResolver(['project' => null], $repo);

        self::assertSame(['main', 'footer'], $resolver->getMenuCodes());
    }
}
