# Configuration

## Table of contents

- [Structure](#structure)
- [Root options](#root-options)
  - [project](#project)
  - [doctrine](#doctrine)
  - [cache](#cache)
  - [icon_library_prefix_map](#icon_library_prefix_map)
  - [locales and default_locale](#locales-and-default_locale)
  - [permission_checker_choices](#permission_checker_choices)
  - [menu_link_resolver_choices](#menu_link_resolver_choices)
  - [api](#api)
  - [menu_collapse](#menu_collapse)
  - [web_ui](#web_ui)
  - [dashboard](#dashboard)
  - [css_class_options](#css_class_options)
- [Security (dashboard admin access)](#security-dashboard-admin-access)
- [Per-menu options (database)](#per-menu-options-database)
- [Example](#example)
- [Symfony UX Icons and icon prefix map](#symfony-ux-icons-and-icon-prefix-map)
- [Collapsible menus](#collapsible-menus)

## Structure

Menus are **defined in the database** (dashboard at `/admin/menus` or fixtures): code, name, optional context (JSON), icon, CSS classes, permission checker, depth limit, collapsible options. In YAML you configure only **global** options:

- **project** (optional): Identifier when multiple apps share the same DB.
- **doctrine**: DBAL connection name and table prefix for menu entities.
- **cache**: Tree cache (TTL and pool) to avoid N+1 and repeated DB hits.
- **icon_library_prefix_map**: Map full icon library names to short prefixes (e.g. `bootstrap-icons` → `bi`) for rendering.
- **locales** / **default_locale**: Enabled locales for menu item labels and fallback.
- **permission_checker_choices** (optional): List of service IDs (to order/filter) and/or map (service id → label) for the dashboard menu form dropdown; merged with tagged permission checkers.
- **api**: Enable JSON API and path prefix.
- **menu_collapse** (2.6.0, opt-in): nested collapse without Bootstrap JS (`js/menu-collapse.js`).
- **dashboard**: Enable admin CRUD, path prefix, route exclude patterns, pagination, modals, CSS class options, icon selector script, configurable step for re-indexing item positions.

## Root options

### project

| Option    | Default | Description |
|-----------|---------|-------------|
| `project` | `null`  | Optional project identifier (e.g. when the same DB holds several apps). |

### doctrine

| Option         | Default   | Description |
|----------------|-----------|-------------|
| `connection`   | `default` | Doctrine DBAL connection name for menu entities. |
| `table_prefix` | `''`      | Prefix for table names (`dashboard_menu`, `dashboard_menu_item`). Empty = no prefix. |

```yaml
nowo_dashboard_menu:
    doctrine:
        connection: default
        table_prefix: ''   # or e.g. 'app_'
```

**Generating a migration**

To create the menu tables with the configured **connection** and **table_prefix**, use the bundle command (recommended instead of `doctrine:schema:update` when you use a custom connection or prefix):

```bash
php bin/console nowo_dashboard_menu:generate-migration
```

By default this writes a migration file into the `migrations/` directory. Options:

- `--path=src/Migrations` — directory where to write the file
- `--namespace=App\Migrations` — PHP namespace for the migration class (default: `DoctrineMigrations`)
- `--dump` — only print the SQL without writing a file (useful if you do not use Doctrine Migrations)

If you use a non-default **connection**, run the migration with that connection:

```bash
php bin/console doctrine:migrations:migrate --conn=YOUR_CONNECTION
```

The generated migration uses `Doctrine\Migrations\AbstractMigration`; your project must have `doctrine/doctrine-migrations-bundle` (or `doctrine/migrations`) to run it. Otherwise use `--dump` and run the SQL manually.

### cache

Tree cache stores the raw menu + items result per (menuCode, locale, contextSets). Reduces DB queries and avoids N+1 when the same menu is rendered multiple times.

| Option | Default       | Description |
|--------|---------------|-------------|
| `ttl`  | `60`          | Time-to-live in seconds. Minimum `0` (values below 60 are allowed; `0` means immediate expiry when the item is saved, so entries are typically not reused on later requests). |
| `pool` | `cache.app`   | PSR-6 cache pool name. Set to `null` or empty to disable tree cache. |

```yaml
nowo_dashboard_menu:
    cache:
        ttl: 60
        pool: cache.app
```

### icon_library_prefix_map

Maps full icon library names to short prefixes so the template can pass the correct identifier to `ux_icon()` (e.g. Symfony UX Icons expects `bi:house` when using Bootstrap Icons). Keys are library names (the part before `:` in the icon string); values are the short prefix.

| Example key (YAML)      | Value | Effect on icon string                |
|-------------------------|-------|--------------------------------------|
| `bootstrap-icons`       | `bi`  | `bootstrap-icons:house` → `bi:house` |

Config keys may be normalized (e.g. `bootstrap-icons` becomes `bootstrap_icons`); the bundle accepts both when resolving.

```yaml
nowo_dashboard_menu:
    icon_library_prefix_map:
        bootstrap-icons: bi
```

### locales and default_locale

| Option           | Default | Description |
|------------------|---------|-------------|
| `locales`        | `[]`    | List of enabled locales for menu item labels (e.g. `['en', 'es', 'fr']`). When empty, the request locale is used as-is. When set, the request locale is used only if in this list; otherwise `default_locale` or the first locale is used. |
| `default_locale` | `null`  | Fallback when the request locale is not in `locales`. If null, the first entry in `locales` is used. |

`locales` can also be a parameter or env var holding the list, so the host keeps a single locale list:

```yaml
parameters:
    app.locales: ['en', 'es']

nowo_dashboard_menu:
    locales: '%app.locales%'            # or '%kernel.enabled_locales%' (framework.enabled_locales)
    # locales: '%env(json:APP_LOCALES)%' # APP_LOCALES='["en","es"]' (or '%env(csv:APP_LOCALES)%' with APP_LOCALES=en,es)
```

Parameters are resolved when the container is built (`nowo_dashboard_menu.locales` holds the resolved list). An env
var is read at runtime: the locale resolver, the item form locale tabs, the dashboard and the profiler panel receive the
list through a factory, and when `default_locale` is null the first env locale is the default (the
`nowo_dashboard_menu.default_locale_resolved` parameter keeps the `en` fallback). Values are trimmed and de-duplicated.

### permission_checker_choices

Services shown in the dashboard "Permission checker" dropdown when creating/editing a Menu. Any service whose class implements `MenuPermissionCheckerInterface` is **automatically** tagged and included (no need to add the tag in `services.yaml`). The dropdown label for auto-discovered checkers can be set with the class constant `DASHBOARD_LABEL` or the attribute `#[PermissionCheckerLabel('...')]`; if unset, the service id is used. This option adds or orders them and optionally overrides labels.

You can use either format:

- **List:** ordered service IDs (FQCN). Labels come from the service tag unless overridden in a map.
- **Map:** service id → display label. Use to override labels or to define order (map keys define order).

| Option                      | Default | Description |
|-----------------------------|---------|-------------|
| `permission_checker_choices` | `[]`    | List of service IDs, or map (service id => label). Merged with tagged services; list defines order; map overrides labels. |

**List format (order; labels from tags):**

```yaml
nowo_dashboard_menu:
    permission_checker_choices:
        - Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker   # Allow all (no filter)
        - App\Service\MyPermissionChecker                                  # By role and path
```

**Map format (custom labels and order):**

```yaml
nowo_dashboard_menu:
    permission_checker_choices:
        Nowo\DashboardMenuBundle\Service\AllowAllMenuPermissionChecker: 'Allow all'
        App\Service\MyPermissionChecker: 'By role and path'
```

The bundle also provides `PermissionKeyAwareMenuPermissionChecker` (structure example: items with a permission key are hidden unless you extend or replace with your own logic).

### menu_link_resolver_choices

Services shown in the menu item form "Link resolver" dropdown (item type **Service**, runtime URL). Any service implementing `MenuLinkResolverInterface` is **automatically** tagged `nowo_dashboard_menu.menu_link_resolver` and listed. The label is, in order: the tag attribute `label`, the class constant `DASHBOARD_LABEL`, the attribute `#[MenuLinkResolverLabel('...')]`, or the service id.

| Option | Default | Description |
|--------|---------|-------------|
| `menu_link_resolver_choices` | `[]` | Ordered list of resolver service ids. Listed (tagged) resolvers appear first in this order; the remaining tagged resolvers follow. Ids that are not tagged resolvers are ignored. With an empty list the dropdown is sorted by service id. Legacy `{order: [...]}` and map formats are normalized to this list (map keys are used). |

```yaml
nowo_dashboard_menu:
    menu_link_resolver_choices:
        - App\Menu\AccountLinksResolver
        - App\Menu\HelpCenterResolver
```

When no resolver is registered, the item form shows a free-text field for the service id.

### api

The JSON API route `nowo_dashboard_menu_api` is registered when you import `@NowoDashboardMenuBundle/Resources/config/routes.yaml` (route loader type `nowo_dashboard_menu_api`).

| Option        | Default      | Description        |
|---------------|--------------|--------------------|
| `enabled`     | `true`       | Register the JSON API route. When `false` the route is not registered (requests 404 and `path('nowo_dashboard_menu_api')` is unavailable). |
| `path_prefix` | `/api/menu`  | Path prefix for the API route: `GET {path_prefix}/{code}`. Leading/trailing slashes are normalized (`menus/` → `/menus/{code}`). |

```yaml
nowo_dashboard_menu:
    api:
        enabled: true
        path_prefix: /internal/menus   # GET /internal/menus/sidebar
```

### menu_collapse

Since **2.6.0**. Makes the menu's Bootstrap collapse markup (`data-bs-toggle="collapse"`, `.collapse` / `.collapse.show`) work without Bootstrap's JavaScript, through the bundle script `public/js/menu-collapse.js`. Opt-in: with the defaults the menu markup is unchanged. See [USAGE.md — Nested collapse without Bootstrap JS](USAGE.md#nested-collapse-without-bootstrap-js).

| Option        | Default                          | Description |
|---------------|----------------------------------|-------------|
| `enabled`     | `false`                          | `menu.html.twig` adds `data-dashboard-menu-collapse="<code>"` (+ `-mode`, `-storage-key`, `-branch-class`) to the menu root (the `.dashboard-menu-collapsible` wrapper when the menu is collapsible, else the root `<ul>`). Per include: `menuCollapse: true\|false`. |
| `mode`        | `auto`                           | `auto`: while Bootstrap Collapse JS is loaded (`window.bootstrap.Collapse` or the jQuery plugin) the script does not toggle (Bootstrap does — no double toggle); it still restores / remembers the state and syncs ARIA. `always`: the script owns the toggles (removes their `data-bs-toggle`, so Bootstrap ignores them). Per include: `menuCollapseMode`. |
| `storage_key` | `nowo.dashboardMenu.navCollapse` | `localStorage` key, value `{ "<menu code>": { "<panel id>": true\|false } }`. `""` disables persistence. Per include: `menuCollapseStorageKey`. |

```yaml
nowo_dashboard_menu:
    menu_collapse:
        enabled: true
        mode: auto            # auto | always
        storage_key: app.navCollapse
```

The Twig global `nowo_dashboard_menu_collapse` exposes the resolved values (`enabled`, `mode`, `storage_key`).

### web_ui

*Since 2.7 (REQ-UI-003).* The admin dashboard pages extend the UiKit scaffolds (list, show, form) and render inside
`nowo_ui_kit.layouts.admin`. CSS stack, icon set, row-action display and every class / attribute / partial come from
`nowo_ui_kit` (theme once in `nowo_ui_kit.theme`, per-bundle exception `nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle`).
Pages, blocks and view models: [TEMPLATES.md](TEMPLATES.md).

| Option | Default | Description |
| --- | --- | --- |
| `enabled` | `false` | Enable the dashboard routes (import `routes_dashboard.yaml`); when `false` they answer 404. Replaces `dashboard.enabled`. |
| `path_prefix` | `/admin/menus` | The prefix you import `routes_dashboard.yaml` with (informational; lock it in `security.access_control`). Replaces `dashboard.path_prefix`. |
| `layout_template` | `null` | Layout the scaffolds extend instead of `nowo_ui_kit.layouts.admin`. Must render the UiKit canonical blocks (`nowo_ui_content`…). A 1.x layout that renders `nowo_dashboard_menu_content` is wrapped in a deprecated bridge. Replaces `dashboard.layout_template`. |
| `index.intro` | `null` | *(2.10+)* Intro paragraph under the menus list title, rendered by the UiKit page header (`header.intro`, theme slot `page_intro`). Translation key (or text) of `index.intro_domain`; the bundle ships `dashboard.menus_intro` (31 locales). `null` / blank = no intro. |
| `index.intro_domain` | `NowoDashboardMenuBundle` | *(2.10+)* Translation domain of `index.intro` (e.g. `messages` for host copy). |
| `templates` | `{}` | Per-page override (page => template): `index`, `show`, `show_items_reorder`, `menu_form`, `item_form`, `copy_menu`, `import`, `menu_form_partial`, `item_form_partial`, `item_form_live`, `copy_menu_partial`, `import_partial`. Prefer a thin `{% extends '@!NowoDashboardMenuBundle/…' %}` overriding a data block. |

```yaml
nowo_ui_kit:
    layouts:
        admin: 'layouts/admin.html.twig'   # your layout or a one-file bridge rendering the canonical blocks
nowo_dashboard_menu:
    web_ui:
        enabled: true
        path_prefix: /admin/menus
        index:
            intro: dashboard.menus_intro      # or a host key + intro_domain: messages
```

### dashboard

Options for the admin dashboard (list, create, edit, copy menus and manage items).

| Option                        | Default        | Description |
|-------------------------------|----------------|-------------|
| `enabled`                     | *(deprecated)* | **Deprecated (2.7)** alias of `web_ui.enabled`. |
| `layout_template`             | *(deprecated)* | **Deprecated (2.7)** alias of `web_ui.layout_template`; the 1.x default `@NowoDashboardMenuBundle/dashboard/layout.html.twig` (removed) maps to `null` (UiKit layout). |
| `css_framework`               | `bootstrap5`   | **Deprecated (2.7)**: the pages follow `nowo_ui_kit.css_framework`. When set here (and not under `nowo_ui_kit`) it is seeded into UiKit; it also picks the FormKit `dashboard_menu` profile classes. Twig global `nowo_dashboard_menu_css_framework` kept. |
| `icon_set`                    | `bootstrap-icons` | **Deprecated (2.7)**: row-action icons follow `nowo_ui_kit.icon_set` (seeded from this value when set here only). |
| `path_prefix`                 | *(deprecated)* | **Deprecated** alias of `web_ui.path_prefix`. Set the dashboard URL prefix in your app routing when importing `routes_dashboard.yaml` (e.g. in `config/routes.yaml` or the recipe’s `config/routes_nowo_dashboard_menu.yaml`). |
| `route_name_exclude_patterns`  | `[]`           | Regex patterns to hide route names from the route selector (e.g. `['^_', '^web_profiler']`). |
| `pagination.enabled`         | `true`         | Paginate the menus list. |
| `pagination.per_page`        | `20`           | Menus per page. |
| `position_step`              | `100`          | Gap used by the dashboard “Re-index positions” action: within each sibling group (same parent), positions become `step`, `2*step`, `3*step`, … in current sort order. |
| `modals`                      | see below      | Dialog sizes (UiKit `dialog` partial): `menu_form` (menu + import dialogs), `copy`, `item_form`: `normal`, `lg`, `xl`. `delete` is unused since 2.7 (UiKit confirm dialog). |
| `ui`                          | see below      | **Deprecated (2.7)** 1.x admin chrome hooks. `classes.*` and `partials.pagination` are bridged into `nowo_ui_kit.theme.bundles.NowoDashboardMenuBundle`, `templates.*` mapped to `web_ui.templates`, `row_action_display` / `header_action_variants` / `form_themes` still applied, the rest has no effect. Mapping: [UPGRADING](UPGRADING.md#deprecated-configuration). |
| `css_class_options`           | (defaults)     | Arrays of CSS class choices shown as dropdowns when editing a menu. `section` / `divider` (and `span`) also provide rendering defaults. See [css_class_options](#css_class_options). |
| `icon_selector_script_url`   | `null`         | Optional URL of the icon-selector script (e.g. with `nowo-tech/icon-selector-bundle`). When set, the item form can show an icon selector. |
| `stimulus_script_url`        | *(see below)*  | URL of the Stimulus script for the Live Component form in the item modal (e.g. `bundles/nowodashboardmenu/js/stimulus-live.js`). The bundle needs `window.Stimulus` (or `$$stimulusApp$$` / `__stimulusApp__`) to connect controllers in the modal. Set to `null` only if your layout loads Stimulus and exposes it on `window` before the modal is opened. If you override the dashboard layout, include the script tag when this is set (same as the bundle layout). Dashboard autocomplete bootstrap in `dashboard.js` also relies on Stimulus availability to auto-register UX Autocomplete when needed. |
| `permission_key_choices`     | `[]`           | Optional list of permission keys (strings) for the menu item form. The field is rendered as a multiselect (`permissionKeys`) with autocomplete. Keys can be translated with `form.menu_item_type.permission_keys.choice.{key}` (use `path__` for `path:/`). |
| `id_options`                | `[]`           | Optional list of HTML id values for the root `<ul>` of each rendered menu. When non-empty, the menu form renders `ulId` as a dropdown (autocomplete-enabled); otherwise it becomes a plain text input. |
| `icon_size`               | `1em`          | CSS size used to render menu item icons (applied to SVG via width/height and to legacy icons via `font-size`). Example: `16px`, `1.2em`. |
| `autocomplete_bootstrap`   | `true`        | When `true`, `dashboard.js` registers Symfony UX Autocomplete from esm.sh and injects the Tom Select CDN stylesheet if the host Stimulus app has not registered the controller. Set `false` when the app ships UX Autocomplete and its own Tom Select styles (strict CSP). The script never re-registers a controller the host already registered (2.3.2). |
| `item_span_active`         | `false`       | When `true`, non-section items are wrapped in an extra `<span>` in `menu.html.twig` (those wrapper lines are otherwise omitted). The wrapper `<span>` class comes from the first non-empty choice in `dashboard.css_class_options.span`. |
| `import_max_bytes`           | `2097152` (2 MiB) | Maximum size in bytes for JSON import uploads. Reduces DoS risk from very large files. |
| `required_role`              | *(deprecated)* | **DEPRECATED** — use `security.access_roles`. Still accepted for BC and mapped to a one-element role list. |
| `import_export_rate_limit`   | disabled      | Rate limit for import and export actions. When set, use `limit` (requests per window) and `interval` (seconds). E.g. `{ limit: 10, interval: 60 }` = 10 requests per minute per user/IP. Set to `false` or omit to disable. |

**Modal defaults:** `menu_form: normal`, `copy: normal`, `item_form: lg`, `delete: normal`.

**`ui` defaults (deprecated):**

```yaml
ui:
    include_ui_kit_css: true
    header_actions_in_layout: false
    index_view: list            # list | table
    actions_column: first       # first | last
    row_action_display: null    # null | icon | text | icon_text
    region_attribute: null      # e.g. data-testid
    header_action_variants: {}  # action => primary|secondary|ghost|danger|link, e.g. { edit_menu: secondary }
                                # actions: export_all, import, new_menu, export, copy, edit_menu, edit_config,
                                # reindex_positions, check_parent_cycles, add_item
    form_themes: []
    classes: {}                 # button_primary, button_secondary, button_danger, button_link, button_ghost, button_small, page_header,
                                # toolbar, search_form, search_input, info_panel, inline_form, empty, empty_action, muted,
                                # badge, badge_base, badge_type, badge_unanimous, badge_non_unanimous, list, list_item,
                                # table_wrap, table, table_header_row, table_row, row_actions, row_action, row_action_danger,
                                # row_action_disabled, sortable_panel, sortable_header, sortable_body, modal_loading,
                                # modal_form, modal_content, modal_section, modal_message, modal_actions (null = UiKit default)
    partials:
        pagination: '@NowoDashboardMenuBundle/dashboard/_pagination.html.twig'
        row_action_inner: '@NowoDashboardMenuBundle/dashboard/_row_action_inner.html.twig'
        modal: '@NowoDashboardMenuBundle/dashboard/_modal.html.twig'
    templates:                  # index, show, show_items_reorder, menu_form_partial, item_form_partial,
                                # copy_menu_partial, import_partial, modals (bundle templates by default)
```

### css_class_options

`dashboard.css_class_options.<key>` is a list of class strings offered as dropdown choices for the matching **Menu** CSS field in the dashboard (an empty string `''` in the list offers "no class"). The value saved on the menu entity is what renders; when the field is empty the fallback below applies. Only `section`, `divider` and `span` read a rendering default from these lists; for every other key the list is just the set of dropdown choices.

| Key | Menu field | Applies to | When the menu field is empty |
|-----|------------|------------|------------------------------|
| `menu` | `classMenu` | Root `<ul>` | `dashboard-menu` |
| `item` | `classItem` | Every `<li>` | No class |
| `link` | `classLink` | Every `<a>` | No class |
| `children` | `classChildren` | Nested `<ul>` | No class |
| `section_children` | `classSectionChildren` | First-level `<ul>` under an item of type `section` (e.g. flat, no indent) | Same as `children` |
| `section_child_item` | `classSectionChildItem` | `<li>` of links and dividers that are direct children of a section | Same as `item` |
| `section_child_link` | `classSectionChildLink` | `<a>` of links that are direct children of a section | Same as `link` |
| `section_label` | `classSectionLabel` | `<span>` label of section items | `menu-section-label` |
| `section` | `classSection` | `<li>` of items of type `section` | **First non-empty entry of `css_class_options.section`** (default `menu-section`) |
| `divider` | `classDivider` | `<li>` of items of type `divider` | **First non-empty entry of `css_class_options.divider`** (default `menu-divider`) |
| `span` | — | Wrapper `<span>` of non-section labels when `item_span_active: true` | First non-empty entry of `css_class_options.span` |
| `current` | `classCurrent` | `<a>` of the current route | `active` |
| `branch_expanded` | `classBranchExpanded` | `<li>` whose branch contains the current route | `active-branch` |
| `has_children` | `classHasChildren` | `<li>` with children | No class |
| `expanded` / `collapsed` | `classExpanded` / `classCollapsed` | `<li>` whose children block is open / closed (collapsible menus) | No class |

```yaml
nowo_dashboard_menu:
    dashboard:
        css_class_options:
            section: [navigation-header, menu-section, '']
            divider: [dropdown-divider, menu-divider, '']
            section_children: ['nav flex-column list-unstyled ps-0', 'nav flex-column ms-2', '']
            section_child_item: ['nav-item ms-0', '']
            section_child_link: ['nav-link d-flex align-items-center ps-0', '']
            section_label: [navigation-header-text, '']
```

With the example above, menus that leave **Class section** / **Class divider** empty render sections with `navigation-header` and dividers with `dropdown-divider`.

## Security (dashboard admin access)

Canonical REQ-UI-002 keys under `nowo_dashboard_menu.security`:

| Option | Default | Description |
| --- | --- | --- |
| `access_roles` | `[ROLE_ADMIN]` | User must be granted **at least one** role for dashboard CRUD. Empty list **denies** access (fail-closed) unless `access_checker` is set; to rely on the firewall only, use e.g. `[IS_AUTHENTICATED_FULLY]`. |
| `access_checker` | `null` | Optional service id implementing `DashboardMenuAccessCheckerInterface`. |
| `allow_unauthenticated` | `false` | **DEV/DEMO only.** When `true`, dashboard may load without SecurityBundle / login. Production **MUST** keep `false`. |

```yaml
nowo_dashboard_menu:
    security:
        access_roles: [ROLE_ADMIN]
        # access_checker: App\Security\DashboardMenuAccessChecker
        allow_unauthenticated: false
    dashboard:
        enabled: true
        path_prefix: /admin/menus  # lock with host security.access_control
```

## Per-menu options (database)

Each **Menu** entity in the database can override:

- **Name**, **code**, **context** (JSON), **icon**
- **CSS classes**: classMenu, classItem, classLink, classChildren, classCurrent, classBranchExpanded, classHasChildren, classExpanded, classCollapsed
- **Permission checker**: service id of a `MenuPermissionCheckerInterface` implementation
- **Depth limit**: max depth to render (null = unlimited)
- **Collapsible**, **collapsible_expanded**, **nested_collapsible**
- **Root `<ul>` id:** `ulId` (rendered as the `id` attribute when set)

These are set when creating or editing the menu in the dashboard (or via fixtures). There is no per-menu YAML override; the bundle merges entity values with internal defaults when loading the tree.

## Example

```yaml
# config/packages/nowo_dashboard_menu.yaml
nowo_dashboard_menu:
    project: my_app
    doctrine:
        connection: default
        table_prefix: ''
    cache:
        ttl: 60
        pool: cache.app
    icon_library_prefix_map:
        bootstrap-icons: bi
    locales: ['es', 'en', 'fr']
    default_locale: 'en'
    api:
        enabled: true
        path_prefix: /api/menu
    web_ui:
        enabled: true
        path_prefix: /admin/menus  # same prefix as the routes_dashboard.yaml import
        # layout_template: null    # null = nowo_ui_kit.layouts.admin
    dashboard:
        # Prefix is set in config/routes.yaml when importing routes_dashboard.yaml (e.g. prefix: /admin/menus).
        route_name_exclude_patterns: ['^_', '^web_profiler']
        pagination:
            enabled: true
            per_page: 20
        modals:
            menu_form: normal
            copy: normal
            item_form: lg
            delete: normal
```

## Symfony UX Icons and icon prefix map

For SVG icons (e.g. Heroicons, Bootstrap Icons) install [Symfony UX Icons](https://symfony.com/bundles/ux-icons/current/index.html):

```bash
composer require symfony/ux-icons
```

Then set the **icon** on each MenuItem in the dashboard (e.g. `bootstrap-icons:house`). Configure `icon_library_prefix_map` so the bundle converts that to the short form expected by your setup (e.g. `bi:house`). The default map includes `bootstrap-icons: bi`. When `use_ux_icons` is false (or you don’t use UX Icons), the template can still output a `<span data-icon="...">` for custom styling.

## Collapsible menus

Set **collapsible** on the Menu entity (in the dashboard) to wrap the menu in a block with a toggle button and collapsible content. The template uses Bootstrap 5–compatible markup (`data-bs-toggle="collapse"`, `data-bs-target`, class `collapse` / `collapse show`). Load Bootstrap’s collapse JS, or (2.6.0) enable [`menu_collapse`](#menu_collapse) and include the bundle script `js/menu-collapse.js` instead.

- **collapsible_expanded**: open by default (true) or collapsed (false).
- **nested_collapsible**: when true, each item with children gets a toggle; children are inside a collapse. The branch stays open when the current route is in that branch.

The toggle button uses the menu’s **name** (or code) as label and a span with class `dashboard-menu-toggle-icon` for an optional chevron. Example CSS:

```css
.dashboard-menu-toggle-icon::after { content: "▾"; }
.dashboard-menu-toggle[aria-expanded="false"] .dashboard-menu-toggle-icon::after { content: "▸"; }
```
