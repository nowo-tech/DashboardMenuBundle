<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\View;

use Nowo\DashboardMenuBundle\Controller\Dashboard\DashboardRoutes;
use Nowo\DashboardMenuBundle\Entity\Menu;
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\NowoDashboardMenuBundle;
use Nowo\DashboardMenuBundle\Service\DashboardUiConfig;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DetailItem;
use Nowo\UiKitBundle\View\DetailSection;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FilterField;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\UiKitBundle\View\ShowPage;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_keys;
use function implode;
use function is_string;
use function trim;

/**
 * Builds the UiKit view models of the dashboard pages (REQ-UI-003): menus list (list scaffold), menu detail with the
 * items table (show scaffold), reorder tree (unique page header), and the menu / item / copy / import forms (form
 * scaffold). Plain data in, immutable view models out — no request or session state is kept (worker safe); the
 * controller passes the entities, the CSRF-only forms and the layout.
 *
 * Forms open in the UiKit {@code dialog}s of the page: their openers are plain links to the full form page with
 * {@code data-nowo-dashboard-menu-dialog="<dialog id>"} (dashboard.js loads the link with {@code ?_modal=1} into the
 * dialog; without JavaScript the link opens the page). Destructive actions use the UiKit confirm contract.
 *
 * The deprecated {@code dashboard.ui} chrome hooks still read here (one minor version): {@code row_action_display}
 * (row actions {@code display}), {@code header_action_variants} (header action variants) and {@code form_themes}
 * ({@code FormPage::formThemes}).
 */
final readonly class DashboardPages
{
    public const BUNDLE = DashboardWebUi::BUNDLE;

    public const DOMAIN = NowoDashboardMenuBundle::TRANSLATION_DOMAIN;

    /** Attribute of the dialog openers (value: the dialog id). */
    public const DIALOG_ATTRIBUTE = 'data-nowo-dashboard-menu-dialog';

    public const DIALOG_MENU = 'nowo-dashboard-menu-dialog-menu';

    public const DIALOG_COPY = 'nowo-dashboard-menu-dialog-copy';

    public const DIALOG_ITEM = 'nowo-dashboard-menu-dialog-item';

    public const DIALOG_IMPORT = 'nowo-dashboard-menu-dialog-import';

    /** 1.x {@code dashboard.ui.header_action_variants} value => UiKit {@see Action} variant. */
    private const LEGACY_VARIANTS = [
        'primary'   => 'primary',
        'secondary' => 'secondary',
        'ghost'     => 'neutral',
        'danger'    => 'danger',
        'link'      => 'link',
    ];

    /**
     * @param array<string, string> $modalSizes dashboard.modals (menu_form, copy, item_form, delete: normal | lg | xl)
     */
    public function __construct(
        private UrlGeneratorInterface $urls,
        private DashboardUiConfig $legacyUi = new DashboardUiConfig(),
        private array $modalSizes = [],
        private ?string $indexIntro = null,
        private string $indexIntroDomain = self::DOMAIN,
    ) {
    }

    /**
     * Menus list (index): search filter, pagination, export / import / new menu header actions and per-menu row
     * actions (items, copy, edit, configuration, delete).
     *
     * @param list<Menu> $menus menus of the current page
     * @param array<int, int> $itemCounts menu id => number of items
     * @param array<int, FormView> $deleteForms menu id => CSRF-only delete form (non-base menus)
     */
    public function indexPage(
        array $menus,
        array $itemCounts,
        FormView $search,
        string $searchTerm = '',
        ?Pagination $pagination = null,
        array $deleteForms = [],
        ?string $layout = null,
    ): ListPage {
        $indexUrl = $this->url(DashboardRoutes::ROUTE_INDEX);
        $rows     = [];
        foreach ($menus as $menu) {
            $id = $menu->getId();
            if ($id === null) {
                continue;
            }
            $rows['menu-' . $id] = ['menu' => $menu, 'items' => $itemCounts[$id] ?? 0];
        }

        $newMenu = $this->dialogAction('create', 'dashboard.new_menu', $this->url(DashboardRoutes::ROUTE_MENU_NEW), self::DIALOG_MENU, $this->variant('new_menu', 'primary'));

        return new ListPage(
            new PageHeader($this->label('dashboard.menus'), $this->indexIntro(), [
                new Action('export', $this->label('dashboard.export_all'), $this->url(DashboardRoutes::ROUTE_EXPORT_ALL), variant: $this->variant('export_all'), attributes: ['target' => '_blank', 'rel' => 'noopener noreferrer']),
                $this->dialogAction('import', 'dashboard.import', $this->url(DashboardRoutes::ROUTE_IMPORT), self::DIALOG_IMPORT, $this->variant('import')),
                $newMenu,
            ]),
            [
                new Column('code', $this->label('dashboard.table_code'), required: true),
                new Column('name', $this->label('dashboard.table_name')),
                new Column('items', $this->label('dashboard.table_items'), align: 'end', priority: 2),
                new Column('context', $this->label('dashboard.table_context'), priority: 3),
            ],
            $rows,
            $search,
            fn (array $row): array => $this->menuRowActions($row['menu'], $deleteForms),
            $pagination,
            new EmptyState(null, $this->label('dashboard.no_menus_yet'), $newMenu),
            $searchTerm !== '' ? $indexUrl : null,
            bundle: self::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => 'dashboard-menu-menus'],
            actionsLabel: $this->label('dashboard.actions'),
            filterFields: ['q' => new FilterField('q', width: 'lg')],
            filtersSubmit: new Action('filter', $this->label('dashboard.filter')),
            filtersClear: $searchTerm !== '' ? new Action('cancel', $this->label('dashboard.clear_filters'), $indexUrl) : null,
        );
    }

    /**
     * Pagination of the menus list (null when pagination is disabled).
     */
    public function pagination(int $page, int $perPage, int $total, int $totalPages): Pagination
    {
        return new Pagination($page, $perPage, $total, $totalPages, DashboardRoutes::ROUTE_INDEX);
    }

    /**
     * Menu detail: properties and CSS classes as detail sections, header actions (reorder, export, copy, edit,
     * configuration, re-index, cycle check, add item). The items table renders in {@code nowo_ui_show_after} from
     * {@see self::itemRows()}.
     */
    public function showPage(Menu $menu, bool $hasItems, FormView $reindexForm, FormView $checkCyclesForm, int $positionStep, ?string $layout = null): ShowPage
    {
        $id      = (int) $menu->getId();
        $actions = [];
        if ($hasItems) {
            $actions[] = new Action('custom', $this->label('dashboard.open_sortable_reorder'), $this->url(DashboardRoutes::ROUTE_SHOW_ITEMS_REORDER, ['id' => $id]), attributes: ['data-testid' => 'dashboard-menu-open-reorder']);
        }
        $actions = [...$actions, ...$this->menuActions($menu)];
        if ($hasItems) {
            $actions[] = new Action('custom', $this->label('dashboard.reindex_positions', ['%step%' => $positionStep]), $this->url(DashboardRoutes::ROUTE_ITEMS_REINDEX_POSITIONS, ['id' => $id]), method: 'POST', variant: $this->variant('reindex_positions'), form: $reindexForm);
            $actions[] = new Action('custom', $this->label('dashboard.check_parent_cycles'), $this->url(DashboardRoutes::ROUTE_ITEMS_CHECK_PARENT_CYCLES, ['id' => $id]), method: 'POST', variant: $this->variant('check_parent_cycles'), form: $checkCyclesForm);
        }
        $actions[] = $this->addItemAction($menu);

        $sections   = [];
        $properties = $this->propertyItems($menu);
        if ($properties !== []) {
            $sections[] = new DetailSection($this->label('dashboard.menu'), $properties, 'dashboard-menu-properties');
        }
        $classes = $this->cssClassItems($menu);
        if ($classes !== []) {
            $sections[] = new DetailSection($this->label('dashboard.css_classes'), $classes, 'dashboard-menu-css-classes');
        }

        return new ShowPage(
            new PageHeader($menu->getCode(), $menu->getName(), $actions, $this->menuCrumbs($menu)),
            $sections,
            bundle: self::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => 'dashboard-menu-show'],
        );
    }

    /**
     * Columns of the items table (show page).
     *
     * @return list<Column>
     */
    public function itemColumns(string $locale): array
    {
        return [
            new Column('parent', $this->label('dashboard.parent'), priority: 3),
            new Column('label', $this->label('form.menu_item_type.label_locale', ['%locale%' => $locale]), required: true),
            new Column('link', $this->label('dashboard.route_url'), priority: 2),
            new Column('item', $this->label('dashboard.item'), required: true),
        ];
    }

    /**
     * Rows of the items table: the item, its tree depth and parent label, and its row actions (move up / down, add
     * child, edit labels, edit type / position / icon, configuration, delete).
     *
     * @param list<MenuItem> $items items in tree display order
     * @param array<int, int> $depths item id => depth (0 = root)
     * @param array<int, string> $parentLabels item id => parent label
     * @param array<int, FormView> $moveUpForms item id => CSRF-only form (items with a previous sibling)
     * @param array<int, FormView> $moveDownForms item id => CSRF-only form (items with a next sibling)
     * @param array<int, FormView> $deleteForms item id => CSRF-only delete form
     *
     * @return array<string, array{item: MenuItem, depth: int, parentLabel: string, actions: list<Action>}>
     */
    public function itemRows(Menu $menu, array $items, array $depths, array $parentLabels, array $moveUpForms, array $moveDownForms, array $deleteForms, string $locale): array
    {
        $rows = [];
        foreach ($items as $item) {
            $itemId = $item->getId();
            if ($itemId === null) {
                continue;
            }
            $rows['item-' . $itemId] = [
                'item'        => $item,
                'depth'       => $depths[$itemId] ?? 0,
                'parentLabel' => $parentLabels[$itemId] ?? '—',
                'actions'     => $this->itemRowActions($menu, $item, $moveUpForms[$itemId] ?? null, $moveDownForms[$itemId] ?? null, $deleteForms[$itemId] ?? null, $locale),
            ];
        }

        return $rows;
    }

    /**
     * Header of the drag-reorder page (unique page): back to the table, export, copy, edit, configuration, add item.
     */
    public function reorderHeader(Menu $menu): PageHeader
    {
        $showUrl   = $this->url(DashboardRoutes::ROUTE_SHOW, ['id' => (int) $menu->getId()]);
        $crumbs    = $this->menuCrumbs($menu);
        $crumbs[1] = new Crumb($menu->getCode(), $showUrl);
        $crumbs[]  = new Crumb($this->label('dashboard.sortable_page_heading'));

        return new PageHeader(
            $menu->getCode(),
            $this->label('dashboard.sortable_page_heading'),
            [
                new Action('prev', $this->label('dashboard.back_to_items_table'), $showUrl),
                ...$this->menuActions($menu),
                $this->addItemAction($menu),
            ],
            $crumbs,
        );
    }

    /**
     * New / edit menu form ({@code section}: basic = definition only, config = configuration only, null = both).
     */
    public function menuFormPage(FormView $form, bool $isEdit, ?Menu $menu = null, ?string $section = null, ?string $layout = null): FormPage
    {
        $title  = !$isEdit ? 'dashboard.new_menu' : ($section === 'config' ? 'dashboard.edit_config' : 'dashboard.edit_menu');
        $cancel = $menu instanceof Menu && $menu->getId() !== null
            ? $this->url(DashboardRoutes::ROUTE_SHOW, ['id' => $menu->getId()])
            : $this->url(DashboardRoutes::ROUTE_INDEX);
        $crumbs = $menu instanceof Menu && $menu->getId() !== null ? $this->menuCrumbs($menu, true) : [new Crumb($this->label('dashboard.menus'), $this->url(DashboardRoutes::ROUTE_INDEX))];

        return $this->formPage(
            new PageHeader($this->label($title), $isEdit && $menu instanceof Menu ? $menu->getCode() : null, [], [...$crumbs, new Crumb($this->label($title))]),
            $form,
            $isEdit ? 'dashboard.update' : 'dashboard.create',
            $cancel,
            'dashboard-menu-menu-form',
            $layout,
        );
    }

    /**
     * New / edit item form ({@code section}: minimal (new item), basic, icon, config, identity or null = full edit).
     * {@code $form} null: UiKit 2.2 body-only form page (the configuration section's Live component renders its own
     * form, submit and cancel inside the form panel).
     */
    public function itemFormPage(?FormView $form, Menu $menu, bool $isEdit, ?string $section = null, ?MenuItem $parent = null, ?MenuItem $item = null, string $locale = 'en', ?string $layout = null): FormPage
    {
        $title = match (true) {
            !$isEdit              => $parent instanceof MenuItem ? 'dashboard.add_child' : 'dashboard.add_item',
            $section === 'basic'  => 'dashboard.edit_labels',
            $section === 'icon'   => 'dashboard.edit_type_position_icon',
            $section === 'config' => 'dashboard.edit_config',
            default               => 'dashboard.edit_item',
        };
        $intro = $item instanceof MenuItem ? $item->getLabelForLocale($locale) : ($parent instanceof MenuItem ? $parent->getLabelForLocale($locale) : null);

        return $this->formPage(
            new PageHeader($this->label($title), $intro === '' ? null : $intro, [], [...$this->menuCrumbs($menu, true), new Crumb($this->label($title))]),
            $form,
            $isEdit ? 'dashboard.update' : 'dashboard.create',
            $this->url(DashboardRoutes::ROUTE_SHOW, ['id' => (int) $menu->getId()]),
            'dashboard-menu-item-form',
            $layout,
        );
    }

    public function copyFormPage(FormView $form, Menu $menu, ?string $layout = null): FormPage
    {
        return $this->formPage(
            new PageHeader($this->label('dashboard.copy_menu'), $this->label('dashboard.copy_menu_help'), [], [...$this->menuCrumbs($menu, true), new Crumb($this->label('dashboard.copy_menu'))]),
            $form,
            'dashboard.copy_menu',
            $this->url(DashboardRoutes::ROUTE_SHOW, ['id' => (int) $menu->getId()]),
            'dashboard-menu-copy-form',
            $layout,
        );
    }

    public function importFormPage(FormView $form, ?string $layout = null): FormPage
    {
        $indexUrl = $this->url(DashboardRoutes::ROUTE_INDEX);

        return $this->formPage(
            new PageHeader($this->label('dashboard.import'), $this->label('dashboard.import_help'), [], [new Crumb($this->label('dashboard.menus'), $indexUrl), new Crumb($this->label('dashboard.import'))]),
            $form,
            'dashboard.import',
            $indexUrl,
            'dashboard-menu-import-form',
            $layout,
        );
    }

    /**
     * The UiKit dialogs of a page (id, title, size from {@code dashboard.modals}) the form openers load into.
     *
     * @param list<string> $ids dialog ids (self::DIALOG_*)
     *
     * @return list<array{id: string, title: Label, size: string}>
     */
    public function dialogs(array $ids): array
    {
        $defs = [
            self::DIALOG_MENU   => ['dashboard.edit_menu', 'menu_form', 'normal'],
            self::DIALOG_COPY   => ['dashboard.copy_menu', 'copy', 'normal'],
            self::DIALOG_ITEM   => ['dashboard.item', 'item_form', 'lg'],
            self::DIALOG_IMPORT => ['dashboard.import', 'menu_form', 'normal'],
        ];
        $dialogs = [];
        foreach ($ids as $id) {
            if (!isset($defs[$id])) {
                continue;
            }
            [$title, $sizeKey, $default] = $defs[$id];
            $size                        = $this->modalSizes[$sizeKey] ?? $default;
            $dialogs[]                   = ['id' => $id, 'title' => $this->label($title), 'size' => $size === 'normal' ? 'md' : $size];
        }

        return $dialogs;
    }

    /**
     * Form themes of the dashboard forms: the deprecated {@code dashboard.ui.form_themes} (prefer the FormKit
     * {@code dashboard_menu} profile).
     *
     * @return list<string>
     */
    public function formThemes(): array
    {
        return $this->legacyUi->toArray()['form_themes'];
    }

    private function formPage(PageHeader $header, ?FormView $form, string $submitLabel, string $cancelHref, string $testId, ?string $layout): FormPage
    {
        return new FormPage(
            $header,
            $form,
            $this->label($submitLabel),
            $cancelHref,
            $this->label('dashboard.cancel'),
            bundle: self::BUNDLE,
            layout: $layout,
            attributes: ['data-testid' => $testId],
            formThemes: $this->formThemes(),
        );
    }

    /**
     * @param array<int, FormView> $deleteForms
     *
     * @return list<Action>
     */
    private function menuRowActions(Menu $menu, array $deleteForms): array
    {
        $id      = (int) $menu->getId();
        $actions = [
            $this->row(new Action('view', $this->label('dashboard.edit_items'), $this->url(DashboardRoutes::ROUTE_SHOW, ['id' => $id]), icon: 'view')),
            $this->row($this->dialogAction('copy', 'dashboard.copy', $this->url(DashboardRoutes::ROUTE_MENU_COPY, ['id' => $id]), self::DIALOG_COPY, icon: 'copy')),
            $this->row($this->dialogAction('edit', 'dashboard.edit_identity', $this->url(DashboardRoutes::ROUTE_MENU_EDIT, ['id' => $id, 'section' => 'basic']), self::DIALOG_MENU, icon: 'edit')),
        ];
        if (!$menu->isBase()) {
            $actions[] = $this->row($this->dialogAction('config', 'dashboard.edit_config', $this->url(DashboardRoutes::ROUTE_MENU_EDIT, ['id' => $id, 'section' => 'config']), self::DIALOG_MENU, icon: 'config'));
            $actions[] = $this->row(Action::delete(
                $this->url(DashboardRoutes::ROUTE_MENU_DELETE, ['id' => $id]),
                confirm: new Confirm($this->label('dashboard.confirm_delete'), $this->label('dashboard.delete_menu_confirm', ['%name%' => $this->menuDisplayName($menu)]), $this->label('dashboard.delete')),
                label: $this->label('dashboard.delete'),
                form: $deleteForms[$id] ?? null,
            ));
        }

        return $actions;
    }

    /**
     * Header actions shared by the detail and reorder pages: export, copy, edit, configuration (not for base menus).
     *
     * @return list<Action>
     */
    private function menuActions(Menu $menu): array
    {
        $id      = (int) $menu->getId();
        $actions = [
            new Action('export', $this->label('dashboard.export'), $this->url(DashboardRoutes::ROUTE_EXPORT_MENU, ['id' => $id]), variant: $this->variant('export'), attributes: ['target' => '_blank', 'rel' => 'noopener noreferrer']),
            $this->dialogAction('copy', 'dashboard.copy', $this->url(DashboardRoutes::ROUTE_MENU_COPY, ['id' => $id]), self::DIALOG_COPY, $this->variant('copy')),
            $this->dialogAction('edit', 'dashboard.edit_menu', $this->url(DashboardRoutes::ROUTE_MENU_EDIT, ['id' => $id, 'section' => 'basic']), self::DIALOG_MENU, $this->variant('edit_menu', 'primary')),
        ];
        if (!$menu->isBase()) {
            $actions[] = $this->dialogAction('config', 'dashboard.edit_config', $this->url(DashboardRoutes::ROUTE_MENU_EDIT, ['id' => $id, 'section' => 'config']), self::DIALOG_MENU, $this->variant('edit_config'));
        }

        return $actions;
    }

    /**
     * "Add item" (root item) opener: page header and the empty state of the items table.
     */
    public function addItemAction(Menu $menu): Action
    {
        return $this->dialogAction('create', 'dashboard.add_item', $this->url(DashboardRoutes::ROUTE_ITEM_NEW, ['id' => (int) $menu->getId()]), self::DIALOG_ITEM, $this->variant('add_item', 'primary'));
    }

    /**
     * @return list<Action>
     */
    private function itemRowActions(Menu $menu, MenuItem $item, ?FormView $moveUp, ?FormView $moveDown, ?FormView $delete, string $locale): array
    {
        $menuId   = (int) $menu->getId();
        $itemId   = (int) $item->getId();
        $params   = ['id' => $menuId, 'itemId' => $itemId];
        $canChild = $item->getItemType() === MenuItem::ITEM_TYPE_LINK || $item->getItemType() === MenuItem::ITEM_TYPE_SERVICE;
        $editUrl  = fn (string $section): string => $this->url(DashboardRoutes::ROUTE_ITEM_EDIT, $params + ['section' => $section]);
        $label    = $item->getLabelForLocale($locale);

        return [
            $this->row($moveUp instanceof FormView
                ? new Action('prev', $this->label('dashboard.move_up'), $this->url(DashboardRoutes::ROUTE_ITEM_MOVE_UP, $params), method: 'POST', icon: 'chevron_up', form: $moveUp)
                : new Action('prev', $this->label('dashboard.move_up'), icon: 'chevron_up', disabled: true)),
            $this->row($moveDown instanceof FormView
                ? new Action('next', $this->label('dashboard.move_down'), $this->url(DashboardRoutes::ROUTE_ITEM_MOVE_DOWN, $params), method: 'POST', icon: 'chevron_down', form: $moveDown)
                : new Action('next', $this->label('dashboard.move_down'), icon: 'chevron_down', disabled: true)),
            $this->row($canChild
                ? $this->dialogAction('create', 'dashboard.add_child', $this->url(DashboardRoutes::ROUTE_ITEM_NEW, ['id' => $menuId, 'parent' => $itemId]), self::DIALOG_ITEM, icon: 'create')
                : new Action('create', $this->label('dashboard.add_child_disabled'), icon: 'create', disabled: true)),
            $this->row($this->dialogAction('edit', 'dashboard.edit_labels', $editUrl('basic'), self::DIALOG_ITEM, icon: 'edit')),
            $this->row($this->dialogAction('custom', 'dashboard.edit_type_position_icon', $editUrl('icon'), self::DIALOG_ITEM, icon: 'rows')),
            $this->row($this->dialogAction('config', 'dashboard.edit_config', $editUrl('config'), self::DIALOG_ITEM, icon: 'config')),
            $this->row(Action::delete(
                $this->url(DashboardRoutes::ROUTE_ITEM_DELETE, $params),
                confirm: new Confirm($this->label('dashboard.confirm_delete'), $this->label('dashboard.delete_item_confirm', ['%name%' => $label !== '' ? $label : '#' . $itemId]), $this->label('dashboard.delete')),
                label: $this->label('dashboard.delete'),
                form: $delete,
            )),
        ];
    }

    /**
     * A link to a form page that the dashboard script opens in the given UiKit dialog.
     */
    private function dialogAction(string $kind, string $label, string $href, string $dialog, ?string $variant = null, ?string $icon = null): Action
    {
        return new Action($kind, $this->label($label), $href, icon: $icon, variant: $variant, attributes: [self::DIALOG_ATTRIBUTE => $dialog, 'aria-haspopup' => 'dialog']);
    }

    /**
     * Applies the deprecated {@code dashboard.ui.row_action_display} to a row action (null = UiKit row_actions_display).
     */
    private function row(Action $action): Action
    {
        $display = $this->legacyUi->toArray()['row_action_display'];
        if (!is_string($display) || $display === $action->display) {
            return $action;
        }

        return new Action(
            $action->kind,
            $action->label,
            $action->href,
            $action->route,
            $action->params,
            $action->method,
            $action->csrfToken,
            $action->confirm,
            $action->icon,
            $action->variant,
            $action->attributes,
            $action->form,
            $action->csrfField,
            $action->modalId,
            $action->disabled,
            $action->visible,
            $display,
        );
    }

    /**
     * Variant of a header action: the deprecated {@code dashboard.ui.header_action_variants} entry, else the default.
     */
    private function variant(string $action, ?string $default = null): ?string
    {
        $legacy = $this->legacyUi->headerActionVariant($action, '');

        return self::LEGACY_VARIANTS[$legacy] ?? $default;
    }

    /**
     * @return list<Crumb>
     */
    private function menuCrumbs(Menu $menu, bool $linkMenu = false): array
    {
        return [
            new Crumb($this->label('dashboard.menus'), $this->url(DashboardRoutes::ROUTE_INDEX)),
            new Crumb($menu->getCode(), $linkMenu ? $this->url(DashboardRoutes::ROUTE_SHOW, ['id' => (int) $menu->getId()]) : null),
        ];
    }

    /**
     * @return list<DetailItem>
     */
    private function propertyItems(Menu $menu): array
    {
        $items = [];
        if ($menu->isBase()) {
            $items[] = new DetailItem($this->label('dashboard.base'), $this->label('dashboard.base_badge_title'));
        }
        if ($menu->getContext() !== null && $menu->getContext() !== []) {
            $items[] = new DetailItem($this->label('dashboard.context'), implode(', ', array_keys($menu->getContext())));
        }
        if ($menu->getPermissionChecker() !== null && $menu->getPermissionChecker() !== '') {
            $items[] = new DetailItem($this->label('dashboard.permission_checker'), $menu->getPermissionChecker());
        }
        if ($menu->getDepthLimit() !== null) {
            $items[] = new DetailItem($this->label('dashboard.depth_limit'), $menu->getDepthLimit());
        }
        foreach ([
            'dashboard.collapsible'                            => $menu->getCollapsible(),
            'dashboard.collapsible_expanded'                   => $menu->getCollapsibleExpanded(),
            'dashboard.nested_collapsible'                     => $menu->getNestedCollapsible(),
            'form.menu_type.nested_collapsible_sections.label' => $menu->getNestedCollapsibleSections(),
        ] as $key => $value) {
            if ($value !== null) {
                $items[] = new DetailItem($this->label($key), $this->label($value ? 'dashboard.yes' : 'dashboard.no'));
            }
        }

        return $items;
    }

    /**
     * @return list<DetailItem>
     */
    private function cssClassItems(Menu $menu): array
    {
        $items = [];
        foreach ([
            'menu'               => $menu->getClassMenu(),
            'ul_id'              => $menu->getUlId(),
            'item'               => $menu->getClassItem(),
            'link'               => $menu->getClassLink(),
            'children'           => $menu->getClassChildren(),
            'section-children'   => $menu->getClassSectionChildren(),
            'section-child-item' => $menu->getClassSectionChildItem(),
            'section-child-link' => $menu->getClassSectionChildLink(),
            'section-label'      => $menu->getClassSectionLabel(),
            'section'            => $menu->getClassSection(),
            'divider'            => $menu->getClassDivider(),
            'current'            => $menu->getClassCurrent(),
            'branch'             => $menu->getClassBranchExpanded(),
            'has-children'       => $menu->getClassHasChildren(),
            'expanded'           => $menu->getClassExpanded(),
            'collapsed'          => $menu->getClassCollapsed(),
        ] as $slot => $value) {
            if ($value !== null && $value !== '') {
                $items[] = new DetailItem($slot, $value);
            }
        }

        return $items;
    }

    private function menuDisplayName(Menu $menu): string
    {
        $name = $menu->getName();

        return $name !== null && $name !== '' ? $menu->getCode() . ' — ' . $name : $menu->getCode();
    }

    /**
     * @param array<string, int|string> $params
     */
    private function url(string $route, array $params = []): string
    {
        return $this->urls->generate($route, $params);
    }

    /**
     * Intro of the menus list (web_ui.index.intro, 2.10+): a translation key or text of web_ui.index.intro_domain.
     */
    private function indexIntro(): ?Label
    {
        return $this->indexIntro === null || trim($this->indexIntro) === '' ? null : new Label($this->indexIntro, $this->indexIntroDomain !== '' ? $this->indexIntroDomain : self::DOMAIN);
    }

    /**
     * @param array<string, int|string> $params
     */
    private function label(string $key, array $params = []): Label
    {
        return new Label($key, self::DOMAIN, $params);
    }
}
