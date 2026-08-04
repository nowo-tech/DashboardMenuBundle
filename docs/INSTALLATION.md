# Installation

This guide covers installing Dashboard Menu Bundle in a Symfony application.


## Table of contents

- [Requirements](#requirements)
- [Install with Composer](#install-with-composer)
- [Register the bundle](#register-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Without Flex (manual)](#without-flex-manual)
- [Import routes](#import-routes)
- [Schema](#schema)
- [Verify](#verify)
- [Upgrading](#upgrading)

## Requirements

- **PHP** >= 8.2, < 8.6
- **Symfony** 7.4+ or 8.x (`^7.4 || ^8.0`)
- **Doctrine ORM** ^2.13 || ^3.0 (no Gedmo/Stof or other ORM extensions required)
- **UiKitBundle** (`nowo-tech/ui-kit-bundle` `^1.4`) — required for dashboard Twig macros and `nowo-ui.css` (REQ-UI-001-kit)
- **FormKitBundle** (`nowo-tech/form-kit-bundle` `^2.0`) — required for dashboard Symfony form field options / profile `dashboard_menu`
- **Symfony UX:** `symfony/ux-autocomplete` and `symfony/ux-live-component` are required by Composer (dashboard autocomplete and the optional Live Component item form). The bundle supports **UX 2.x** (from 2.32 / 2.33) and **UX 3.x** on those packages.

**Note:** Symfony **8.0** requires **PHP >= 8.4**. With PHP 8.2 or 8.3, Composer will resolve to Symfony **7.4+**. With PHP 8.4+ you can use Symfony 7.4+ or 8.

The bundle does **not** require `nowo-tech/icon-selector-bundle`. The dashboard item form uses it when installed (Symfony ^7.0 || ^8.0); otherwise the icon field is a plain text input.

## Install with Composer

```bash
composer require nowo-tech/dashboard-menu-bundle
```

Use `^2.0` for the stable 2.x line (or `^1.0` only if you must stay on the pre-2.0 series).

## Register the bundle

### With Symfony Flex

If you use [Symfony Flex](https://symfony.com/doc/current/setup/flex.html) and the bundle is installed from Packagist (or your recipe repository), the recipe will:

- Register the bundle in `config/bundles.php`
- Create `config/packages/nowo_dashboard_menu.yaml` (default config)

You do **not** need to edit any file manually. Then continue with [Import routes](#import-routes) and [Schema](#schema) (routes and schema may be added by the recipe if included).

### Without Flex (manual)

1. **Register the bundle** in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nowo\DashboardMenuBundle\NowoDashboardMenuBundle::class => ['all' => true],
    Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
    Nowo\FormKitBundle\NowoFormKitBundle::class => ['all' => true],
];
```

2. **Create config**: Add `config/packages/nowo_dashboard_menu.yaml` with at least `doctrine`, `api` (and optionally `project`, `locales`, `cache`, `icon_library_prefix_map`, `dashboard`). See [CONFIGURATION.md](CONFIGURATION.md) for a minimal example.

## Import routes

Import the bundle routes so the dashboard and API are available. In `config/routes.yaml`:

```yaml
nowo_dashboard_menu:
    resource: '@NowoDashboardMenuBundle/Resources/config/routes.yaml'
```

If you use the **dashboard** (admin UI), import the dashboard routes and set the URL prefix there. The Flex recipe adds `config/routes_nowo_dashboard_menu.yaml` (prefix `/admin/menus`); add to `config/routes.yaml`:

```yaml
_nowo_dashboard_menu_dashboard:
    resource: routes_nowo_dashboard_menu.yaml
```

Without Flex, add the same import manually and create a file that imports `@NowoDashboardMenuBundle/Resources/config/routes_dashboard.yaml` with `prefix: /admin/menus` (or your chosen path). The dashboard URL prefix is **only** configured in routing, not in `nowo_dashboard_menu` config.

## Schema

Create the menu tables using the **connection** and **table_prefix** from your config:

```bash
php bin/console nowo_dashboard_menu:generate-migration
php bin/console doctrine:migrations:migrate
```

If you use a non-default `doctrine.connection`, run the migration with that connection:

```bash
php bin/console doctrine:migrations:migrate --conn=YOUR_CONNECTION
```

Alternatively you can use `doctrine:schema:update --force` if you use the default connection and no table prefix; the bundle command is recommended so the migration matches your YAML config.

**Existing database (column errors such as `class_section` / `class_divider`):** If `dashboard_menu` was created with an older migration and Doctrine reports unknown columns when loading menus, generate ALTER statements from the current entity mapping and run migrations:

```bash
php bin/console nowo_dashboard_menu:generate-migration --update
php bin/console doctrine:migrations:migrate
```

Use `--dump` first to print SQL without writing a file. If you use a non-default connection, pass `--conn=` to `doctrine:migrations:migrate` as above.

## Verify

- Open the dashboard at the configured path (default `/admin/menus`) to create menus and items.
- In a Twig template, use `dashboard_menu_tree('sidebar')` and include `@NowoDashboardMenuBundle/menu.html.twig` (see [USAGE.md](USAGE.md)).
- Call `GET /api/menu/sidebar` to get the JSON tree.

## Using css_framework: custom (Bootstrap-free hosts)

Set `dashboard.css_framework: custom` in your bundle config when your host layout does **not** load Bootstrap. UiKit macros and CSS supply the standalone modal overlay and UI tokens; this bundle aligns `nowo_ui_kit` from the dashboard keys unless you set `nowo_ui_kit` yourself:

```yaml
# config/packages/nowo_dashboard_menu.yaml
nowo_dashboard_menu:
    dashboard:
        css_framework: custom
# optional explicit kit config (wins over dashboard alignment):
# nowo_ui_kit:
#     css_framework: custom
#     icon_set: svg_inline
```

After setting this option:

1. **Do not** load Bootstrap CSS or JS in your layout. Use UiKit `nowo-ui.css` for tokens and the custom modal overlay.
2. Run `php bin/console assets:install` (or `assets:install --symlink`) so `public/bundles/nowouikit/css/nowo-ui.css` and `public/bundles/nowodashboardmenu/js/dashboard.js` are published.
3. Include the assets in your layout shell (prefer named packages):

```twig
<link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
<script src="{{ asset('js/dashboard.js', 'nowo_dashboard_menu') }}" defer></script>
```

4. **Remap design tokens** under your host shell class without forking templates:

```css
.kit-admin {
    --nowo-ui-primary: #7c3aed;
    --nowo-ui-surface: #ffffff;
    --nowo-ui-border: #ddd4fe;
    /* … any --nowo-ui-* token from UiKit nowo-ui.css */
}
```

5. **Modals** for non-Bootstrap stacks are handled by dashboard JS (`dashboard.js`). Opener buttons use `data-nowo-modal-open` (boolean) + `data-nowo-modal-target="<id>"` (from kit macros when `css_framework` is `custom` / `none` / `tailwind` / …). No Bootstrap JS is required; the script dispatches a synthetic `show.bs.modal` Event so existing form-loading listeners work transparently.

> **Note:** The default `css_framework` remains `bootstrap5` for all demos and new installs. Set `custom` only when your host explicitly omits Bootstrap.

## Upgrading

See [UPGRADING.md](UPGRADING.md) for version-to-version changes and breaking changes.
