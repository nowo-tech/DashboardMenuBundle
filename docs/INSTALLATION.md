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
- **Symfony** 6.4 (LTS), 7.x or 8.x (the bundle supports all via `^6.4 || ^7.0 || ^8.0`)
- **Doctrine ORM** ^2.13 || ^3.0 (no Gedmo/Stof or other ORM extensions required)

**Note:** Symfony **8.0** requires **PHP >= 8.4**. With PHP 8.2 or 8.3, Composer will resolve to Symfony **6.4** or **7.x**. With PHP 8.4+ you can use Symfony 6.4, 7 or 8.

The bundle does **not** require `nowo-tech/icon-selector-bundle`. The dashboard item form uses it when installed (Symfony ^7.0 || ^8.0); otherwise the icon field is a plain text input.

## Install with Composer

```bash
composer require nowo-tech/dashboard-menu-bundle
```

Use `^0.0` for the 0.x line (or the latest stable constraint).

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

## Upgrading

See [UPGRADING.md](UPGRADING.md) for version-to-version changes and breaking changes.
