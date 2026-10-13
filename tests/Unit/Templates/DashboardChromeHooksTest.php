<?php

declare(strict_types=1);

namespace Nowo\DashboardMenuBundle\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

use function dirname;
use function substr_count;

/**
 * Render tests for the admin chrome hooks (dashboard.ui, 2.3.0): defaults keep the bundle markup; class map,
 * partial paths, template blocks and layout switches let a host theme the dashboard without forking pages.
 */
final class DashboardChromeHooksTest extends TestCase
{
    private const INDEX   = '@NowoDashboardMenuBundle/dashboard/index.html.twig';
    private const SHOW    = '@NowoDashboardMenuBundle/dashboard/show.html.twig';
    private const REORDER = '@NowoDashboardMenuBundle/dashboard/show_items_reorder.html.twig';

    /** Host layout_template: places the header actions exposed by the pages (header_actions_in_layout). */
    private const HOST_LAYOUT = <<<'TWIG'
        <html><head>{% block stylesheets %}<link href="host.css">{% endblock %}</head><body>
        <header class="host-header">{{ nowo_dashboard_menu_header_actions|default('') }}</header>
        {% block nowo_dashboard_menu_content %}{% endblock %}
        {% block javascripts %}{% endblock %}
        </body></html>
        TWIG;

    /** Host modal shell (<dialog>) keeping the bundle contract (ids, body id, block modal_body). */
    private const HOST_MODAL = <<<'TWIG'
        <dialog class="host-dialog host-dialog--{{ modal_kind|default('form') }}" id="{{ modal_id }}" aria-labelledby="{{ modal_label_id }}">
            <h2 id="{{ modal_label_id }}">{{ modal_title }}</h2>
            <div class="host-dialog__body"{% if modal_body_id %} id="{{ modal_body_id }}"{% endif %}>{% block modal_body %}{% endblock %}</div>
        </dialog>
        TWIG;

    private const HOST_PAGINATION = '<nav class="host-pagination" data-page="{{ pagination.page }}" data-route="{{ route }}" data-count="{{ item_count }}">{{ nav_label|trans({}, nav_trans_domain) }}</nav>';

    private const HOST_INDEX = <<<'TWIG'
        {% extends '@NowoDashboardMenuBundle/dashboard/index.html.twig' %}
        {% block dashboard_empty_state %}<p class="host-empty">Nothing here</p>{% endblock %}
        {% block dashboard_page_header %}<h1 class="host-title">{{ block('dashboard_title') }}</h1>{% endblock %}
        TWIG;

    private const HOST_FORM_THEME = '{% block form_row %}<div class="host-row">{{ form_widget(form) }}</div>{% endblock %}';

    /** Beacon-like config: Administration chrome expressed only through dashboard.ui. */
    private const HOST_UI = [
        'include_ui_kit_css'       => false,
        'header_actions_in_layout' => true,
        'index_view'               => 'table',
        'actions_column'           => 'last',
        'row_action_display'       => 'text',
        'region_attribute'         => 'data-testid',
        'classes'                  => [
            'button_primary'      => 'btn-primary',
            'button_secondary'    => 'btn-ghost',
            'button_danger'       => 'btn-danger',
            'button_small'        => 'btn-ghost',
            'search_form'         => 'host-toolbar',
            'search_input'        => 'input',
            'table_wrap'          => 'panel overflow-x-auto',
            'table'               => 'data-table',
            'table_row'           => 'row-hover',
            'row_actions'         => 'host-row-actions',
            'row_action'          => 'btn-ghost btn-sm btn-label',
            'row_action_danger'   => 'btn-danger btn-sm btn-label',
            'row_action_disabled' => 'opacity-40',
            'info_panel'          => 'panel mb-4',
            'badge_unanimous'     => 'badge-moss',
            'badge_non_unanimous' => 'badge-alert',
            'sortable_panel'      => 'panel',
            'sortable_header'     => 'panel-head',
            'sortable_body'       => 'panel-body',
            'modal_loading'       => 'kit-modal__lead',
            'modal_form'          => 'kit-modal__panel',
            'modal_content'       => 'confirm-dialog__content',
            'modal_section'       => 'kit-modal__section',
            'modal_message'       => 'confirm-dialog__body',
            'modal_actions'       => 'confirm-dialog__actions',
            'inline_form'         => 'inline',
        ],
        'partials' => [
            'modal'      => 'host/_modal.html.twig',
            'pagination' => 'host/_pagination.html.twig',
        ],
        'form_themes' => ['host/form_theme.html.twig'],
    ];

    public function testDefaultIndexKeepsBundleChrome(): void
    {
        $renderer = new DashboardTemplateRenderer();
        $html     = $renderer->render(self::INDEX, $renderer->indexContext());

        self::assertStringContainsString('<ul class="nowo-ui-list', $html);
        self::assertStringContainsString('nowo-ui-toolbar', $html);
        self::assertStringContainsString('class="modal fade nowo-ui-modal" id="modal-menu-new"', $html);
        self::assertStringContainsString('id="modal-menu-form-body"', $html);
        self::assertStringContainsString('<nav aria-label="Menus pagination" class="mt-3">', $html);
        self::assertStringNotContainsString('data-testid', $html);
        // layout.html.twig + base.html.twig (include_ui_kit_css default true)
        self::assertSame(2, substr_count($html, 'css/nowo-ui.css'));
    }

    public function testHostThemesIndexWithConfigOnly(): void
    {
        $renderer = $this->hostRenderer();
        $html     = $renderer->render(self::INDEX, $renderer->indexContext() + ['nowo_dashboard_layout_template' => 'host/layout.html.twig']);

        self::assertStringNotContainsString('css/nowo-ui.css', $html);
        self::assertStringNotContainsString('nowo-ui-toolbar', $html);
        self::assertMatchesRegularExpression('~<header class="host-header">\s*<a href="/nowo_dashboard_menu_dashboard_export_all" class="btn-ghost"~', $html);
        self::assertStringContainsString('class="btn-primary" data-bs-toggle="modal" data-bs-target="#modal-menu-new"', $html);
        self::assertStringContainsString('<div class="panel overflow-x-auto" data-testid="dashboard-menu-menus-table">', $html);
        self::assertStringContainsString('<table class="data-table">', $html);
        self::assertStringContainsString('<th>Code</th>', $html);
        self::assertStringContainsString('<tr class="row-hover">', $html);
        self::assertStringContainsString('class="btn-danger btn-sm btn-label btn-delete-menu"', $html);
        self::assertStringContainsString('data-token="tok-del"', $html);
        self::assertStringContainsString('<span class="nowo-ui-action__label">Delete</span>', $html);
        self::assertStringContainsString('<form name="search" method="post" class="host-toolbar" role="search">', $html);
        self::assertStringContainsString('<div class="host-row">', $html);
        self::assertStringContainsString('<nav class="host-pagination" data-page="1" data-route="nowo_dashboard_menu_dashboard_index" data-count="1">Menus pagination</nav>', $html);
        self::assertStringContainsString('<dialog class="host-dialog host-dialog--form" id="modal-menu-new"', $html);
        self::assertStringContainsString('<div class="host-dialog__body" id="modal-import-body"><div class="kit-modal__lead">', $html);
        self::assertStringContainsString('<p class="kit-modal__section">', $html);
        self::assertMatchesRegularExpression('~<div class="confirm-dialog__actions">\s*<button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancel</button>\s*<button type="submit" class="btn-primary">Create</button>~', $html);
        self::assertStringContainsString('<dialog class="host-dialog host-dialog--confirm" id="modal-delete-confirm"', $html);
        self::assertStringContainsString('<p class="confirm-dialog__body" id="modal-delete-message">', $html);
    }

    public function testEmptyTableAndTemplateBlocks(): void
    {
        $renderer = new DashboardTemplateRenderer(['index_view' => 'table'], ['host/index.html.twig' => self::HOST_INDEX]);
        $html     = $renderer->render('host/index.html.twig', $renderer->indexContext(false));

        self::assertStringContainsString('<td colspan="5"><p class="host-empty">Nothing here</p></td>', $html);
        self::assertStringContainsString('<h1 class="host-title">Menus</h1>', $html);
    }

    public function testShowPageHooksAndTranslatedBadges(): void
    {
        $renderer = $this->hostRenderer('es');
        $html     = $renderer->render(self::SHOW, $renderer->showContext() + ['nowo_dashboard_layout_template' => 'host/layout.html.twig']);

        self::assertStringContainsString('<span class="badge-moss">Unánime</span>', $html);
        self::assertStringContainsString('<span class="badge-alert">No unánime</span>', $html);
        self::assertStringContainsString('Sin claves de permiso', $html);
        self::assertStringContainsString('Pos: 100', $html);
        self::assertStringContainsString('data-testid="dashboard-menu-items-table"', $html);
        self::assertStringNotContainsString('dashboard.menus_back', $html);
        self::assertStringNotContainsString('<h1 class="h3 d-inline">', $html);
        // actions_column: last → the item label header comes before the actions header
        self::assertLessThan(strpos($html, 'nowo-dashboard-menu-actions-col text-end'), strpos($html, 'Etiqueta (es)'));
        self::assertStringContainsString('<span class="btn-ghost btn-sm btn-label opacity-40" title="Primero"', $html);
        self::assertStringContainsString('<form name="move_down_1" method="post" class="inline">', $html);
        self::assertStringContainsString('class="nowo-dashboard-menu-actions host-row-actions"', $html);
        // header links + actions placed by the layout
        self::assertMatchesRegularExpression('~<header class="host-header">\s*<a href="/nowo_dashboard_menu_dashboard_show_items_reorder\?id=7" class="btn-ghost">~', $html);
    }

    public function testDefaultShowKeepsEnglishBadgesAndActionsFirst(): void
    {
        $renderer = new DashboardTemplateRenderer();
        $html     = $renderer->render(self::SHOW, $renderer->showContext());

        self::assertStringContainsString('<span class="badge bg-success">Unanimous</span>', $html);
        self::assertStringContainsString('<span class="badge bg-warning text-dark">Non-unanimous</span>', $html);
        self::assertStringContainsString('No permission keys', $html);
        self::assertStringContainsString('<h1 class="h3 d-inline">', $html);
        self::assertLessThan(strpos($html, 'Label (en)'), strpos($html, '<th class="nowo-dashboard-menu-actions-col">'));
    }

    public function testShowEmptyStateAndMenuProperties(): void
    {
        $renderer = $this->hostRenderer();
        $menu     = DashboardTemplateRenderer::menu()->setPermissionChecker('App\\Checker')->setClassMenu('nav');
        $html     = $renderer->render(self::SHOW, ['items' => [], 'menu' => $menu] + $renderer->showContext() + ['nowo_dashboard_layout_template' => 'host/layout.html.twig']);

        self::assertSame(2, substr_count($html, '<div class="panel mb-4">'));
        self::assertStringContainsString('btn-add-item', $html);
        self::assertStringNotContainsString('dashboard-menu-items-table', $html);
    }

    public function testReorderPageHooks(): void
    {
        $renderer = $this->hostRenderer();
        $html     = $renderer->render(self::REORDER, $renderer->reorderContext() + ['nowo_dashboard_layout_template' => 'host/layout.html.twig']);

        self::assertStringContainsString('<div class="panel" data-testid="dashboard-menu-sortable">', $html);
        self::assertStringContainsString('<div class="panel-head">', $html);
        self::assertStringContainsString('<div class="panel-body">', $html);
        self::assertStringContainsString('id="nowo-menu-sortable-payload"', $html);
        self::assertMatchesRegularExpression('~<header class="host-header">\s*<a href="/nowo_dashboard_menu_dashboard_show\?id=7" class="btn-ghost">Table view</a>~', $html);

        $default = new DashboardTemplateRenderer();
        $html    = $default->render(self::REORDER, $default->reorderContext());
        self::assertStringContainsString('<div class="card border mb-3">', $html);
        self::assertStringContainsString('<a href="/nowo_dashboard_menu_dashboard_index" class="nowo-ui-btn nowo-ui-btn--link btn btn-link">', $html);
    }

    public function testHeaderActionVariantsRepaintSingleActions(): void
    {
        $ui                            = self::HOST_UI;
        $ui['classes']['button_ghost'] = 'btn-ghost btn-quiet';
        $ui['header_action_variants']  = ['edit_menu' => 'ghost', 'copy' => 'danger', 'new_menu' => 'secondary'];
        $renderer                      = new DashboardTemplateRenderer($ui, [
            'host/layout.html.twig'      => self::HOST_LAYOUT,
            'host/_modal.html.twig'      => self::HOST_MODAL,
            'host/_pagination.html.twig' => self::HOST_PAGINATION,
            'host/form_theme.html.twig'  => self::HOST_FORM_THEME,
        ]);
        $layout = ['nowo_dashboard_layout_template' => 'host/layout.html.twig'];

        foreach ([self::SHOW => $renderer->showContext(), self::REORDER => $renderer->reorderContext()] as $page => $context) {
            $html = $renderer->render($page, $context + $layout);
            self::assertStringContainsString('class="btn-ghost btn-quiet btn-edit-menu"', $html, $page);
            self::assertStringContainsString('class="btn-danger btn-copy-menu"', $html, $page);
            // untouched actions keep their bundle variant (class map button_primary / button_secondary)
            self::assertStringContainsString('class="btn-primary btn-add-item"', $html, $page);
            self::assertStringContainsString('class="btn-ghost btn-edit-menu btn-edit-menu-config"', $html, $page);
            self::assertStringNotContainsString('class="btn-primary btn-edit-menu"', $html, $page);
        }

        $index = $renderer->render(self::INDEX, $renderer->indexContext() + $layout);
        self::assertStringContainsString('class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modal-menu-new"', $index);

        // No class-map entry for the variant: UiKit ui.btn(variant) for the css_framework.
        $default = new DashboardTemplateRenderer(['header_action_variants' => ['edit_menu' => 'ghost', 'add_item' => 'link', 'reindex_positions' => 'danger']]);
        $html    = $default->render(self::SHOW, $default->showContext());
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--ghost btn btn-outline-secondary btn-edit-menu"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--link btn btn-link btn-add-item"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger" title="', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary btn-copy-menu"', $html);
    }

    public function testDefaultHeaderActionsKeepBundleVariants(): void
    {
        $renderer = new DashboardTemplateRenderer();
        $html     = $renderer->render(self::SHOW, $renderer->showContext());

        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary btn-edit-menu"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--primary btn btn-primary btn-add-item"', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary btn-copy-menu"', $html);
    }

    public function testModalBodyPartialsWithHostClasses(): void
    {
        $renderer = $this->hostRenderer();

        $copy = $renderer->render('@NowoDashboardMenuBundle/dashboard/_copy_menu_partial.html.twig', ['form' => $renderer->form('copy', ['code' => null])]);
        self::assertStringContainsString('class="kit-modal__panel dm-dashboard-form"', $copy);
        self::assertStringNotContainsString('max-width: 32rem', $copy);
        self::assertStringContainsString('<div class="confirm-dialog__actions">', $copy);

        $import = $renderer->render('@NowoDashboardMenuBundle/dashboard/_import_partial.html.twig', ['form' => $renderer->form('import', ['file' => null, 'strategy' => null])]);
        self::assertStringContainsString('class="kit-modal__panel import-form dm-dashboard-form" data-import-form="1"', $import);
        self::assertStringContainsString('<div class="confirm-dialog__content">', $import);
        self::assertStringContainsString('<div class="confirm-dialog__actions">', $import);

        $item = $renderer->render('@NowoDashboardMenuBundle/dashboard/_item_form_partial.html.twig', [
            'form'          => $renderer->form('item', ['basic' => ['label' => null], 'icon' => ['itemType' => null], 'config' => ['parent' => null, 'isUnanimous' => null, 'permissionKey' => null]]),
            'is_edit'       => true,
            'section_focus' => '',
        ]);
        self::assertSame(3, substr_count($item, '<p class="kit-modal__section">'));
        self::assertStringContainsString('<div class="confirm-dialog__content">', $item);
        self::assertStringContainsString('>Update</button>', $item);

        $menuForm = $renderer->render('@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig', [
            'form'          => $renderer->form('menu', ['definition' => 'compound', 'config' => ['classMenu' => null]]),
            'is_edit'       => true,
            'section_focus' => 'config',
        ]);
        self::assertStringContainsString('<input type="hidden" name="_section" value="config">', $menuForm);
        self::assertSame(2, substr_count($menuForm, '<p class="kit-modal__section">'));
    }

    public function testDefaultModalBodyPartials(): void
    {
        $renderer = new DashboardTemplateRenderer();

        $copy = $renderer->render('@NowoDashboardMenuBundle/dashboard/_copy_menu_partial.html.twig', ['form' => $renderer->form('copy', ['code' => null])]);
        self::assertStringContainsString('style="max-width: 32rem;"', $copy);
        self::assertStringNotContainsString('confirm-dialog', $copy);

        $menuForm = $renderer->render('@NowoDashboardMenuBundle/dashboard/_menu_form_partial.html.twig', [
            'form'    => $renderer->form('menu', ['definition' => 'compound', 'config' => ['classMenu' => null]]),
            'is_edit' => true,
        ]);
        self::assertStringContainsString('bi bi-gear-fill', $menuForm);
        self::assertStringContainsString('<div class="row mb-2">', $menuForm);
    }

    public function testRowActionInnerDisplayModes(): void
    {
        $renderer = new DashboardTemplateRenderer();
        $partial  = '@NowoDashboardMenuBundle/dashboard/_row_action_inner.html.twig';

        $icon = $renderer->render($partial, ['display' => 'icon', 'icon' => 'trash', 'icon_name' => 'delete', 'label' => 'Delete']);
        self::assertStringContainsString('<svg', $icon);
        self::assertStringContainsString('<span class="visually-hidden">Delete</span>', $icon);

        $both = $renderer->render($partial, ['display' => 'icon_text', 'icon' => 'arrow_up', 'icon_name' => 'prev', 'label' => 'Up']);
        self::assertStringContainsString('<svg', $both);
        self::assertStringContainsString('<span class="nowo-ui-action__label">Up</span>', $both);

        $text = $renderer->render($partial, ['display' => 'text', 'icon' => 'copy', 'icon_name' => 'copy', 'label' => 'Copy']);
        self::assertStringNotContainsString('<svg', $text);

        $legacy = $renderer->render($partial, ['display' => null, 'icon' => 'add', 'icon_name' => 'create', 'label' => 'Add']);
        self::assertStringContainsString('<svg', $legacy);
        self::assertStringNotContainsString('Add', $legacy);

        foreach (['items', 'edit', 'config', 'export_json', 'filter', 'arrow_down'] as $name) {
            self::assertStringContainsString('<svg', $renderer->render($partial, ['display' => null, 'icon' => $name, 'icon_name' => $name, 'label' => $name]));
        }
        self::assertSame('', trim($renderer->render($partial, ['display' => null, 'icon' => 'unknown', 'icon_name' => 'x', 'label' => 'x'])));
    }

    public function testTemplatesMapOverridesModalsInclude(): void
    {
        $renderer = new DashboardTemplateRenderer(['templates' => ['modals' => 'host/modals.html.twig']], [
            'host/modals.html.twig' => '{% extends "@NowoDashboardMenuBundle/dashboard/_menu_dashboard_modals.html.twig" %}{% block dashboard_modal_import %}<div id="modal-import" class="host-import"></div>{% endblock %}',
        ]);
        $html = $renderer->render(self::INDEX, $renderer->indexContext());

        self::assertStringContainsString('<div id="modal-import" class="host-import"></div>', $html);
        self::assertStringContainsString('id="modal-menu-copy"', $html);
    }

    public function testBundleLayoutPrintsHeaderActionsWhenMovedToLayout(): void
    {
        $renderer = new DashboardTemplateRenderer(['header_actions_in_layout' => true]);
        $html     = $renderer->render(self::INDEX, $renderer->indexContext());

        self::assertStringNotContainsString('nowo-ui-toolbar', $html);
        self::assertMatchesRegularExpression('~<div class="nowo-ui-page-header d-flex flex-wrap justify-content-end gap-2 mb-3">\s*<a href="/nowo_dashboard_menu_dashboard_export_all"~', $html);
    }

    public function testEveryDashboardTemplateCompiles(): void
    {
        $renderer = new DashboardTemplateRenderer();
        $root     = dirname(__DIR__, 3) . '/src/Resources/views';
        $compiled = 0;
        foreach (['dashboard', 'components'] as $dir) {
            foreach (glob($root . '/' . $dir . '/*.html.twig') ?: [] as $file) {
                $renderer->compile('@NowoDashboardMenuBundle/' . $dir . '/' . basename($file));
                ++$compiled;
            }
        }

        self::assertGreaterThan(20, $compiled);
    }

    private function hostRenderer(string $locale = 'en'): DashboardTemplateRenderer
    {
        return new DashboardTemplateRenderer(self::HOST_UI, [
            'host/layout.html.twig'      => self::HOST_LAYOUT,
            'host/_modal.html.twig'      => self::HOST_MODAL,
            'host/_pagination.html.twig' => self::HOST_PAGINATION,
            'host/form_theme.html.twig'  => self::HOST_FORM_THEME,
        ], $locale);
    }
}
