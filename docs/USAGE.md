# Usage

## Table of contents

- [Twig](#twig)
- [Overriding templates and translations](#overriding-templates-and-translations)
- [Customising the admin chrome](#customising-the-admin-chrome)
- [Items with children (parent, link vs section)](#items-with-children-parent-link-vs-section)
- [Resolving by context (context sets)](#resolving-by-context-context-sets)
- [JSON API](#json-api)
- [Resolving menu by criteria](#resolving-menu-by-criteria-operatorid-partnerid-menu-name)
- [Current item matchers](#current-item-matchers)
- [Request performance (menu lookup memo)](#request-performance-menu-lookup-memo)
- [Permissions](#permissions)
- [Item visibility rules](#item-visibility-rules)
- [Web Profiler diagnostics](#web-profiler-diagnostics)

## Twig

Get the tree for the current locale and optional permission context:

```twig
{% set tree = dashboard_menu_tree('sidebar') %}
{% set menuConfig = dashboard_menu_config('sidebar') %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar', menuConfig: menuConfig } %}
```

If you omit `menuConfig`, the template will call `dashboard_menu_config(menuCode)` itself, so you can also do:

```twig
{% set tree = dashboard_menu_tree('sidebar') %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar' } %}
```

**Render options** (from config): the template uses `menuConfig` for CSS **classes** (menu, item, link, children), **depth_limit** (stops rendering below that level), and **icons** (enabled, use_ux_icons, default). Per-menu options (including classes and icons) are set on the Menu entity in the database (see [CONFIGURATION.md](CONFIGURATION.md)). Icon identifiers (e.g. `bootstrap-icons:house`) are converted using `icon_library_prefix_map` (e.g. to `bi:house`) before being passed to `ux_icon()`.

Generate href for an item:

```twig
{{ dashboard_menu_href(item) }}
```

## Overriding templates and translations

**REQ-TWIG-001:** `TwigPathsPass` registers your project directory **`templates/bundles/NowoDashboardMenuBundle/`** with **`prependPath()`** when that folder exists, then registers the bundle’s **`src/Resources/views`** with **`addPath()`**. Your copies are therefore always resolved **before** the vendor templates. If you have not created that folder yet, only the bundle path is added (still after Symfony’s default paths for the namespace). Controllers use logical names only (e.g. `@NowoDashboardMenuBundle/dashboard/show.html.twig`); overrides are never blocked. Translations are not prepended, so your app's translation files for the domain `NowoDashboardMenuBundle` take precedence by default.

### Overriding templates

Place a file in your project under **`templates/bundles/NowoDashboardMenuBundle/`** with the **same relative path** as inside the bundle. Twig will use your template instead of the bundle’s (see **REQ-TWIG-001** note above). For dashboard **chrome** (classes, header placement, tables, modals, pagination) prefer the hooks in [Customising the admin chrome](#customising-the-admin-chrome): a full copy stops receiving vendor fixes.

**Form themes (e.g. autocomplete):** Some bundle templates use `{% form_theme form '@SymfonyUXAutocomplete/autocomplete_form_theme.html.twig' %}` so that route/permission selectors get a searchable dropdown. That reference points to the Symfony UX Autocomplete bundle, not to this bundle. When you override those templates (`item_form.html.twig`, `_item_form_partial.html.twig`, or the Live Component template), you can keep that line to keep the same behaviour, remove it, or replace it with your own form theme; it does not block or lock overrides. In dashboard pages, bundle `dashboard.js` can auto-register UX Autocomplete when Stimulus is available, so bundle templates can still initialize autocomplete even without app-specific JS entrypoints.

**Example:** to override the menu template used when rendering the tree, create:

```
templates/
  bundles/
    NowoDashboardMenuBundle/
      menu.html.twig
```

Copy the original from `vendor/nowo-tech/dashboard-menu-bundle/src/Resources/views/menu.html.twig` and adjust markup, blocks or variables as needed. The bundle passes `menuTree`, `menuCode` and `menuConfig` (and optional `menuConfig` is resolved by the template if omitted).

**Templates you can override:**

| Path | Purpose |
|------|---------|
| `menu.html.twig` | Frontend menu tree (sidebar, nav, etc.). Receives `menuTree`, `menuCode`, `menuConfig`. |
| `dashboard/layout.html.twig` | Demo/fallback full HTML shell. Defines `nowo_dashboard_menu_content` / `nowo_ui_content`. |
| `dashboard/base.html.twig` | Intermediate layout: flashes, modals, `parent()` asset stacking, `{% block body %}`. Pages extend this. |
| `dashboard/_icons.html.twig` | Inline SVG action icons. |
| `dashboard/index.html.twig` | Dashboard menu list. |
| `dashboard/show.html.twig` | Single menu detail and item table (links to reorder page). |
| `dashboard/show_items_reorder.html.twig` | Drag-and-drop tree reorder (SortableJS); posts to `items_reorder_tree`. |
| `dashboard/_sortable_tree_macro.html.twig` | Nested list markup for the reorder view. |
| `dashboard/_menu_dashboard_modals.html.twig` | Shared modals for show and reorder pages. |
| `dashboard/_dashboard_item_icon.html.twig` | Optional icon next to item labels in dashboard lists. |
| `dashboard/menu_form.html.twig` | Create/edit menu form. |
| `dashboard/item_form.html.twig` | Create/edit menu item form. |
| `dashboard/copy_menu.html.twig` | Copy menu form. |
| `dashboard/import.html.twig` | Import menus from JSON (standalone page). |
| `dashboard/_import_partial.html.twig` | Partial for import form (loaded in modal). |
| `dashboard/_menu_form_partial.html.twig` | Partial used in menu form. |
| `dashboard/_item_form_partial.html.twig` | Partial used in item form. |
| `dashboard/_copy_menu_partial.html.twig` | Partial used in copy form. |
| `components/ItemFormLiveComponent.html.twig` | Live Component template for the item form (modal). |
| `dashboard/_modal.html.twig` | *(2.3.0)* Modal shell embedded by every dashboard modal (prefer `dashboard.ui.partials.modal`). |
| `dashboard/_pagination.html.twig` | *(2.3.0)* Menus list pagination (prefer `dashboard.ui.partials.pagination`). |
| `dashboard/_row_action_inner.html.twig` | *(2.3.0)* Icon / label of one row action (prefer `dashboard.ui.partials.row_action_inner`). |
| `dashboard/_menu_properties.html.twig` | *(2.3.0)* Menu properties / CSS classes panels (show and reorder pages). |
| `dashboard/_delete_confirm_body.html.twig` | *(2.3.0)* Body of the delete confirmation modals. |
| `Collector/dashboard_menu.html.twig` | Web debug toolbar / profiler panel. |

**Dashboard reorder:** the item table (`show`) links to a separate page for drag-and-drop ordering. Symfony registers `nowo_dashboard_menu_dashboard_show_items_reorder` (GET `/{id}/items/reorder`) and `nowo_dashboard_menu_dashboard_items_reorder_tree` (POST `/{id}/items/reorder-tree`, CSRF). Bundle templates use the `dashboard_routes` map (`show_items_reorder`, `items_reorder_tree` keys → those full names) so overrides stay aligned if routing prefixes change.

**Dashboard forms:** bundle dashboard templates use Symfony Form Types for both full CRUD forms and lightweight search / CSRF-only actions. When you override `dashboard/index.html.twig`, `dashboard/show.html.twig`, `dashboard/show_items_reorder.html.twig`, or shared modals, prefer `form_start()` / `form_end()` (FormKit profile `dashboard_menu`) instead of literal HTML `<form>` tags so CSRF field names, token ids, and widget classes stay aligned with the controller and JS hooks.

**Dashboard layout:** besides overriding `dashboard/layout.html.twig` in the bundle path above, you can keep using the bundle layout and only change the **wrapper** via config: set `dashboard.layout_template` in `nowo_dashboard_menu.yaml` to your app layout (e.g. `base.html.twig`) so the dashboard uses your shell (see [CONFIGURATION.md](CONFIGURATION.md#dashboard)). Overriding the file gives full control over the dashboard HTML; the config option only swaps the extended template.

**CSS stack (REQ-UI-001 / REQ-UI-001-kit):** set `dashboard.css_framework` (`bootstrap5` default, or `tailwind` / `foundation` / `custom` / …) and optionally `dashboard.icon_set`. Markup always includes semantic `nowo-ui-*` classes; framework classes come from **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** macros:

```twig
{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}
```

CSS loads from the kit named package; dashboard behaviour JS stays on `nowo_dashboard_menu` (REQ-ASSETS-004):

```twig
<link href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}" rel="stylesheet">
<script src="{{ asset('js/dashboard.js', 'nowo_dashboard_menu') }}" defer></script>
```

When the host does not configure `nowo_ui_kit`, this bundle prepends `nowo_ui_kit.css_framework` / `icon_set` from the dashboard keys so `ui.btn('primary')` and friends resolve the same stack. Explicit host `nowo_ui_kit` config wins.

`nowo-ui.css` (from UiKit) uses CSS custom properties (`--nowo-ui-primary`, `--nowo-ui-surface`, `--nowo-ui-border`, `--nowo-ui-text`, `--nowo-ui-muted`, …) with slate/blue defaults. Remap under your host chrome (e.g. `.kit-admin`) without forking templates:

```css
.kit-admin {
  --nowo-ui-primary: var(--brand-primary);
  --nowo-ui-text: var(--brand-ink);
  --nowo-ui-border: var(--brand-border);
  --nowo-ui-surface: var(--brand-surface);
}
```

When `layout_template` points at the **project** layout, the demo CDN is skipped; your layout must expose `stylesheets` / `javascripts` (bundle `base.html.twig` calls `{{ parent() }}`). Twig globals: `nowo_dashboard_layout_template`, `nowo_dashboard_menu_css_framework`, `nowo_dashboard_menu_icon_set` (and UiKit globals `nowo_ui_kit_css_framework` / `nowo_ui_kit_icon_set`).

After adding or changing template overrides, clear the Twig cache if needed: `php bin/console cache:clear`.

### Overriding translations

The bundle uses the translation domain **NowoDashboardMenuBundle** for all its strings: dashboard UI (titles, buttons, labels, pagination), form labels and validation messages. Translation files in the bundle are named `NowoDashboardMenuBundle.{locale}.yaml` (e.g. `NowoDashboardMenuBundle.en.yaml`, `NowoDashboardMenuBundle.es.yaml`).

Keys are structured in YAML under `dashboard` (e.g. `dashboard.title`, `dashboard.new_menu`) and under `form` for form labels and validation (e.g. `form.copy_menu_type.code.regex_message`). To override a string, add the same key in your app's translation files for that domain; your app's translations take precedence.

**Override bundle strings** — create or edit `translations/NowoDashboardMenuBundle.{locale}.yaml` in your project (e.g. `NowoDashboardMenuBundle.en.yaml`, `NowoDashboardMenuBundle.es.yaml`):

```yaml
# Override bundle strings – same domain (NowoDashboardMenuBundle) and key structure
dashboard:
  title: My menu admin
  title_suffix: Menus
  menus: Menu list
  new_menu: Create menu
  search_placeholder: "Search…"

form:
  copy_menu_type:
    code:
      regex_message: "Custom validation message."
```

You only need to define the keys you want to change; the rest fall back to the bundle’s translations. Clear the cache after changing translations: `php bin/console cache:clear`.

## Customising the admin chrome

*Since 2.3.0.* The admin dashboard can be themed **without copying page templates** into `templates/bundles/NowoDashboardMenuBundle/`. Three layers, all opt-in (with the defaults the markup is the same as before):

1. **Config** (`nowo_dashboard_menu.dashboard.ui`): a CSS class map, partial paths, form themes and a few layout switches.
2. **Blocks**: every page and partial is split into named blocks. Point `dashboard.ui.templates.<page>` to a host template that `{% extends %}` the bundle page and overrides only the blocks you need (no `templates/bundles/` copy, so vendor updates still reach you).
3. **Layout variables**: pages expose their header actions as `nowo_dashboard_menu_header_actions` so your `layout_template` can place them in the page header.

What must stay intact in any override: the modal ids (`modal-menu-form`, `modal-menu-copy`, `modal-item-form`, `modal-import`, `modal-menu-new`, `modal-delete-confirm`, `modal-delete-item-confirm`), the `*-body` ids of fetch-loaded modals, the `btn-*-menu` / `btn-*-item` / `btn-import` classes with their `data-*` attributes, `#nowo-menu-sortable-*`, `data-import-form` and the Live `data-action` / `data-live-action-param` attributes. They are `dashboard.js` hooks.

### Configuration

```yaml
nowo_dashboard_menu:
    dashboard:
        layout_template: 'admin/menus_layout.html.twig'
        css_framework: tailwind
        ui:
            include_ui_kit_css: false        # the host layout already loads nowo-ui.css
            header_actions_in_layout: true   # layout prints nowo_dashboard_menu_header_actions
            index_view: table                # list (default) | table
            actions_column: last             # items table: first (default) | last
            row_action_display: text         # null (default: SVG icons) | icon | text | icon_text
            region_attribute: data-testid    # tags the menus / items / sortable regions (E2E)
            header_action_variants:          # one primary CTA per header: repaint single actions
                edit_menu: secondary
            form_themes: ['form/admin_theme.html.twig']
            classes:
                button_primary: btn-primary
                button_secondary: btn-ghost
                button_danger: btn-danger
                table_wrap: 'panel overflow-x-auto'
                table: data-table
                row_action: 'btn-ghost btn-sm btn-label'
                row_action_danger: 'btn-danger btn-sm btn-label'
                modal_form: kit-modal__panel
                modal_content: confirm-dialog__content
                modal_section: kit-modal__section
                modal_actions: confirm-dialog__actions
            partials:
                modal: 'admin/_modal.html.twig'
                pagination: '@NowoUiKitBundle/partials/_pagination.html.twig'
```

| Option | Default | Effect |
|--------|---------|--------|
| `include_ui_kit_css` | `true` | `dashboard/base.html.twig` appends UiKit `nowo-ui.css` after the layout stylesheets. Set `false` when your layout loads it (re-appending it reasserts the UiKit `:root` tokens over your remap). |
| `header_actions_in_layout` | `false` | Pages stop rendering their header (back link, `<h1>`, primary actions) and the index toolbar; the same HTML is available to the layout as `nowo_dashboard_menu_header_actions` (the bundle demo layout prints it above the content). |
| `index_view` | `list` | `table` renders the menus as a table (code, name, items, context, actions) with the empty state inside the table. |
| `actions_column` | `first` | `last` moves the actions column of the items table (show page) to the end. |
| `row_action_display` | `null` | `null` keeps the bundle SVG icons. `icon` / `text` / `icon_text` render `partials.row_action_inner` with a visually-hidden or visible label (same values as UiKit `row_actions_display`). |
| `region_attribute` | `null` | Attribute set on the menus table (`dashboard-menu-menus-table`), the items table (`dashboard-menu-items-table`) and the sortable panel (`dashboard-menu-sortable`). |
| `header_action_variants` | `{}` | *(2.4.1)* Button variant per page header action (block `dashboard_header_actions`): `primary` \| `secondary` \| `ghost` \| `danger` \| `link`. Keys: `export_all`, `import`, `new_menu` (index); `export`, `copy`, `edit_menu`, `edit_config`, `reindex_positions`, `check_parent_cycles`, `add_item` (show / reorder). A variant uses class-map key `button_<variant>` when set, else UiKit `ui.btn(variant)`. Unset actions keep the bundle variant (`primary` for New menu / Edit menu / Add item, `secondary` otherwise). Header links (reorder / table view) keep `button_small`. Unknown keys or variants fail at config load. |
| `form_themes` | `[]` | Form themes applied to every dashboard form: search, reorder, modal bodies and the Live item form. |
| `classes.*` | `null` | CSS class map (see below). `null` = UiKit macro result for `css_framework`; a string replaces the bundle class; `""` removes it. |
| `partials.pagination` | bundle `_pagination.html.twig` | Menus list pagination. Receives `pagination` (`page`, `current_page`, `per_page`, `total`, `total_pages`), `item_count`, `route`, `route_params`, `query`, `nav_label`, `nav_trans_domain`, so `@NowoUiKitBundle/partials/_pagination.html.twig` works as is. |
| `partials.row_action_inner` | bundle `_row_action_inner.html.twig` | Content of one row action. Receives `display`, `icon` (bundle icon name), `icon_name` (UiKit icon name) and `label`, the UiKit `_action_inner` contract. |
| `partials.modal` | bundle `_modal.html.twig` | Shell of every dashboard modal, used with `{% embed %}`. See [Modal shell](#modal-shell). |
| `templates.*` | bundle templates | Page / partial rendered by the controller: `index`, `show`, `show_items_reorder`, `menu_form_partial`, `item_form_partial`, `copy_menu_partial`, `import_partial`, `modals`. |

**Class map keys** (default in brackets):

| Area | Keys |
|------|------|
| Buttons | `button_primary` [`ui.btn('primary')`], `button_secondary` [`ui.btn('secondary')`], `button_danger` [`ui.btn('danger')`], `button_link` [`ui.btn('link')`, back link], `button_ghost` [`ui.btn('ghost')`, only via `header_action_variants`], `button_small` [`ui.btn('secondary', 'sm')`, reorder / table view links] |
| Page | `page_header` (show / reorder header row), `toolbar` [`ui.toolbar()`], `search_form` [`ui.search_form()`], `search_input` [`ui.search_input()`], `info_panel` (menu properties), `inline_form` [`d-inline`], `empty` [`ui.empty()`], `empty_action` ("add one" button), `muted` [`ui.muted()`] |
| Badges | `badge` [`ui.badge()`, item count], `badge_base`, `badge_type`, `badge_unanimous`, `badge_non_unanimous` |
| Lists / tables | `list`, `list_item`, `table_wrap` [`ui.table_wrap()`], `table` [`ui.table()`], `table_header_row`, `table_row`, `row_actions` [`ui.row_actions()`], `row_action` [`ui.action('edit')`, …], `row_action_danger` [`ui.action('delete')`], `row_action_disabled` [`disabled`] |
| Reorder page | `sortable_panel` [`card border mb-3`], `sortable_header` [`card-header …`], `sortable_body` [`card-body`] |
| Modals | `modal_loading` (loading placeholder), `modal_form` (form class; `dm-dashboard-form` is always appended), `modal_content` (wrapper around the fields), `modal_section` (section headings become `<p class="…">`), `modal_message` (delete confirmation text), `modal_actions` (footer wrapper; when set, the body renders **Cancel + Submit** in it) |

The Twig function `dashboard_menu_ui_class(key, default)` returns the configured class or the default; the whole config is the Twig global `nowo_dashboard_menu_ui`.

### Blocks

| Template | Blocks |
|----------|--------|
| `dashboard/index.html.twig` | `dashboard_page_header` (empty by default), `dashboard_header_actions`, `dashboard_search`, `dashboard_toolbar`, `dashboard_menus`, `dashboard_menus_list`, `dashboard_menus_table`, `dashboard_menu_row_actions`, `dashboard_empty_state`, `dashboard_pagination`, `dashboard_new_menu_modal` |
| `dashboard/show.html.twig` | `dashboard_page_header`, `dashboard_header_title`, `dashboard_header_links`, `dashboard_header_actions`, `dashboard_menu_properties`, `dashboard_empty_state`, `dashboard_items_table`, `dashboard_item_cells`, `dashboard_item_row_actions` |
| `dashboard/show_items_reorder.html.twig` | `dashboard_page_header`, `dashboard_header_title`, `dashboard_header_links`, `dashboard_header_actions`, `dashboard_menu_properties`, `dashboard_empty_state`, `dashboard_sortable`, `dashboard_sortable_header` |
| `dashboard/_menu_dashboard_modals.html.twig` | `dashboard_modal_menu_form`, `dashboard_modal_menu_copy`, `dashboard_modal_item_form`, `dashboard_modal_import`, `dashboard_modal_delete_menu`, `dashboard_modal_delete_item` |
| `dashboard/base.html.twig` | `nowo_dashboard_menu_config_island`, `nowo_ui_flashes`, `body`, `nowo_ui_modals` (unchanged) |

Header blocks are rendered once at the top of the page and stored in `nowo_dashboard_menu_header_actions` (show / reorder: `dashboard_header_links` + `dashboard_header_actions`); with `header_actions_in_layout: false` the page prints the same HTML in its header / toolbar. Row-action blocks run inside the loop (`menu` or `item` in context).

Override a block from a host template wired with `templates.*`:

```twig
{# templates/admin/menus/index.html.twig — dashboard.ui.templates.index #}
{% extends '@NowoDashboardMenuBundle/dashboard/index.html.twig' %}

{% block dashboard_empty_state %}
    <div class="empty-state">{{ 'dashboard.no_menus_yet'|trans({}, 'NowoDashboardMenuBundle') }}</div>
{% endblock %}
```

### Modal shell

Every modal is rendered with `{% embed dashboard.ui.partials.modal %}`. The shell receives `modal_id`, `modal_label_id`, `modal_title`, `modal_body_id` (may be empty), `modal_size_class` (from `dashboard.modals`), `modal_scrollable` and `modal_kind` (`form` | `confirm`), and must define `{% block modal_body %}`. A `<dialog>` shell:

```twig
{# templates/admin/_modal.html.twig #}
{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}
<dialog class="nowo-ui-modal confirm-dialog{% if modal_size_class == 'modal-lg' %} confirm-dialog--wide{% endif %}" id="{{ modal_id }}" aria-labelledby="{{ modal_label_id }}">
    <div class="confirm-dialog__panel">
        <div class="confirm-dialog__header">
            <h2 class="confirm-dialog__title" id="{{ modal_label_id }}">{{ modal_title }}</h2>
            <button type="button" class="btn-close" {{ ui.modal_dismiss_attrs()|raw }} aria-label="{{ 'dashboard.close'|trans({}, 'NowoDashboardMenuBundle') }}"></button>
        </div>
        <div class="kit-modal__body"{% if modal_body_id %} id="{{ modal_body_id }}"{% endif %}>{% block modal_body %}{% endblock %}</div>
    </div>
</dialog>
```

### Example: a host themed with config + blocks only

A host with its own Administration shell (sidebar, page heading, design tokens) and no `templates/bundles/NowoDashboardMenuBundle/` folder:

```yaml
# config/packages/nowo_dashboard_menu.yaml
nowo_dashboard_menu:
    dashboard:
        enabled: true
        layout_template: 'admin/menus_layout.html.twig'
        css_framework: tailwind
        ui:
            include_ui_kit_css: false
            header_actions_in_layout: true
            index_view: table
            actions_column: last
            row_action_display: text
            classes:
                button_primary: btn-primary
                button_secondary: btn-ghost
                button_small: btn-ghost
                button_danger: btn-danger
                search_form: 'toolbar mb-4'
                table_wrap: 'panel overflow-x-auto'
                table: 'data-table w-full text-sm'
                table_row: row-hover
                row_action: 'btn-ghost btn-sm btn-label'
                row_action_danger: 'btn-danger btn-sm btn-label'
                row_action_disabled: opacity-40
                info_panel: 'panel mb-4'
                sortable_panel: panel
                modal_form: kit-modal__panel
                modal_content: confirm-dialog__content
                modal_section: kit-modal__section
                modal_message: confirm-dialog__body
                modal_actions: confirm-dialog__actions
            partials:
                modal: 'admin/_modal.html.twig'
                pagination: '@NowoUiKitBundle/partials/_pagination.html.twig'
```

```twig
{# templates/admin/menus_layout.html.twig #}
{% extends 'admin/base.html.twig' %}

{% block page_title %}{% block dashboard_title %}{{ 'dashboard.title'|trans({}, 'NowoDashboardMenuBundle') }}{% endblock %}{% endblock %}
{% block page_actions %}{{ nowo_dashboard_menu_header_actions|default('') }}{% endblock %}

{# base.html.twig nests the page body, flashes and modals in this block #}
{% block content %}{% block nowo_dashboard_menu_content %}{% endblock %}{% endblock %}
```

Two small host templates (layout bridge + modal shell) and config are enough: the bundle pages, partials and the Live item form keep coming from the vendor, so new fields and JS hooks arrive with `composer update`. The layout must still expose `stylesheets` / `javascripts` blocks (the bundle base calls `{{ parent() }}`). Spacing utilities the class map does not reach (table cell padding, section gaps) belong in the host CSS, e.g. `.admin .data-table td { … }`.

## Items with children (parent, link vs section)

Hierarchy is defined by the **Parent** field when creating or editing an item in the dashboard: each item can have a parent (or “— Root —” for top-level). No extra “type” is needed to mark something as a parent; if any item has this one as parent, it will have children in the tree.

- **Parent with link:** Use type **Link** and set a route or URL. The item is clickable and, when the menu has **nested collapsible** enabled, it shows a chevron to expand/collapse its children. Children are rendered in a nested list (and stay visible by default when the current route is in that branch).
- **Parent without link:** Use type **Section**. The label is not a real navigation target. When the section has children and a **collapsible** row is shown (nested collapsible / per-item section collapsible), the bundle outputs the same **structure as a link with children:** `<a class="… menu-section-label-link">` (styled like a heading; not meant for navigation) plus the chevron `button` that toggles the nested list. When the section is **always expanded** (no collapse UI) or has no children, the label stays a **`<span>`** as before.
- **Section — “Section children collapsible: no”** (per-item field `sectionCollapsible` = **No**): the bundle renders each child as a **top-level sibling** `<li>` under the same `<ul>` as the section row (no nested `<ul>` under the section). Use this when you want a visual heading followed by links that behave like independent menu rows. **Yes** / **Inherit** keep the usual nested list (and chevron when collapsible settings apply).

So: **Link** = clickable, can have children (optional chevron + collapse). **Section** = label only, can have children (optional chevron + collapse). Both support any depth (children, grandchildren, etc.). Enable **nested_collapsible** on the menu (in config or in the dashboard) so that items with children get the expand/collapse control.

## Resolving by context (context sets)

Menus with the same `code` can have different **context** (a JSON key-value map stored on the `Menu` entity, e.g. `{"partnerId": 1, "operatorId": 1}`). You pass an ordered list of context objects; the first menu that matches `code` + context is used. Use `{}` or `null` to match the menu with no context (fallback).

In Twig:

```twig
{% set contextSets = [{ 'partnerId': 1, 'operatorId': 1 }, { 'partnerId': 1 }, {}] %}
{% set tree = dashboard_menu_tree('sidebar', null, contextSets) %}
{% set config = dashboard_menu_config('sidebar', contextSets) %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar', menuConfig: config } %}
```

In the API, send the same list as the `_context_sets` query parameter (JSON-encoded array of objects).

## JSON API

`GET /api/menu/{code}` returns a JSON array of root nodes. Each node has:

- `label` (translated for the request locale)
- `href` (resolved URL)
- `routeName` (if internal route, else null)
- `icon` (resolved icon identifier, e.g. after `icon_library_prefix_map`; null if none)
- `itemType` (e.g. `link`, `section`)
- `children` (same structure, recursively)

Query parameters: `_locale` overrides the request locale; `_context_sets` (JSON array of context objects) resolves which menu variant to use, same as in Twig.

**Link URLs:** Menu hrefs are built from the item’s route name and params. If a route needs path parameters (e.g. `id`, `slug`, `partnerMachineName`) that are not set on the item, the bundle fills them from the **main** (browser) request’s route params when available, so links keep the same context. During a controller forward/sub-request (which often drops `_route_params`), the main request is preferred; loose attributes on the current sub-request are used as a fallback. On URL generation failure, an error is added to the flash bag.

## Dashboard export and import

From the dashboard (list of menus) you can:

- **Export all menus** — downloads a single JSON file with every menu and its items (config + tree). Use for backup or moving menus between environments.
- **Export one menu** — same structure for a single menu (e.g. `menu-{code}-export.json`).
- **Import** — upload a JSON file produced by export (from a dedicated page or from the **Import** modal on the index). Choose strategy: **Skip existing** (do not overwrite menus that already exist for the same code+context) or **Replace** (replace items of existing menus). Errors (e.g. invalid format or missing `code`) are shown as flash messages. The index modal loads the form with **GET** and the query flag **`_modal=1`** so the server returns the partial template only; if you load the import form via `fetch` or XHR yourself, append `?_modal=1` (or POST with the hidden `_modal` field from the partial) for the same behaviour.

**Dashboard forms:** Menu and item forms are split into **definition** (pencil icon: code, name, context, icon for menus; type, icon, labels for items) and **configuration** (gear icon: permission checker, depth, collapsible, CSS for menus; position, parent, link, permission keys for items). New menu and new item show only definition; after saving you can edit configuration via the gear button. When adding a **child** item, the modal fixes the item type (link) and shows only `label` + per-locale translations (no icon/position fields). After any successful action (create, update, delete, copy, import, move), the app redirects to the request **Referer** when it is same-origin, otherwise to the usual list or show page.

The JSON format accepts: one menu as an object with `menu` + `items`; several menus as `menus` (array of `{ menu, items }`); or the **same list of blocks as a root JSON array** `[{ "menu": {...}, "items": [...] }, ...]` (equivalent to `menus`). Item trees and translations are preserved. For permissions, item payloads support both legacy `permissionKey` (single string) and `permissionKeys` (array of strings) plus `isUnanimous` (boolean): `true` = all keys must pass (AND), `false` = any key can pass (OR).

## Resolving menu by criteria (operatorId, partnerId, menu name)

You can resolve which menu to show using custom criteria (e.g. first try operatorId + partnerId + menu name, then partnerId + menu name, then menu name). Implement `Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface` and set `nowo_dashboard_menu.menu_code_resolver` to your service id.

```php
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class MyMenuCodeResolver implements MenuCodeResolverInterface
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
    ) {
    }

    public function resolveMenuCode(Request $request, string $hint): string
    {
        $operatorId = $request->attributes->getInt('operatorId', 0);
        $partnerId  = $request->attributes->getInt('partnerId', 0);

        // First try: operator + partner + name
        if ($operatorId > 0 && $partnerId > 0) {
            $code = sprintf('op_%d_partner_%d_%s', $operatorId, $partnerId, $hint);
            if ($this->menuRepository->findOneByCode($code) !== null) {
                return $code;
            }
        }
        // Then: partner + name
        if ($partnerId > 0) {
            $code = sprintf('partner_%d_%s', $partnerId, $hint);
            if ($this->menuRepository->findOneByCode($code) !== null) {
                return $code;
            }
        }
        // Fallback: name only
        return $hint;
    }
}
```

The same resolution is used in Twig (`dashboard_menu_tree('sidebar')`) and in the API (`GET /api/menu/sidebar`). Only links that pass the permission checker are included; parents (and section titles) with no visible children are automatically pruned.

## Current item matchers

By default, `CurrentRouteTreeDecorator` marks a link **current** when the request path equals the item href path and the request query contains every query param from the href (extra request params are allowed).

Hosts can OR additional rules by implementing `MenuCurrentMatcherInterface`. Autoconfigure tags the service as `nowo_dashboard_menu.current_matcher` (no manual tag needed):

```php
use Nowo\DashboardMenuBundle\Entity\MenuItem;
use Nowo\DashboardMenuBundle\Service\MenuCurrentMatcherInterface;
use Symfony\Component\HttpFoundation\Request;

final class OpsDefaultsCurrentMatcher implements MenuCurrentMatcherInterface
{
    public function isCurrent(MenuItem $item, Request $request, string $resolvedHref): bool
    {
        return $item->getRouteName() === 'admin_ops_defaults'
            && str_starts_with((string) $request->attributes->get('_route'), 'admin_ops_defaults');
    }
}
```

For the common case “menu points at an index route, pages use `*_section` / kit prefixes”, extend `AbstractRoutePrefixMenuCurrentMatcher`:

```php
use Nowo\DashboardMenuBundle\Service\AbstractRoutePrefixMenuCurrentMatcher;

final class AdministrationMenuCurrentMatcher extends AbstractRoutePrefixMenuCurrentMatcher
{
    protected function routePrefixes(): array
    {
        return [
            'admin_ops_defaults' => ['admin_ops_defaults'],
            'nowo_http_log_admin_index' => ['nowo_http_log_'],
        ];
    }
}
```

`hasCurrentInBranch` still bubbles from children after custom matchers run, so nested collapsible sections stay open.

## Request performance (menu lookup memo)

`MenuRepository::findOneByCodeAndContext` memoizes each `(code, context)` result for the current HTTP request (including misses). That avoids repeated `SELECT … FROM dashboard_menu WHERE code = ? AND attributes_key = ?` when Twig calls `dashboard_menu_config()` several times or a host setup detector checks the same codes.

The repository implements Symfony `ResetInterface` (`kernel.reset`). Independently of the resetter, `DashboardMenuWorkerStateSubscriber` clears the menu-lookup and tree-cache-version memos (and the href memo) at the start of every **main** request, so FrankenPHP worker mode with `reset_kernel: false` does not keep them across requests. Doctrine listeners also clear the menu memo after menu/item writes in the same request. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

Tree loading (`findMenuAndItemsRaw` / `MenuTreeLoader` cache pool) is separate; this memo only covers entity lookups by code+context.

## Permissions

Implement `Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface`:

```php
public function canView(MenuItem $item, mixed $context = null): bool
{
    $keys = $item->getPermissionKeys() ?? [];
    if ($keys === []) {
        return true;
    }

    if ($item->isUnanimous()) {
        foreach ($keys as $key) {
            if (!$this->authorizationChecker->isGranted($key, $context)) {
                return false;
            }
        }

        return true; // AND
    }

    foreach ($keys as $key) {
        if ($this->authorizationChecker->isGranted($key, $context)) {
            return true; // OR
        }
    }

    return false;
}
```

### Demo expression syntax (AND / OR / parentheses)

The demo checker in `demo/symfony8` supports simple expressions per key, and supports multiple keys via `permissionKeys` + `isUnanimous`:

- `keyA|keyB` → OR (at least one token must pass)
- `keyA&keyB` → AND (all tokens must pass)
- `(keyA|keyB)&keyC` → grouping with parentheses

Supported demo tokens:

- `authenticated`
- `admin`
- `path:/prefix` (matches current request path prefix)

Examples:

```text
authenticated|admin
path:/admin&(authenticated|admin)
(path:/operator|path:/admin)&authenticated
```

Notes:

- The demo checker evaluates parentheses first, then `&` (AND), then `|` (OR).
- Unknown tokens are denied by default in the demo implementation.
- For production logic, create your own checker and resolve tokens with your authorization system.

**Auto-registration:** Any service whose class implements `MenuPermissionCheckerInterface` is automatically included in the dashboard "Permission checker" dropdown; you do not need to add the tag in `services.yaml`. Deprecated definitions (`setDeprecated()` / `#[Deprecated]`) are skipped and the class is not loaded, so a retired checker does not appear in the dropdown. The same skip applies to auto-tagged `MenuLinkResolverInterface` services. The label in the dropdown can be set in either of these ways (optional):

- **Class constant:** `public const string DASHBOARD_LABEL = 'Your label';`
- **Attribute:** `#[PermissionCheckerLabel('Your label')]` on the class (use `Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel`)

If neither is set, the service id (e.g. FQCN) is used as the label. You can discover your checker via a service directory (e.g. `App\Service\`: `resource: '../src/Service/'`) so you do not need to register it explicitly in `services.yaml`. You can still order or override labels via config: `permission_checker_choices` as a list of service IDs or a map (service id => label) in `nowo_dashboard_menu.yaml` (see [CONFIGURATION](CONFIGURATION.md#permission_checker_choices)). Assign a checker to a menu in the dashboard (per-menu) or leave the menu with no checker for default allow-all behaviour. Manually tagging with `nowo_dashboard_menu.permission_checker` in `services.yaml` or using `#[AsTaggedItem]` remains supported.

## Item visibility rules

The bundle applies the following rules in order to decide which items appear in the rendered tree. All rules are enforced in `MenuTreeLoader` (see `buildTree` + `pruneEmptySections`).

### 1. Permission check (per item, all types)

Every item — regardless of type — is evaluated once by the configured `MenuPermissionCheckerInterface::canView($item, $context)`. If the checker returns `false`, the item is excluded from the tree; none of its children are rendered either.

The checker receives the full `MenuItem` object and can inspect `$item->getPermissionKeys()` and `$item->isUnanimous()` to implement OR / AND logic. The bundle does not enforce that logic itself; it is the checker's responsibility.

### 2. Link (leaf) — always visible when the checker allows

A link with **no children in the database** (`had_children = false`) is included as-is if the checker allows it.

```
visible = canView(item)
```

### 3. Link with children — visible only when it has at least one visible child

A link that **has children in the database** (`had_children = true`) is pruned from the tree when all its children are hidden by the permission checker (or are themselves pruned recursively).

```
visible = canView(item) AND count(visible_children) > 0
```

Rationale: a parent link with no visible children renders as a clickable element with nowhere useful to go and an empty submenu.

### 4. Section — visible only when it has at least one visible child

A section (`itemType = section`) that **has children in the database** (`had_children = true`) is pruned when all its children are hidden by the permission checker.

```
visible = canView(item) AND count(visible_children) > 0
```

A section that **has no children in the database** (`had_children = false`) is always kept when the checker allows it (it may be an intentional standalone heading).

### 5. Divider — visible when the checker allows

Dividers (`itemType = divider`) follow the same rule as leaf links: visible if and only if `canView` returns `true`. Dividers have no children.

### Summary table

| Item type | Has DB children | Checker passes | Visible? |
|-----------|-----------------|---------------|----------|
| Link (leaf) | No | Yes | ✅ |
| Link (leaf) | No | No | ❌ |
| Link (parent) | Yes | Yes | Only if ≥ 1 child visible |
| Link (parent) | Yes | No | ❌ |
| Section | No | Yes | ✅ |
| Section | No | No | ❌ |
| Section | Yes | Yes | Only if ≥ 1 child visible |
| Section | Yes | No | ❌ |
| Divider | — | Yes | ✅ |
| Divider | — | No | ❌ |

> **Note:** pruning is applied recursively from the deepest level up. A grandchild being hidden can cause a parent link or section to be pruned if all its children end up hidden.

## Web Profiler diagnostics

In `dev`, the **Dashboard menus** profiler panel includes:

- **Menus**: rendered menus, resolved context, root count, query segment counts and a compact tree summary.
- **Configuration**: effective bundle snapshot (key values) plus a raw merged config JSON block.
- **Permission checks**: per-link checker evaluation (`menu + link`, `permission key`, resolved checker, `result`) with sortable columns.
- **Tab deep-linking**: collector tabs persist in the URL hash (`#nowo-dm-tab-menus`, `#nowo-dm-tab-configuration`, `#nowo-dm-tab-permission-checks`) and are restored after refresh.

These diagnostics are request-scoped and intended for troubleshooting runtime selection (e.g. checker fallback, context resolution, visibility pruning).
