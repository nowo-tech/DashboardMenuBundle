# Configuration

## Table of contents

- [Structure](#structure)
- [Options](#options)
  - [Root](#root)
  - [defaults](#defaults)
  - [defaults.classes](#defaultsclasses)
  - [defaults.icons](#defaultsicons)
  - [dashboard.modals](#dashboardmodals)
  - [menus.{code}](#menuscode)
  - [menu_code_resolver](#menu_code_resolver)
  - [api](#api)
- [Example](#example)
- [Symfony UX Icons](#symfony-ux-icons)
- [Collapsible menus](#collapsible-menus)

## Structure

- **project** (optional): Identifier to differentiate menus when multiple apps share the same DB (e.g. table scope).
- **locales** (optional): List of enabled locales for menu labels; request locale is validated against this list (see Root options).
- **default_locale** (optional): Fallback when the request locale is not in `locales`.
- **defaults**: Default values for all menus (each menu can override).
- **menus** (optional): Per-menu overrides in YAML. **Menus are defined in the database** (dashboard or fixtures): code, name, optional context (JSON key-value for variant resolution), icon, CSS classes. You do not need to list each menu here unless you want to override connection, permission_checker, cache, etc. for a given menu code.
- **api**: API route options.

## Options

### Root

| Option           | Default | Description |
|------------------|---------|-------------|
| `project`        | `null`  | Optional project identifier (e.g. when same DB holds several apps) |
| `locales`        | `[]`    | List of enabled locales for menu labels (e.g. `['es', 'en', 'fr']`). When empty, the request locale is used as-is. When set, the request locale is used only if in this list; otherwise `default_locale` or the first locale is used. |
| `default_locale`| `null`  | Fallback locale when the request locale is not in `locales`. If null, the first entry in `locales` is used. |

### defaults

| Option               | Default   | Description                          |
|----------------------|-----------|--------------------------------------|
| `connection`         | `default` | Doctrine connection name             |
| `table_prefix`       | `''`      | Table name prefix (e.g. `app_`)      |
| `permission_checker` | `null`    | Default permission checker service id |
| `cache_pool`         | `null`    | Cache pool for menu trees            |
| `cache_ttl`          | `300`     | Cache TTL in seconds                 |
| `classes`            | see below | CSS classes (menu, item, link, children) |
| `depth_limit`        | `null`    | Max depth to render (null = unlimited) |
| `icons`              | see below | Icon support (enabled, use_ux_icons, default) |
| `collapsible`        | `false`   | When true, the menu is wrapped in a collapsible block (toggle button + content). Requires Bootstrap collapse JS or similar. |
| `collapsible_expanded` | `true`   | When collapsible is true: open by default (true) or collapsed (false). |
| `nested_collapsible` | `false`   | When true, each item that has children gets a toggle; children are inside a Bootstrap collapse. The branch stays open when the current route is in that branch. |

### defaults.classes

| Key                    | Default          | Description                                                                 |
|------------------------|------------------|-----------------------------------------------------------------------------|
| `menu`                 | `dashboard-menu` | Class for the root `<ul>`                                                   |
| `item`                 | `''`             | Class for each `<li>`                                                       |
| `link`                 | `''`             | Class for each `<a>`                                                        |
| `children`             | `''`             | Class for nested `<ul>`                                                     |
| `class_current`        | `active`         | Class added to the `<a>` when its URL matches the current request path      |
| `class_branch_expanded`| `active-branch`  | Class added to the `<li>` when the current route is in that branch (e.g. to keep a collapsible open or style the parent) |

**Entity override:** The `Menu` entity has optional fields `classMenu`, `classItem`, `classLink`, `classChildren`, `classCurrent`, `classBranchExpanded`. When set in the database (e.g. via the dashboard or fixtures), they override the config classes for that menu. So you can define all CSS classes per menu in the admin instead of in YAML.

### defaults.icons

| Key            | Default | Description |
|----------------|---------|-------------|
| `enabled`      | `false` | Whether to show icons (uses `MenuItem::icon` or `default`) |
| `use_ux_icons` | `false` | When true, template calls `ux_icon()` (requires `symfony/ux-icons`) |
| `default`      | `null`  | Default icon name when item has no icon (e.g. `heroicons:home`) |

### dashboard.modals

When the dashboard is enabled, modal dialog sizes can be set per type (Bootstrap 5: `normal`, `lg`, `xl`):

| Key         | Default  | Description                    |
|------------|----------|--------------------------------|
| `menu_form`| `normal` | New menu and edit menu modals  |
| `copy`     | `normal` | Copy menu modal                |
| `item_form`| `lg`     | Add/edit menu item modal       |
| `delete`   | `normal` | Delete confirmation modals     |

Example: `modals: { menu_form: xl, item_form: xl }` to use extra-wide modals for forms.

### menus.{code}

Optional. Only add an entry here when you need to override defaults for a specific menu code (e.g. another connection, a permission checker). **Menu code, name, icon and CSS classes are taken from the `Menu` entity in the database** (created via the dashboard or fixtures). All options from **defaults** can be overridden per menu. Additionally:

| Option                 | Default | Description |
|------------------------|---------|-------------|
| `menu_name`            | `null`  | Optional display name (used as toggle label when collapsible) |
| `collapsible`          | (defaults) | Override: wrap this menu in a collapsible block |
| `collapsible_expanded` | (defaults) | Override: start open (true) or collapsed (false) |

### menu_code_resolver

| Option               | Default | Description |
|----------------------|---------|-------------|
| `menu_code_resolver` | `null`  | Service id of `MenuCodeResolverInterface`. Resolves the effective menu code from the request and hint (e.g. by operatorId, partnerId, menu name). When null, the hint is used as the menu code. |

### api

| Option        | Default      | Description        |
|---------------|--------------|--------------------|
| `enabled`     | `true`       | Enable JSON API    |
| `path_prefix` | `/api/menu`  | Path prefix for API route |

## Example

```yaml
nowo_dashboard_menu:
    project: my_app
    defaults:
        connection: default
        table_prefix: ''
        permission_checker: null
        cache_pool: null
        cache_ttl: 300
        classes:
            menu: dashboard-menu
            item: nav-item
            link: nav-link
            children: submenu
        depth_limit: null
        icons:
            enabled: true
            use_ux_icons: true
            default: heroicons:home
    menus:
        sidebar:
            menu_name: Sidebar
            classes:
                menu: sidebar-menu list-unstyled
            depth_limit: 3
        topbar:
            permission_checker: app.menu.topbar_checker
            icons:
                enabled: true
                use_ux_icons: true
    menu_code_resolver: null   # optional: service id of MenuCodeResolverInterface
    api:
        enabled: true
        path_prefix: /api/menu
```

## Symfony UX Icons

For SVG icons (e.g. Heroicons, Bootstrap Icons) install [Symfony UX Icons](https://symfony.com/bundles/ux-icons/current/index.html):

```bash
composer require symfony/ux-icons
```

Then set `icons.enabled: true` and `icons.use_ux_icons: true` for the menu, and set `icon` on each `MenuItem` (e.g. `heroicons:home`, `bootstrap:house`). When `use_ux_icons` is false, the template outputs a `<span data-icon="...">` so you can style or replace with your own icon library.

## Collapsible menus

Set `collapsible: true` (in `defaults` or per menu) to wrap the menu in a block with a toggle button and a collapsible content area. The template uses Bootstrap 5–compatible markup (`data-bs-toggle="collapse"`, `data-bs-target`, class `collapse` / `collapse show`). Ensure Bootstrap’s collapse JS is loaded (or equivalent).

The toggle button uses the menu’s `menu_name` (or the menu code) as label and includes a span with class `dashboard-menu-toggle-icon` for an optional chevron. You can style it with CSS, for example:

```css
.dashboard-menu-toggle-icon::after {
    content: "▾";
}
.dashboard-menu-toggle[aria-expanded="false"] .dashboard-menu-toggle-icon::after {
    content: "▸";
}
```
