# Usage

## Table of contents

- [Twig](#twig)
- [Overriding templates and translations](#overriding-templates-and-translations)
  - [Overriding templates](#overriding-templates)
  - [Overriding translations](#overriding-translations)
- [Customising the admin chrome](#customising-the-admin-chrome)
- [Nested collapse without Bootstrap JS](#nested-collapse-without-bootstrap-js)
  - [Initial state of nested groups (`nested_default`)](#initial-state-of-nested-groups-nested_default)
- [Menu render options (link-row groups, dotted links, chevron icon)](#menu-render-options-link-row-groups-dotted-links-chevron-icon)
- [Items with children (parent, link vs section)](#items-with-children-parent-link-vs-section)
- [Resolving by context (context sets)](#resolving-by-context-context-sets)
- [JSON API](#json-api)
- [Dashboard export and import](#dashboard-export-and-import)
- [Resolving menu by criteria (operatorId, partnerId, menu name)](#resolving-menu-by-criteria-operatorid-partnerid-menu-name)
- [Current item matchers](#current-item-matchers)
- [Request performance (menu lookup memo)](#request-performance-menu-lookup-memo)
- [Permissions](#permissions)
  - [Demo expression syntax (AND / OR / parentheses)](#demo-expression-syntax-and--or--parentheses)
- [Standard Symfony host](#standard-symfony-host)
- [DDD host](#ddd-host)
  - [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
  - [Keeping your domain free of the bundle](#keeping-your-domain-free-of-the-bundle)
  - [Reading the tree without entities](#reading-the-tree-without-entities)
  - [Known limitations](#known-limitations)
- [Item visibility rules](#item-visibility-rules)
  - [1. Permission check (per item, all types)](#1-permission-check-per-item-all-types)
  - [2. Link (leaf) — always visible when the checker allows](#2-link-leaf--always-visible-when-the-checker-allows)
  - [3. Link with children — visible only when it has at least one visible child](#3-link-with-children--visible-only-when-it-has-at-least-one-visible-child)
  - [4. Section — visible only when it has at least one visible child](#4-section--visible-only-when-it-has-at-least-one-visible-child)
  - [5. Divider — visible when the checker allows](#5-divider--visible-when-the-checker-allows)
  - [Summary table](#summary-table)
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

**Templates you can override:** `menu.html.twig` (frontend menu tree: receives `menuTree`, `menuCode`, `menuConfig`) and `Collector/dashboard_menu.html.twig` (profiler panel). The dashboard pages (2.7) are on the UiKit page contract: do **not** copy them into `templates/bundles/` — use `nowo_dashboard_menu.web_ui.templates.<page>` with a thin `{% extends '@!NowoDashboardMenuBundle/…' %}` that overrides a data block, and theme them through `nowo_ui_kit.theme`. The list of pages, fragments, data blocks and view models is in [TEMPLATES.md](TEMPLATES.md); see [Customising the admin chrome](#customising-the-admin-chrome).

**Override vs upgrade (freeze rule).** An application file at the override path always wins and does not pick up vendor changes for that path until you remove or merge it: a full-file override freezes that `<subpath>` on every `composer update` while the rest of the bundle keeps upgrading. Prefer the upgrade-safe options first: `nowo_dashboard_menu.web_ui.templates.<page>` with a thin `{% extends '@!NowoDashboardMenuBundle/…' %}` that overrides one data block, the admin chrome hooks, `nowo_ui_kit.theme`, and the menu `config` (classes, icons, depth) for the frontend `menu.html.twig`.

**Recovering upstream after a freeze:** delete (or rename aside) the application override, clear the cache (`php bin/console cache:clear`) and re-apply only the customisation you still need. Diff your copy against the new vendor file under `vendor/nowo-tech/dashboard-menu-bundle/src/Resources/views/` before re-copying.

**Dashboard reorder:** the item table (`show`) links to a separate page for drag-and-drop ordering. Symfony registers `nowo_dashboard_menu_dashboard_show_items_reorder` (GET `/{id}/items/reorder`) and `nowo_dashboard_menu_dashboard_items_reorder_tree` (POST `/{id}/items/reorder-tree`, CSRF).

**Dashboard layout and CSS stack:** the pages render in `nowo_ui_kit.layouts.admin` (or `web_ui.layout_template`), which loads `nowo-ui.css`, the UiKit scripts (`nowo-ui-confirm.js`, `nowo-ui-dialog.js`…) and `nowo_ui_tokens()`; the pages stack `css/dashboard.css` and `js/dashboard.js` (asset package `nowo_dashboard_menu`) in `nowo_ui_styles` / `nowo_ui_scripts`. The CSS framework, icon set and row-action display are UiKit's (`nowo_ui_kit.css_framework`, `icon_set`, `row_actions_display`); colours come from the `--nowo-ui-*` tokens (`nowo_ui_kit.theme.tokens`). A 1.x `dashboard.layout_template` rendering `nowo_dashboard_menu_content` still works through a deprecated bridge.

After adding or changing template overrides, clear the Twig cache if needed: `php bin/console cache:clear`.

**Forms (REQ-TWIG-005):** every dashboard form (menu, item, copy, import, search, CSRF-only actions) is a FormKit Type (profile `dashboard_menu`, see [CONFIGURATION](CONFIGURATION.md#formkit-profile-dashboard_menu)) created in the controller and rendered with `form_start` / `form_widget` / `form_row` / `form_end`. The focused section of the menu and item forms (`?section=basic|icon|config|identity`) posts back as the form's unmapped hidden child `_section` (`menu[_section]`, `menu_item[_section]`), rendered with `{% if form._section is defined %}{{ form_widget(form._section) }}{% endif %}`. Host overrides and the demo must not reintroduce raw `<form>` / `<input>` / `<textarea>` / `<select>`; `make check-no-raw-html-form` (part of `make release-check`) fails on them, with no allowlist. The controller still accepts a top-level `_section` field from templates overridden before 2.12.

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

**Maintainer tool — missing keys / Google Translate:** `php bin/console nowo_dashboard_menu:translations:google-sync --base-locale=en [--translate-missing --write] [--timeout=20]` reports keys missing per locale and can fill them through the Google Translate REST API. Each HTTP call is bounded by `--timeout` (seconds, default **20**, REQ-RUNTIME-001); on timeout or any API error the source string is kept, so the command never hangs and never writes an empty value. It runs from the CLI only (no web request), so no PHP / FrankenPHP request deadline applies.

## Customising the admin chrome

*Since 2.7 (REQ-UI-003).* The admin dashboard is built on the **UiKit page contract**: every page extends a UiKit
scaffold (`page/list`, `page/show`, `page/form`; the drag reorder page is a declared unique page) and renders inside
`nowo_ui_kit.layouts.admin`. Theme it — together with every other Nowo UI bundle — once:

1. **Layout**: `nowo_ui_kit.layouts.admin` → your layout or a one-file bridge rendering the UiKit canonical blocks
   (`demo/symfony8/templates/layouts/admin.html.twig` is a complete example). `nowo_dashboard_menu.web_ui.layout_template`
   overrides it for the dashboard only.
2. **Theme**: `nowo_ui_kit.theme` (tokens, slot classes, attributes, partials); dashboard-only exceptions under
   `nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle`.
3. **Per-page override** (rare): `nowo_dashboard_menu.web_ui.templates.<page>` → a template that extends
   `@!NowoDashboardMenuBundle/dashboard/<page>.html.twig` and overrides a data block.

```yaml
nowo_ui_kit:
    layouts: { admin: 'layouts/admin.html.twig' }
    theme:
        bundles:
            NowoDashboardMenuBundle:
                classes: { table: 'table table-sm table-hover' }
nowo_dashboard_menu:
    web_ui:
        templates:
            index: 'admin/dashboard_menu/index.html.twig'
```

```twig
{# templates/admin/dashboard_menu/index.html.twig #}
{% extends '@!NowoDashboardMenuBundle/dashboard/index.html.twig' %}
{% block nowo_ui_list_after %}<p class="text-muted">Menus are cached for 60 s.</p>{% endblock %}
```

Pages, data blocks, view models, dialog fragments and the hooks `dashboard.js` relies on (`data-nowo-dashboard-menu-dialog`,
`#modal-item-form-container`, `#nowo-menu-sortable-*`, `data-import-form`, the Live `data-action` attributes):
[TEMPLATES.md](TEMPLATES.md). Forms open in UiKit dialogs; every opener is a link to the full form page, so the
dashboard keeps working without JavaScript.

The 1.x hooks (`dashboard.ui.*`, the `dashboard_*` blocks, `nowo_dashboard_menu_header_actions`) are deprecated or
removed: see [UPGRADING — Template contract](UPGRADING.md#template-contract).

## Nested collapse without Bootstrap JS

Since **2.6.0**. Collapsible menus (`collapsible`, `nested_collapsible`) render Bootstrap 5 markup. Hosts that do not load Bootstrap's JavaScript can use the bundle script `js/menu-collapse.js` (≈1.6 kB gzip, no dependencies):

1. **Enable the attributes** (or set them yourself on any ancestor of the menu, see below):

   ```yaml
   nowo_dashboard_menu:
       menu_collapse:
           enabled: true
           storage_key: app.navCollapse   # optional; "" or null = do not remember
           nested_default: current        # optional (2.16.0); expanded (default) | collapsed | current
   ```

2. **Include the script once per page** (after `assets:install`). The partial prints an external `defer` script with the request `csp_nonce` when one is set — no inline code, no inline handlers:

   ```twig
   {{ include('@NowoDashboardMenuBundle/_menu_collapse_script.html.twig') }}
   {# same as: <script src="{{ asset('js/menu-collapse.js', 'nowo_dashboard_menu') }}" defer></script> #}
   ```

   Bundler hosts (Vite / Webpack / AssetMapper) can import the module instead of the built file and connect roots themselves (e.g. from a Stimulus controller's `connect()`):

   ```ts
   import { initMenuCollapse } from '../vendor/nowo-tech/dashboard-menu-bundle/src/Resources/assets/src/menu-collapse';
   initMenuCollapse(document);            // or new MenuCollapse(element, { storageKey: 'app.navCollapse' }).connect()
   ```

3. **Hide closed panels in CSS** if Bootstrap's CSS is not loaded: `.collapse:not(.show) { display: none; }` (or animate `grid-template-rows: 0fr → 1fr`; the root gets `is-nav-collapse-ready` two frames after the initial state is applied, so scope transitions to `[data-dashboard-menu-collapse].is-nav-collapse-ready`).

**Behaviour**

- One delegated `click` listener per root on `[data-bs-toggle="collapse"]` / `[data-dashboard-menu-collapse-toggle]` (`data-bs-target="#id"`, `href="#id"` or `aria-controls`). Nested roots are independent (a panel is toggled by its closest root only).
- Keeps `aria-expanded` on every toggle of a panel in sync (adds `aria-controls` when missing); closed panels get `inert` and `aria-hidden="true"`; the panel's `<li>` gets `is-expanded` / `is-collapsed`.
- Remembers the state per menu code in `localStorage` (`{ "<menu code>": { "<panel id>": bool } }`) and restores it on load; ancestors of the current item (`<li>` with the menu's `class_branch_expanded`, default `active-branch`, or a panel with `data-dashboard-menu-collapse-current`) are always opened, whatever was stored. Panels without a stored value keep the server-rendered state (`nested_default`). With an empty storage key nothing is read or written.
- Keeps Bootstrap's `collapsed` class on the toggles in sync (closed panel = `collapsed`).
- **Bootstrap JS present:** in `auto` mode (default) the script never toggles (Bootstrap does, so a click flips a panel once); it restores the state and listens to `show|shown|hidden.bs.collapse` to sync `inert` / ARIA and remember the state. In `always` mode it removes `data-bs-toggle` from its toggles so Bootstrap's data API ignores them.
- Dispatches `dashboard-menu:collapse-toggle` (bubbles; `detail: { menuCode, id, open }`) on the panel after each toggle.
- Connects on `DOMContentLoaded`, `turbo:load` and `turbo:frame-load` (idempotent); `window.NowoDashboardMenuCollapse.init(scope?)` connects markup added later.

**Attributes** (on the menu root or any ancestor you mark yourself):

| Attribute | Default | Meaning |
|-----------|---------|---------|
| `data-dashboard-menu-collapse` | — (required) | Marks the root; value = menu code (store namespace; empty = `menu`). |
| `data-dashboard-menu-collapse-mode` | `auto` | `auto` \| `always` (see above). |
| `data-dashboard-menu-collapse-storage-key` | `nowo.dashboardMenu.navCollapse` | `localStorage` key; empty = no persistence. |
| `data-dashboard-menu-collapse-branch-class` | `active-branch` | `<li>` class of an ancestor of the current item (forced open). |
| `data-dashboard-menu-collapse-current` (on a `.collapse` panel) | — | 2.16.0: the panel's branch contains the current item (forced open). Emitted by `menu.html.twig` when `menu_collapse.enabled`. |

### Initial state of nested groups (`nested_default`)

Since **2.16.0**. `menu_collapse.nested_default` decides how `menu.html.twig` renders the nested collapsible groups:

| Value | Groups rendered open | Toggle of a closed group |
|-------|----------------------|--------------------------|
| `expanded` (default) | all (2.15 markup, byte-identical) | — |
| `collapsed` | none | `class="… collapsed" aria-expanded="false"`, panel `class="collapse"` |
| `current` | those whose branch contains the current item (`hasCurrentInBranch`, the current item itself included) | same as `collapsed` |

`aria-controls` / panel ids are unchanged. It works with Bootstrap's collapse JS as well as the bundle script; with the script, a remembered state still wins for the other groups, so use `storage_key: ''` to always start from the server state:

```yaml
nowo_dashboard_menu:
    menu_collapse:
        enabled: true
        nested_default: current
        storage_key: ''
```

Per menu, merge it into the menu config; per include, `menuCollapseNestedDefault` wins over both:

```twig
{% set menuConfig = dashboard_menu_config('sidebar')|merge({ nested_default: 'collapsed' }) %}
{{ include('@NowoDashboardMenuBundle/menu.html.twig', { menuTree: dashboard_menu_tree('sidebar'), menuCode: 'sidebar', menuConfig: menuConfig }) }}
{{ include('@NowoDashboardMenuBundle/menu.html.twig', { menuTree: dashboard_menu_tree('sidebar'), menuCode: 'sidebar', menuCollapseNestedDefault: 'current' }) }}
```

Per include, `menuCollapse`, `menuCollapseMode`, `menuCollapseStorageKey` and `menuCollapseNestedDefault` override the `menu_collapse` config:

```twig
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar', menuCollapse: true, menuCollapseStorageKey: 'app.navCollapse' } %}
```

## Menu render options (link-row groups, dotted links, chevron icon)

Since **2.15.0**. Three options of `nowo_dashboard_menu.dashboard` change how `menu.html.twig` draws nested groups, so a
sidebar with collapsible groups needs no copy of the template (a full override freezes it on the copied version). The
defaults keep the 2.14 markup byte for byte.

```yaml
nowo_dashboard_menu:
    dashboard:
        nested_group_display: link_row        # collapsible (default) | link_row
        group_children_marker: dot            # none (default) | dot
        toggle_icon: 'tabler:chevron-down'    # null (default): the inline SVG triangle
        group_classes:                        # optional: classes of the added markup (defaults shown)
            toggle: menu-group-toggle
            label: menu-group-label
            chevron: menu-group-chevron
            dot: menu-group-dot
```

| Option | Values | Effect |
|--------|--------|--------|
| `nested_group_display` | `collapsible` \| `link_row` | `link_row`: a **section nested in another item** (depth > 1) whose children are collapsible renders as one `<button>` toggle row in the link style — `class="<link classes> <group_classes.toggle>[ <class_branch_expanded>]"`, the item icon, `<span class="<group_classes.label>">label</span>` and the chevron (`<group_classes.chevron>`) on the right — instead of the heading link plus the small chevron button. The `<div id="…" class="collapse show">` panel, `data-bs-toggle` / `aria-*` attributes and `menu_collapse` support are unchanged. Top-level sections keep their heading. |
| `group_children_marker` | `none` \| `dot` | `dot`: links whose parent is a section rendered as a nested list (not flattened by `sectionCollapsible: false`) show `<span class="<group_classes.dot>" aria-hidden="true"></span>` instead of their icon, styled by the host as a bullet. |
| `toggle_icon` | `null` \| UX icon name | Renders every expand / collapse chevron with `ux_icon(<name>)` (class `menu-nested-toggle-svg` in the existing toggles, `<group_classes.chevron>` in link rows) instead of the inline SVG. Validated as `prefix:name`; needs `symfony/ux-icons`. |

Per include, merge them into `menuConfig` (the same keys), e.g. one menu only:

```twig
{% set config = dashboard_menu_config('sidebar')|merge({ nested_group_display: 'link_row', group_children_marker: 'dot' }) %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: dashboard_menu_tree('sidebar'), menuCode: 'sidebar', menuConfig: config } %}
```

The bundle ships no CSS for the new elements; a starting point:

```css
button.menu-group-toggle { display: flex; align-items: center; gap: .5rem; width: 100%; border: 0; background: transparent; font: inherit; text-align: start; }
button.menu-group-toggle .menu-group-label { flex: 1 1 auto; min-width: 0; }
button.menu-group-toggle .menu-group-chevron { width: .9rem; height: .9rem; transition: transform .2s ease; }
button.menu-group-toggle[aria-expanded="false"] .menu-group-chevron { transform: rotate(-90deg); }
.menu-group-dot { display: inline-grid; place-items: center; width: 1rem; height: 1rem; }
.menu-group-dot::before { content: ''; width: .3rem; height: .3rem; border-radius: 50%; background: currentColor; opacity: .6; }
```

The demo home page shows the sidebar menu with the three options (card "Render options").

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

You can resolve which menu to show using custom criteria (e.g. first try operatorId + partnerId + menu name, then partnerId + menu name, then menu name). Implement `Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface` and alias the interface to your service:

```yaml
# config/services.yaml
services:
    Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface: '@App\Menu\MyMenuCodeResolver'
```

```php
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuCodeContextResolverInterface;

final class MyMenuCodeResolver implements MenuCodeContextResolverInterface
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
    ) {
    }

    public function resolveMenuCodeFor(MenuRequestContext $request, string $hint): string
    {
        $operatorId = (int) $request->routeParam('operatorId');
        $partnerId  = (int) $request->routeParam('partnerId');

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

`MenuRequestContext` carries the route name (`route`), the scalar route parameters (`routeParams`, `routeParam()`), the
path, the locale, the query parameters and the host of the main request; no headers, cookies or session. To build one
from a Symfony request yourself (your own HTTP edge code, or the unit tests of your resolvers and matchers), use the
public `MenuRequestContextFactory::fromRequest()` (2.20): it applies exactly the bundle's rules.

```php
use Nowo\DashboardMenuBundle\Service\MenuRequestContextFactory;

$request = Request::create('/admin/users');
$request->attributes->set('_route', 'admin_users');
self::assertTrue($matcher->isCurrentItemFor($item, MenuRequestContextFactory::fromRequest($request), '/admin'));
```

The same resolution is used in Twig (`dashboard_menu_tree('sidebar')`) and in the API (`GET /api/menu/sidebar`). Only links that
pass the permission checker are included; parents (and section titles) with no visible children are automatically
pruned.

A resolver written for 2.18 or earlier (`MenuCodeResolverInterface::resolveMenuCode(Request, string)`, aliased on
`MenuCodeResolverInterface`) keeps working; that interface is deprecated since 2.19 and removed in 3.0. When you alias
`MenuCodeContextResolverInterface` to your own service, the bundle uses it instead.

## Current item matchers

By default, `CurrentRouteTreeDecorator` marks a link **current** when the request path equals the item href path and the request query contains every query param from the href (extra request params are allowed).

Hosts can OR additional rules by implementing `MenuItemContextCurrentMatcherInterface`. Autoconfigure tags the service as `nowo_dashboard_menu.current_matcher` (no manual tag needed):

```php
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextCurrentMatcherInterface;

final class OpsDefaultsCurrentMatcher implements MenuItemContextCurrentMatcherInterface
{
    public function isCurrentItemFor(MenuItemView $item, MenuRequestContext $request, string $resolvedHref): bool
    {
        return $item->routeName === 'admin_ops_defaults'
            && str_starts_with((string) $request->route, 'admin_ops_defaults');
    }
}
```

`MenuCurrentMatcherInterface::isCurrent(MenuItem, Request, string)` and
`MenuItemViewCurrentMatcherInterface::isCurrentItem(MenuItemView, Request, string)` still work and are deprecated since
2.19 (removed in 3.0).

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

`AbstractRoutePrefixMenuCurrentMatcher` implements both the new and the deprecated matcher interface; subclasses that
only define `routePrefixes()` need no change now or in 3.0.

`hasCurrentInBranch` still bubbles from children after custom matchers run, so nested collapsible sections stay open.

## Request performance (menu lookup memo)

`MenuRepository::findOneByCodeAndContext` memoizes each `(code, context)` result for the current HTTP request (including misses). That avoids repeated `SELECT … FROM dashboard_menu WHERE code = ? AND attributes_key = ?` when Twig calls `dashboard_menu_config()` several times or a host setup detector checks the same codes.

The repository implements Symfony `ResetInterface` (`kernel.reset`). Independently of the resetter, `DashboardMenuWorkerStateSubscriber` clears the menu-lookup and tree-cache-version memos (and the href memo) at the start of every **main** request, so FrankenPHP worker mode with `reset_kernel: false` does not keep them across requests. Doctrine listeners also clear the menu memo after menu/item writes in the same request. See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

Tree loading (`findMenuAndItemsRaw` / `MenuTreeLoader` cache pool) is separate; this memo only covers entity lookups by code+context.

## Permissions

Implement `Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface`:

```php
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;

public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
{
    $keys = $item->permissionKeys;
    if ($keys === []) {
        return true;
    }

    if ($item->unanimous) {
        foreach ($keys as $key) {
            if (!$this->authorizationChecker->isGranted($key)) {
                return false;
            }
        }

        return true; // AND
    }

    foreach ($keys as $key) {
        if ($this->authorizationChecker->isGranted($key)) {
            return true; // OR
        }
    }

    return false; // unknown or refused keys: hidden (deny by default)
}
```

`MenuPermissionContext` replaces the `mixed` context of the deprecated ports:

| You pass (`dashboard_menu_tree(code, context)`, `MenuTreeLoader::loadTree(…, $permissionContext)`) | The checker receives |
|---|---|
| nothing (Twig, JSON API) | `request`: the main request as `MenuRequestContext`; no attributes |
| an array of plain values, e.g. `{tenant: 'acme'}` | `attributes` (scalars, null, lists of those; objects dropped); `request` null |
| a `MenuPermissionContext` | that object |
| a scalar | `attributes['value']` |
| any other object | an empty context |

Values the value object cannot carry (objects, nested maps, array entries with integer keys) are dropped; in debug the
bundle logs a warning naming them. **Deny when the attribute you need is missing**: a value-object checker that
depends on an attribute (`tenant`, a role, an id) must return false for keyed items when `hasAttribute()` is false,
instead of assuming a default. Pass ids and plain values, never entities or services.

**Unresolvable checker.** A menu with no checker shows every item (allow-all, the documented default). A menu whose
configured checker cannot be resolved (typo, removed service, a decorator implementing no checker port, an id from an
imported file) never falls back to allow-all since 2.19: items with permission keys are hidden, keyless items stay,
an error is logged and the profiler's DashboardMenu panel shows the fallback. Set `strict_permission_checker: true` to
throw a `LogicException` instead (off by default, so a typo never locks you out of the dashboard). `MenuImporter`
refuses a menu that names a checker unknown to the container or a label shared by several checkers, and stores the
service id when the file names a checker by its label.

**One variant per class.** When a checker, link resolver or matcher implements several variants of its port, the bundle
calls the oldest; the container build logs a warning (compiler log, plus a deprecation collected in debug) for such
host services (subclasses of
`AbstractRoutePrefixMenuCurrentMatcher` that do not override its methods are not reported).

`MenuPermissionCheckerInterface::canView(MenuItem, mixed)` and `MenuItemViewPermissionCheckerInterface::canViewItem(MenuItemView, mixed)`
still work (they receive the raw value, as before) and are deprecated since 2.19 (removed in 3.0). The bundled
`AllowAllMenuPermissionChecker` and `PermissionKeyAwareMenuPermissionChecker` implement both.

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

## Standard Symfony host

The zero-config path. Nothing to change when upgrading from 2.18:

1. `composer require nowo-tech/dashboard-menu-bundle`, map `Nowo\DashboardMenuBundle\Entity` on your entity manager
   (attribute mapping, `auto_mapping: true` is enough) and create the tables with
   `nowo_dashboard_menu:generate-migration`.
2. Render menus with `dashboard_menu_tree('sidebar')` / `dashboard_menu_config('sidebar')`; build them in the dashboard
   or import them with `MenuImporter`.
3. Add behaviour with autoconfigured services: a permission checker
   (`MenuItemContextPermissionCheckerInterface`, see [Permissions](#permissions)), link resolvers for `service` items,
   current-item matchers, a menu code resolver.

Checkers, resolvers and matchers written for 2.18 or earlier (`MenuPermissionCheckerInterface`,
`MenuLinkResolverInterface`, `MenuCurrentMatcherInterface`, `MenuCodeResolverInterface` and the 2.17 `MenuItemView*`
variants) keep working unchanged and receive the same values as before; they are deprecated since 2.19 (Symfony's
debug class loader reports them in `dev`) and removed in 3.0. See [UPGRADING](UPGRADING.md#to-2190).

## DDD host

### Using it from a DDD / hexagonal application

DashboardMenuBundle is a navigation (presentation) component with its own small persistence: the `Menu` and
`MenuItem` entities and their tables (`dashboard_menu`, `dashboard_menu_item`, optionally prefixed). It never maps
or references your entities: no `resolve_target_entities`, no base class or trait to extend. You map
`Nowo\DashboardMenuBundle\Entity` in `doctrine.orm.mappings` and create the tables with
`nowo_dashboard_menu:generate-migration`.

### Keeping your domain free of the bundle

Your domain does not know about menus. Connect them in the infrastructure layer through the bundle ports. Since 2.19
every port has a variant that takes only bundle-owned value objects (`Nowo\DashboardMenuBundle\Model`): the item as a
read-only `MenuItemView` (no Doctrine entity), the request as `MenuRequestContext` (no HttpFoundation `Request`) and the
permission context as `MenuPermissionContext` (no `mixed`):

| Need | Port (value objects only, 2.19+) | Deprecated variants (removed in 3.0) | Registration |
|------|----------------------------------|--------------------------------------|--------------|
| Hide items the user may not see | `MenuItemContextPermissionCheckerInterface::canViewItemFor(MenuItemView, MenuPermissionContext)` | `MenuPermissionCheckerInterface::canView(MenuItem, mixed)`, `MenuItemViewPermissionCheckerInterface::canViewItem(MenuItemView, mixed)` | auto-tagged; choose it per menu (`permission_checker_choices`) |
| Links or children computed from domain data | `MenuItemContextLinkResolverInterface::resolveItemHrefFor(MenuItemView, ?MenuRequestContext, MenuPermissionContext)` | `MenuLinkResolverInterface::resolveHref(MenuItem, ?Request, mixed)`, `MenuItemViewLinkResolverInterface::resolveItemHref(MenuItemView, ?Request, mixed)` | auto-tagged; item type `service` (`menu_link_resolver_choices`) |
| Custom "current item" rule | `MenuItemContextCurrentMatcherInterface::isCurrentItemFor(MenuItemView, MenuRequestContext, string)` | `MenuCurrentMatcherInterface::isCurrent(MenuItem, Request, string)`, `MenuItemViewCurrentMatcherInterface::isCurrentItem(MenuItemView, Request, string)` | autoconfigured with tag `nowo_dashboard_menu.current_matcher` |
| Pick the menu code per request | `MenuCodeContextResolverInterface::resolveMenuCodeFor(MenuRequestContext, string)` | `MenuCodeResolverInterface::resolveMenuCode(Request, string)` | alias `MenuCodeContextResolverInterface` to your service |
| Admin access | `DashboardMenuAccessCheckerInterface::canAccess()` | — | `security.access_checker` |

If a class implements several variants of one port, the bundle calls the oldest one (entity, then `MenuItemView` with
`Request` / `mixed`, then the value-object variant), so adding the new interface during a migration changes nothing
until you remove the old one. `MenuItemView` exposes `id`, `menuId`, `menuCode`, `parentId`, `itemType`, `label`,
`translations` (+ `labelForLocale()`), `position`, `linkType`, `routeName`, `routeParams`, `externalUrl`, `linkResolver`,
`runtimeHref`, `permissionKeys`, `unanimous`, `icon` and `targetBlank`. `MenuRequestContext` exposes `route`,
`routeParams` (scalars only, `routeParam()`), `path`, `locale`, `query` and `host` of the main request.
`MenuPermissionContext` exposes `request` (a `MenuRequestContext` or null) and `attributes` (plain values you passed,
see [Permissions](#permissions)).

Keep the adapters thin and delegate to an application service:

```php
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextPermissionCheckerInterface;

final class DomainMenuPermissionChecker implements MenuItemContextPermissionCheckerInterface
{
    public function __construct(private readonly AccessPolicy $policy) {} // your application port

    public function canViewItemFor(MenuItemView $item, MenuPermissionContext $context): bool
    {
        foreach ($item->permissionKeys as $key) {
            if (!$this->policy->isGranted($key)) { // unknown keys: not granted
                return false;
            }
        }

        return true;
    }
}
```

```php
use Nowo\DashboardMenuBundle\Model\MenuItemView;
use Nowo\DashboardMenuBundle\Model\MenuPermissionContext;
use Nowo\DashboardMenuBundle\Model\MenuRequestContext;
use Nowo\DashboardMenuBundle\Service\MenuItemContextLinkResolverInterface;

final class ProjectsMenuLinkResolver implements MenuItemContextLinkResolverInterface
{
    public function __construct(
        private readonly ListProjectsQuery $projects, // your application query
        private readonly UrlGeneratorInterface $urls,
    ) {}

    public function resolveItemHrefFor(MenuItemView $item, ?MenuRequestContext $request, MenuPermissionContext $context): string|array
    {
        $rows = [];
        foreach ($this->projects->forCurrentUser() as $i => $project) {
            $rows[] = ['label' => $project->name, 'href' => $this->urls->generate('project_show', ['id' => $project->id]), 'position' => $i];
        }

        return $rows;
    }
}
```

`tests/Integration/DddHost/HostPortsEquivalenceKernelTest.php` boots such a host (value-object ports only, dedicated
entity manager) next to a standard host written against the deprecated ports and checks that both render the same
tree.

### Reading the tree without entities

`MenuTreeLoader::loadTree()` returns arrays holding `MenuItem` entities (what `menu.html.twig` consumes). For an API,
an SPA or another UI, `MenuTreeLoader::loadTreeView($menuCode, $locale, $permissionContext, $contextSets)` returns the
same tree (same visibility rules, cache and permission checker) as a list of immutable `MenuNodeView`
(`item: MenuItemView`, `children`, `href`, `current`, `currentInBranch`). `href` and `current` are only filled for a
tree decorated with the current request: convert the result of `dashboard_menu_tree()` (or
`CurrentRouteTreeDecorator::decorate()`) with `MenuNodeView::listFromTree($tree)`.

`MenuItemView::$externalUrl`, `$runtimeHref` and `MenuNodeView::$href` are already passed through the same scheme
allowlist as the Twig path (`http`, `https`, `mailto`, `tel`, relative; null otherwise). If you build links from
other values (route params, your own data), apply `Nowo\DashboardMenuBundle\Util\ExternalUrlPolicy::sanitize()`
before putting them in an `href`.

### Domain-driven entries and render configuration (2.18)

- `loadTreeView()` dispatches `MenuTreeBuiltEvent` (menu code, locale, `MenuNodeView` list): a listener can append
  entries built from your own read model (e.g. the user's projects, with `runtimeHref`) or remove nodes with
  `setNodes()`, without a `service` item and without the bundle's entities.
- `loadMenuView()` returns the render configuration as a `MenuView` DTO (code, name, icon, context, CSS classes,
  collapse flags); `loadMenu()` keeps returning the entity for standard hosts.

### Dedicated entity manager (2.18)

`nowo_dashboard_menu.doctrine.entity_manager: dashboard_menu` maps the two menu entities on that manager only (also
when your default manager has `auto_mapping: true`) and makes the dashboard, the importer, the item form Live Component
and the migration command write there. The bundle then never flushes, clears or resets your managers; a closed
`dashboard_menu` manager is recovered on the next request (FrankenPHP worker mode, no services resetter needed).
Standard hosts leave it null. Menu writes are the dashboard's and the importer's own (no host command handler writes
them), so there is no separate "host controls the transaction" mode.

### Known limitations

- `MenuTreeLoader::loadTree()` / `loadTreeView()` and `dashboard_menu_tree()` still accept a `mixed` permission context
  for the deprecated checkers; pass an array of plain values or a `MenuPermissionContext` (the value-object ports
  receive it as `MenuPermissionContext`, see [Permissions](#permissions)).
- `CurrentRouteTreeDecorator::decorate()` takes the HttpFoundation request: it is the bundle's own presentation service,
  not a port you implement; call `dashboard_menu_tree()` or use the matcher port instead.
- The JSON menu API path is not versioned yet (separate API versioning track).

## Item visibility rules

The bundle applies the following rules in order to decide which items appear in the rendered tree. All rules are enforced in `MenuTreeLoader` (see `buildTree` + `pruneEmptySections`).

### 1. Permission check (per item, all types)

Every item — regardless of type — is evaluated once by the configured permission checker (`MenuItemContextPermissionCheckerInterface::canViewItemFor($item, $context)`, or a deprecated variant). If the checker returns `false`, the item is excluded from the tree; none of its children are rendered either.

The checker receives the item (`MenuItemView`: `permissionKeys`, `unanimous`) and implements the OR / AND logic. The bundle does not enforce that logic itself; it is the checker's responsibility.

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
