<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Entity;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use PHPUnit\Framework\TestCase;

final class MenuItemTest extends TestCase
{
    public function testIdStartsNull(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getId());
    }

    public function testMenuGetterSetter(): void
    {
        $menu = new Menu();
        $item = new MenuItem();
        self::assertNull($item->getMenu());
        $item->setMenu($menu);
        self::assertSame($menu, $item->getMenu());
    }

    public function testParentGetterSetter(): void
    {
        $parent = new MenuItem();
        $item   = new MenuItem();
        $item->setParent($parent);
        self::assertSame($parent, $item->getParent());
        $item->setParent(null);
        self::assertNull($item->getParent());
    }

    public function testGetChildrenStartsEmpty(): void
    {
        $item = new MenuItem();
        self::assertCount(0, $item->getChildren());
    }

    public function testPositionGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertSame(0, $item->getPosition());
        $item->setPosition(3);
        self::assertSame(3, $item->getPosition());
    }

    public function testLabelGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertSame('', $item->getLabel());
        $item->setLabel('Home');
        self::assertSame('Home', $item->getLabel());
    }

    public function testGetLabelForLocaleReturnsLabelWhenNoTranslations(): void
    {
        $item = new MenuItem();
        $item->setLabel('Fallback');
        self::assertSame('Fallback', $item->getLabelForLocale('en'));
        self::assertSame('Fallback', $item->getLabelForLocale('es'));
    }

    public function testGetLabelForLocaleReturnsTranslationWhenPresent(): void
    {
        $item = new MenuItem();
        $item->setLabel('Fallback');
        $item->setTranslations(['en' => 'Home', 'es' => 'Inicio']);
        self::assertSame('Home', $item->getLabelForLocale('en'));
        self::assertSame('Inicio', $item->getLabelForLocale('es'));
        self::assertSame('Fallback', $item->getLabelForLocale('fr'));
    }

    public function testTranslationsGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getTranslations());
        $item->setTranslations(['en' => 'Home']);
        self::assertSame(['en' => 'Home'], $item->getTranslations());
        $item->setTranslations(null);
        self::assertNull($item->getTranslations());
    }

    public function testLinkTypeGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertSame(MenuItem::LINK_TYPE_ROUTE, $item->getLinkType());
        $item->setLinkType(MenuItem::LINK_TYPE_EXTERNAL);
        self::assertSame(MenuItem::LINK_TYPE_EXTERNAL, $item->getLinkType());
    }

    public function testRouteNameGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getRouteName());
        $item->setRouteName('app_home');
        self::assertSame('app_home', $item->getRouteName());
    }

    public function testRouteParamsGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getRouteParams());
        $item->setRouteParams(['page' => 'dashboard']);
        self::assertSame(['page' => 'dashboard'], $item->getRouteParams());
    }

    public function testExternalUrlGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getExternalUrl());
        $item->setExternalUrl('https://example.com');
        self::assertSame('https://example.com', $item->getExternalUrl());
    }

    public function testPermissionKeyGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getPermissionKey());
        $item->setPermissionKey('admin');
        self::assertSame('admin', $item->getPermissionKey());
    }

    public function testIconGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertNull($item->getIcon());
        $item->setIcon('heroicons:home');
        self::assertSame('heroicons:home', $item->getIcon());
    }

    public function testItemTypeGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertSame(MenuItem::ITEM_TYPE_LINK, $item->getItemType());
        $item->setItemType(MenuItem::ITEM_TYPE_SECTION);
        self::assertSame(MenuItem::ITEM_TYPE_SECTION, $item->getItemType());
        $item->setItemType(MenuItem::ITEM_TYPE_DIVIDER);
        self::assertSame(MenuItem::ITEM_TYPE_DIVIDER, $item->getItemType());
    }

    public function testTargetBlankGetterSetter(): void
    {
        $item = new MenuItem();
        self::assertFalse($item->getTargetBlank());
        $item->setTargetBlank(true);
        self::assertTrue($item->getTargetBlank());
    }
}
