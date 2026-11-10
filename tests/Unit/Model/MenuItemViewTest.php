<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Model;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuNodeView;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MenuItemViewTest extends TestCase
{
    public function testFromEntityCopiesPlainValues(): void
    {
        $menu = new Menu();
        $menu->setCode('sidebar');
        (new ReflectionProperty(Menu::class, 'id'))->setValue($menu, 3);

        $parent = new MenuItem();
        $this->setId($parent, 7);

        $item = new MenuItem();
        $this->setId($item, 9);
        $item->setMenu($menu);
        $item->setParent($parent);
        $item->setItemType(MenuItem::ITEM_TYPE_LINK);
        $item->setLabel('Projects');
        $item->setTranslations(['es' => 'Proyectos']);
        $item->setPosition(4);
        $item->setLinkType(MenuItem::LINK_TYPE_ROUTE);
        $item->setRouteName('project_index');
        $item->setRouteParams(['tab' => 'all']);
        $item->setPermissionKeys(['projects.view', 'projects.list']);
        $item->setIsUnanimous(true);
        $item->setIcon('bi:folder');
        $item->setTargetBlank(true);

        $view = MenuItemView::fromEntity($item);

        self::assertSame(9, $view->id);
        self::assertSame(3, $view->menuId);
        self::assertSame('sidebar', $view->menuCode);
        self::assertSame(7, $view->parentId);
        self::assertSame(MenuItem::ITEM_TYPE_LINK, $view->itemType);
        self::assertSame('Projects', $view->label);
        self::assertSame(['es' => 'Proyectos'], $view->translations);
        self::assertSame(4, $view->position);
        self::assertSame(MenuItem::LINK_TYPE_ROUTE, $view->linkType);
        self::assertSame('project_index', $view->routeName);
        self::assertSame(['tab' => 'all'], $view->routeParams);
        self::assertNull($view->externalUrl);
        self::assertNull($view->linkResolver);
        self::assertNull($view->runtimeHref);
        self::assertSame(['projects.view', 'projects.list'], $view->permissionKeys);
        self::assertTrue($view->unanimous);
        self::assertSame('bi:folder', $view->icon);
        self::assertTrue($view->targetBlank);
        self::assertSame('Proyectos', $view->labelForLocale('es'));
        self::assertSame('Projects', $view->labelForLocale('fr'));
    }

    public function testFromEntityWithoutMenuParentOrKeys(): void
    {
        $item = new MenuItem();
        $item->setItemType(MenuItem::ITEM_TYPE_SERVICE);
        $item->setLinkResolver('app.projects');

        $view = MenuItemView::fromEntity($item);

        self::assertNull($view->id);
        self::assertNull($view->menuId);
        self::assertNull($view->menuCode);
        self::assertNull($view->parentId);
        self::assertSame([], $view->translations);
        self::assertSame([], $view->permissionKeys);
        self::assertSame('app.projects', $view->linkResolver);
    }

    public function testNodeListFromDecoratedTree(): void
    {
        $root = new MenuItem();
        $root->setLabel('Root');
        $child = new MenuItem();
        $child->setLabel('Child');

        $nodes = MenuNodeView::listFromTree([
            [
                'item'               => $root,
                'children'           => [['item' => $child, 'children' => [], 'href' => '/child', 'isCurrent' => true, 'hasCurrentInBranch' => true]],
                'isCurrent'          => false,
                'hasCurrentInBranch' => true,
            ],
            ['item' => 'not an item', 'children' => []],
            'garbage',
        ]);

        self::assertCount(1, $nodes);
        self::assertSame('Root', $nodes[0]->item->label);
        self::assertNull($nodes[0]->href);
        self::assertFalse($nodes[0]->current);
        self::assertTrue($nodes[0]->currentInBranch);
        self::assertCount(1, $nodes[0]->children);
        self::assertSame('Child', $nodes[0]->children[0]->item->label);
        self::assertSame('/child', $nodes[0]->children[0]->href);
        self::assertTrue($nodes[0]->children[0]->current);
    }

    public function testNodeListFromPlainTreeIgnoresInvalidChildren(): void
    {
        $item = new MenuItem();
        $item->setLabel('Only');

        $nodes = MenuNodeView::listFromTree([['item' => $item, 'children' => 'x', 'href' => 5]]);

        self::assertCount(1, $nodes);
        self::assertSame([], $nodes[0]->children);
        self::assertNull($nodes[0]->href);
        self::assertFalse($nodes[0]->currentInBranch);
    }

    private function setId(MenuItem $item, int $id): void
    {
        (new ReflectionProperty(MenuItem::class, 'id'))->setValue($item, $id);
    }
}
