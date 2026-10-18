<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\View;

use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\DashboardMenuBundle\Tests\Support\DashboardPageFixtures;
use Nowo\DashboardMenuBundle\View\DashboardPages;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormView;

use function array_map;

final class DashboardPagesTest extends TestCase
{
    public function testIndexPageBuildsRowsHeaderAndFilters(): void
    {
        $sidebar = DashboardPageFixtures::sidebar();
        $page    = DashboardPageFixtures::pages()->indexPage([$sidebar, new Menu(), DashboardPageFixtures::footer()], [1 => 3], new FormView(), 'side', null, [1 => new FormView()]);

        self::assertSame(['menu-1', 'menu-2'], array_keys($page->rows));
        self::assertSame(3, $page->rows['menu-1']['items']);
        self::assertSame(0, $page->rows['menu-2']['items']);
        self::assertSame('/admin/menus', $page->filtersClearHref);
        self::assertNotNull($page->filtersClear);
        self::assertSame(['export', 'import', 'create'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
        self::assertSame(DashboardPages::BUNDLE, $page->bundle);

        $actions = $page->rowActions($page->rows['menu-1'], 'menu-1');
        self::assertSame(['view', 'copy', 'edit', 'config', 'delete'], array_map(static fn (Action $a): string => $a->kind, $actions));
        self::assertSame(DashboardPages::DIALOG_MENU, $actions[3]->attributes[DashboardPages::DIALOG_ATTRIBUTE]);
        self::assertSame(['%name%' => 'sidebar — Main sidebar'], $this->label($actions[4]->confirm?->message)->params);

        $base = $page->rowActions($page->rows['menu-2'], 'menu-2');
        self::assertSame(['view', 'copy', 'edit'], array_map(static fn (Action $a): string => $a->kind, $base), 'Base menus cannot be configured or deleted.');

        $plain = DashboardPageFixtures::pages()->indexPage([], [], new FormView());
        self::assertNull($plain->filtersClear);
        self::assertNull($plain->filtersClearHref);
    }

    public function testLegacyChromeHooksStillApply(): void
    {
        $pages = DashboardPageFixtures::pages(new DashboardUiConfig([
            'row_action_display'     => 'text',
            'header_action_variants' => ['new_menu' => 'ghost', 'export_all' => 'danger'],
            'form_themes'            => ['admin/form_theme.html.twig'],
        ]));
        $page = $pages->indexPage([DashboardPageFixtures::sidebar()], [], new FormView());

        self::assertSame('danger', $page->header->actions[0]->variant);
        self::assertNull($page->header->actions[1]->variant);
        self::assertSame('neutral', $page->header->actions[2]->variant);
        foreach ($page->rowActions($page->rows['menu-1'], 'menu-1') as $action) {
            self::assertSame('text', $action->display);
        }
        self::assertSame(['admin/form_theme.html.twig'], $pages->importFormPage(new FormView())->formThemes);
    }

    public function testShowPageListsPropertiesAndCssClasses(): void
    {
        $menu = DashboardPageFixtures::sidebar();
        $menu->setBase(true);
        $menu->setCollapsibleExpanded(false);
        $menu->setNestedCollapsibleSections(true);
        $menu->setUlId('main-nav');
        $page = DashboardPageFixtures::pages()->showPage($menu, true, new FormView(), new FormView(), 50);

        self::assertSame('sidebar', $page->header->title);
        self::assertSame('Main sidebar', $page->header->intro);
        self::assertCount(2, $page->header->breadcrumbs);
        self::assertSame(['custom', 'export', 'copy', 'edit', 'custom', 'custom', 'create'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions), 'Base menu: no configuration action.');
        self::assertSame(['%step%' => 50], $this->label($page->header->actions[4]->label)->params);

        [$properties, $classes] = $page->sections;
        $labels                 = array_map(fn (DetailItem $i): string => $this->label($i->label)->key, $properties->items);
        self::assertSame(['dashboard.base', 'dashboard.context', 'dashboard.permission_checker', 'dashboard.depth_limit', 'dashboard.collapsible', 'dashboard.collapsible_expanded', 'dashboard.nested_collapsible', 'form.menu_type.nested_collapsible_sections.label'], $labels);
        self::assertSame('dashboard.no', $this->label($properties->items[5]->value)->key);
        self::assertSame(['menu', 'ul_id', 'link'], array_map(static fn (DetailItem $i): string => $i->label instanceof Label ? $i->label->key : $i->label, $classes->items));

        $bare = DashboardPageFixtures::withId((new Menu())->setCode('bare'), 9);
        $page = DashboardPageFixtures::pages()->showPage($bare, false, new FormView(), new FormView(), 100);
        self::assertSame([], $page->sections);
        self::assertSame(['export', 'copy', 'edit', 'config', 'create'], array_map(static fn (Action $a): string => $a->kind, $page->header->actions));
    }

    public function testItemRowsCarryTheirActions(): void
    {
        $menu  = DashboardPageFixtures::sidebar();
        $items = DashboardPageFixtures::items();
        $rows  = DashboardPageFixtures::pages()->itemRows($menu, [...$items, new MenuItem()], [13 => 1], [13 => 'Blog'], [11 => new FormView()], [11 => new FormView()], [11 => new FormView()], 'en');

        self::assertCount(5, $rows, 'Items without id are skipped.');
        self::assertSame(1, $rows['item-13']['depth']);
        self::assertSame('—', $rows['item-11']['parentLabel']);

        $home = $rows['item-11']['actions'];
        self::assertSame(['prev', 'next', 'create', 'edit', 'custom', 'config', 'delete'], array_map(static fn (Action $a): string => $a->kind, $home));
        self::assertTrue($home[0]->isPost());
        self::assertFalse($home[2]->disabled);
        self::assertSame('/admin/menus/1/item/new?parent=11', $home[2]->href);

        $section = $rows['item-10']['actions'];
        self::assertTrue($section[0]->disabled);
        self::assertTrue($section[1]->disabled);
        self::assertTrue($section[2]->disabled, 'Sections cannot have children.');
        self::assertSame(['%name%' => 'Content'], $this->label($section[6]->confirm?->message)->params);

        $divider = $rows['item-14']['actions'];
        self::assertSame(['%name%' => '#14'], $this->label($divider[6]->confirm?->message)->params);
        self::assertCount(4, DashboardPageFixtures::pages()->itemColumns('es'));
    }

    public function testReorderHeaderLinksBackToTheTable(): void
    {
        $header = DashboardPageFixtures::pages()->reorderHeader(DashboardPageFixtures::sidebar());

        self::assertSame('prev', $header->actions[0]->kind);
        self::assertSame('/admin/menus/1', $header->actions[0]->href);
        self::assertCount(3, $header->breadcrumbs);
        self::assertSame('/admin/menus/1', $header->breadcrumbs[1]->href);
    }

    public function testFormPagesTitlesAndCancelTargets(): void
    {
        $pages = DashboardPageFixtures::pages();
        $menu  = DashboardPageFixtures::sidebar();
        $item  = DashboardPageFixtures::items()[1];

        $new = $pages->menuFormPage(new FormView(), false);
        self::assertSame('dashboard.new_menu', $this->label($new->header->title)->key);
        self::assertSame('/admin/menus', $new->cancelHref);
        self::assertNull($new->header->intro);
        self::assertSame('dashboard.edit_config', $this->label($pages->menuFormPage(new FormView(), true, $menu, 'config')->header->title)->key);
        $edit = $pages->menuFormPage(new FormView(), true, $menu);
        self::assertSame('dashboard.edit_menu', $this->label($edit->header->title)->key);
        self::assertSame('/admin/menus/1', $edit->cancelHref);
        self::assertSame('sidebar', $edit->header->intro);

        $titles = [];
        foreach ([[false, 'minimal', null], [false, 'minimal', $item], [true, 'basic', null], [true, 'icon', null], [true, 'config', null], [true, null, null]] as [$isEdit, $section, $parent]) {
            $titles[] = $this->label($pages->itemFormPage(new FormView(), $menu, $isEdit, $section, $parent, $isEdit ? $item : null)->header->title)->key;
        }
        self::assertSame(['dashboard.add_item', 'dashboard.add_child', 'dashboard.edit_labels', 'dashboard.edit_type_position_icon', 'dashboard.edit_config', 'dashboard.edit_item'], $titles);
        self::assertSame('Home', $pages->itemFormPage(new FormView(), $menu, true, null, null, $item)->header->intro);
        self::assertNull($pages->itemFormPage(new FormView(), $menu, false)->header->intro);
        self::assertSame('Home', $pages->itemFormPage(new FormView(), $menu, false, 'minimal', $item)->header->intro);

        self::assertSame('/admin/menus/1', $pages->copyFormPage(new FormView(), $menu)->cancelHref);
        self::assertSame('/admin/menus', $pages->importFormPage(new FormView())->cancelHref);
    }

    public function testDialogsFollowTheModalSizes(): void
    {
        $dialogs = (new DashboardPages(DashboardPageFixtures::urls(), new DashboardUiConfig(), ['menu_form' => 'xl', 'copy' => 'normal']))
            ->dialogs([DashboardPages::DIALOG_MENU, DashboardPages::DIALOG_COPY, DashboardPages::DIALOG_ITEM, 'unknown']);

        self::assertSame(['xl', 'md', 'lg'], array_map(static fn (array $d): string => $d['size'], $dialogs));
        self::assertSame('dashboard.item', $dialogs[2]['title']->key);
    }

    private function label(mixed $value): Label
    {
        self::assertInstanceOf(Label::class, $value);

        return $value;
    }
}
