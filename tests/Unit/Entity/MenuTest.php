<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Entity;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MenuTest extends TestCase
{
    public function testIdStartsNull(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getId());
    }

    public function testCodeGetterSetter(): void
    {
        $menu = new Menu();
        self::assertSame('', $menu->getCode());
        $menu->setCode('sidebar');
        self::assertSame('sidebar', $menu->getCode());
    }

    public function testNameGetterSetter(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getName());
        $menu->setName('Main sidebar');
        self::assertSame('Main sidebar', $menu->getName());
        $menu->setName(null);
        self::assertNull($menu->getName());
    }

    public function testIconGetterSetter(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getIcon());
        $menu->setIcon('heroicons:bars-3');
        self::assertSame('heroicons:bars-3', $menu->getIcon());
    }

    public function testClassGettersSetters(): void
    {
        $menu = new Menu();
        $menu->setClassMenu('nav flex-column');
        $menu->setClassItem('nav-item');
        $menu->setClassLink('nav-link');
        $menu->setClassChildren('nav ms-2');
        $menu->setClassCurrent('active');
        $menu->setClassBranchExpanded('expand');
        $menu->setClassHasChildren('has-children');
        $menu->setClassExpanded('open');
        $menu->setClassCollapsed('closed');

        self::assertSame('nav flex-column', $menu->getClassMenu());
        self::assertSame('nav-item', $menu->getClassItem());
        self::assertSame('nav-link', $menu->getClassLink());
        self::assertSame('nav ms-2', $menu->getClassChildren());
        self::assertSame('active', $menu->getClassCurrent());
        self::assertSame('expand', $menu->getClassBranchExpanded());
        self::assertSame('has-children', $menu->getClassHasChildren());
        self::assertSame('open', $menu->getClassExpanded());
        self::assertSame('closed', $menu->getClassCollapsed());
    }

    public function testPermissionCheckerGetterSetter(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getPermissionChecker());
        $menu->setPermissionChecker('app.menu_checker');
        self::assertSame('app.menu_checker', $menu->getPermissionChecker());
    }

    public function testDepthLimitGetterSetter(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getDepthLimit());
        $menu->setDepthLimit(2);
        self::assertSame(2, $menu->getDepthLimit());
    }

    public function testCollapsibleGettersSetters(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getCollapsible());
        self::assertNull($menu->getCollapsibleExpanded());
        $menu->setCollapsible(true);
        $menu->setCollapsibleExpanded(false);
        self::assertTrue($menu->getCollapsible());
        self::assertFalse($menu->getCollapsibleExpanded());
    }

    public function testNestedCollapsibleGetterSetter(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getNestedCollapsible());
        $menu->setNestedCollapsible(true);
        self::assertTrue($menu->getNestedCollapsible());
    }

    public function testGetItemsStartsEmpty(): void
    {
        $menu = new Menu();
        self::assertCount(0, $menu->getItems());
    }

    public function testAddItemAddsItemAndSetsMenu(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        $menu->addItem($item);
        self::assertCount(1, $menu->getItems());
        self::assertSame($menu, $item->getMenu());
    }

    public function testAddItemTwiceDoesNotDuplicate(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        $menu->addItem($item);
        $menu->addItem($item);
        self::assertCount(1, $menu->getItems());
    }

    public function testRemoveItemDetachesItem(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        $menu->addItem($item);
        $menu->removeItem($item);
        self::assertCount(0, $menu->getItems());
        self::assertNull($item->getMenu());
    }

    public function testRemoveItemWhenNotInCollectionDoesNotClearMenu(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        $item->setMenu($menu);
        $menu->removeItem($item);
        self::assertSame($menu, $item->getMenu());
    }

    public function testContextGetterSetter(): void
    {
        $menu = new Menu();
        self::assertNull($menu->getContext());
        $menu->setContext(['locale' => 'en']);
        self::assertSame(['locale' => 'en'], $menu->getContext());
        self::assertSame('{"locale":"en"}', $menu->getContextKey());
    }

    public function testContextKeyEmptyWhenContextNullOrEmpty(): void
    {
        $menu = new Menu();
        self::assertSame('', $menu->getContextKey());
        $menu->setContext(null);
        self::assertSame('', $menu->getContextKey());
        $menu->setContext([]);
        self::assertSame('', $menu->getContextKey());
    }

    public function testCanonicalContextKeyStatic(): void
    {
        self::assertSame('', Menu::canonicalContextKey(null));
        self::assertSame('', Menu::canonicalContextKey([]));
        self::assertSame('{"a":"1","b":"2"}', Menu::canonicalContextKey(['b' => '2', 'a' => '1']));
    }

    public function testEnsureContextKeyBackfillsFromContext(): void
    {
        $menu = new Menu();
        $ref  = new ReflectionProperty(Menu::class, 'contextKey');
        $ref->setValue($menu, '');
        $refContext = new ReflectionProperty(Menu::class, 'context');
        $refContext->setValue($menu, ['x' => 'y']);
        $menu->ensureContextKey();
        self::assertSame('{"x":"y"}', $menu->getContextKey());
    }

    public function testEnsureContextKeyDoesNothingWhenContextKeyAlreadySet(): void
    {
        $menu = new Menu();
        $ref  = new ReflectionProperty(Menu::class, 'contextKey');
        $ref->setValue($menu, 'existing');
        $refContext = new ReflectionProperty(Menu::class, 'context');
        $refContext->setValue($menu, ['a' => 'b']);
        $menu->ensureContextKey();
        self::assertSame('existing', $menu->getContextKey());
    }

    public function testEnsureContextKeyDoesNothingWhenContextNull(): void
    {
        $menu = new Menu();
        $ref  = new ReflectionProperty(Menu::class, 'contextKey');
        $ref->setValue($menu, '');
        $menu->ensureContextKey();
        self::assertSame('', $menu->getContextKey());
    }

    public function testBaseGetterSetter(): void
    {
        $menu = new Menu();
        self::assertFalse($menu->isBase());
        $menu->setBase(true);
        self::assertTrue($menu->isBase());
    }
}
