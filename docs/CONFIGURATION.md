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
  - [api](#api)
  - [dashboard](#dashboard)
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
- **dashboard**: Enable admin CRUD, path prefix, route exclude patterns, pagination, modals, CSS class options, icon selector script.

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
| `ttl`  | `60`          | Time-to-live in seconds. Minimum 60. |
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

### api
| Option        | Default      | Description        |
|---------------|--------------|--------------------|
| `enabled`     | `true`       | Enable JSON API.   |
| `path_prefix` | `/api/menu`  | Path prefix for the API route (`GET {path_prefix}/{code}`). |

### dashboard

Options for the admin dashboard (list, create, edit, copy menus and manage items).

| Option                        | Default        | Description |
|-------------------------------|----------------|-------------|
| `enabled`                     | `false`        | Enable dashboard routes. Set to `true` in app config to use the admin UI. |
| `layout_template`             | `@NowoDashboardMenuBundle/dashboard/layout.html.twig` | Twig template that dashboard views extend. Must define a `content` block. Override to use your app base layout (e.g. `base.html.twig`) so the dashboard matches your app shell. |
| `path_prefix`                 | *(deprecated)* | **Deprecated.** Set the dashboard URL prefix in your app routing when importing `routes_dashboard.yaml` (e.g. in `config/routes.yaml` or the recipe’s `config/routes_nowo_dashboard_menu.yaml`). |
| `route_name_exclude_patterns`  | `[]`           | Regex patterns to hide route names from the route selector (e.g. `['^_', '^web_profiler']`). |
| `pagination.enabled`         | `true`         | Paginate the menus list. |
| `pagination.per_page`        | `20`           | Menus per page. |
| `modals`                      | see below      | Modal sizes: `menu_form`, `copy`, `item_form`, `delete` (Bootstrap 5: `normal`, `lg`, `xl`). |
| `css_class_options`           | (defaults)     | Arrays of CSS class choices shown as dropdowns when editing a menu (menu, item, link, children, current, branch_expanded, has_children, expanded, collapsed). |
| `icon_selector_script_url`   | `null`         | Optional URL of the icon-selector script (e.g. with `nowo-tech/icon-selector-bundle`). When set, the item form can show an icon selector. |

**Modal defaults:** `menu_form: normal`, `copy: normal`, `item_form: lg`, `delete: normal`.

## Per-menu options (database)

Each **Menu** entity in the database can override:

- **Name**, **code**, **context** (JSON), **icon**
- **CSS classes**: classMenu, classItem, classLink, classChildren, classCurrent, classBranchExpanded, classHasChildren, classExpanded, classCollapsed
- **Permission checker**: service id of a `MenuPermissionCheckerInterface` implementation
- **Depth limit**: max depth to render (null = unlimited)
- **Collapsible**, **collapsible_expanded**, **nested_collapsible**

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
    dashboard:
        enabled: true
        layout_template: '@NowoDashboardMenuBundle/dashboard/layout.html.twig'  # or e.g. base.html.twig
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

Set **collapsible** on the Menu entity (in the dashboard) to wrap the menu in a block with a toggle button and collapsible content. The template uses Bootstrap 5–compatible markup (`data-bs-toggle="collapse"`, `data-bs-target`, class `collapse` / `collapse show`). Ensure Bootstrap’s collapse JS is loaded.

- **collapsible_expanded**: open by default (true) or collapsed (false).
- **nested_collapsible**: when true, each item with children gets a toggle; children are inside a collapse. The branch stays open when the current route is in that branch.

The toggle button uses the menu’s **name** (or code) as label and a span with class `dashboard-menu-toggle-icon` for an optional chevron. Example CSS:

```css
.dashboard-menu-toggle-icon::after { content: "▾"; }
.dashboard-menu-toggle[aria-expanded="false"] .dashboard-menu-toggle-icon::after { content: "▸"; }
```
