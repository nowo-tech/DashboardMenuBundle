# Nowo Dashboard Menu Bundle

[![CI](https://github.com/nowo-tech/DashboardMenuBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/DashboardMenuBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/dashboard-menu-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/dashboard-menu-bundle.svg)](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207%20%7C%208-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/dashboard-menu-bundle.svg?style=social&label=Star)](https://github.com/nowo-tech/DashboardMenuBundle)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/DashboardMenuBundle) so more developers can find it.

**Nowo Dashboard Menu Bundle** — Configurable dashboard menus with i18n (JSON translations), tree structure (parent + position), permissions, Twig rendering and JSON API. No external ORM extensions (Gedmo/Stof). For Symfony 6.4, 7 and 8 · PHP 8.2+.

## Table of contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Documentation](#documentation)
- [Requirements](#requirements)
- [Demo](#demo)
- [Development](#development)
- [License & author](#license--author)

## Features

- **Menu & MenuItem entities**: Tree (parent/children, ordered by position), labels with optional JSON translations per locale, optional icon per item (e.g. Symfony UX Icons)
- **Context resolution**: Same `code` can have multiple menus with different JSON context (e.g. `partnerId`, `operatorId`); pass an ordered list of context sets and the first match is used; empty context = fallback
- **Config**: Doctrine connection and table prefix; cache (TTL + pool) for the menu tree; `icon_library_prefix_map` (e.g. `bootstrap-icons` → `bi`); locales; per-menu options (classes, permission checker, depth limit, icons, collapsible) in the database
- **Permissions**: `MenuPermissionCheckerInterface` — implement and tag to filter items per user/context
- **Twig**: `dashboard_menu_tree(menuCode, permissionContext?, contextSets?)`, `dashboard_menu_href(item)`, `dashboard_menu_config(menuCode, contextSets?)`; include `@NowoDashboardMenuBundle/menu.html.twig`
- **JSON API**: `GET /api/menu/{code}` for SPA consumption (optional `_locale`, `_context_sets` query params)
- **Dashboard**: CRUD at `/admin/menus` (list, create, edit, copy menu, manage items)
- **Performance**: Two SQL queries per menu (menu + items), optional PSR-6 cache (configurable TTL); labels by locale from JSON; tree built in PHP
- **Dev**: Web Profiler panel “Dashboard menus” (menus on page, query count, configuration tab: connection, cache, locales, icon map, permission checkers)

## Installation

```bash
composer require nowo-tech/dashboard-menu-bundle
```

[![Install from Packagist](https://img.shields.io/badge/Packagist-install-777BB4?logo=composer)](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle)

With **Symfony Flex**, the recipe (if available) registers the bundle and adds config/routes. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md) for manual steps.

## Configuration

Menus are **defined in the database** (dashboard at `/admin/menus` or fixtures): code, name, context (optional JSON), CSS classes. In YAML you only need **defaults** (and optional **project**, **locales**). See [docs/CONFIGURATION.md](docs/CONFIGURATION.md).

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
    locales: ['es', 'en']
    api:
        enabled: true
        path_prefix: /api/menu
```

## Usage

**Twig:**

```twig
{% set tree = dashboard_menu_tree('sidebar') %}
{% set config = dashboard_menu_config('sidebar') %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar', menuConfig: config } %}
```

**With context sets** (resolve which menu variant to show):

```twig
{% set contextSets = [{ 'partnerId': 1, 'operatorId': 1 }, { 'partnerId': 1 }, {}] %}
{% set tree = dashboard_menu_tree('sidebar', null, contextSets) %}
```

**API:** `GET /api/menu/sidebar` returns JSON tree with `label`, `href`, `routeName`, `children`. Optional query: `_context_sets` (JSON array of context objects).

Full details: [docs/USAGE.md](docs/USAGE.md).

## Documentation

- [Demo with FrankenPHP (development and production)](docs/DEMO-FRANKENPHP.md)
- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)

### Additional documentation

- [Demo](docs/DEMO.md)
- [Development](docs/DEVELOPMENT.md)

## Requirements

- PHP >= 8.2, < 8.6
- Symfony >= 7.0 || >= 8.0
- Doctrine ORM (no Gedmo/Stof required)

See [docs/INSTALLATION.md](docs/INSTALLATION.md#requirements) and [docs/UPGRADING.md](docs/UPGRADING.md) for compatibility notes.

## Demo

Demos (Symfony 7 and 8; the bundle also supports Symfony 6.4) are in `demo/symfony7` and `demo/symfony8`. Each uses **FrankenPHP** with **Caddy** (worker mode) serving HTTP. Quick start: [docs/DEMO.md](docs/DEMO.md).

**FrankenPHP worker:** The demos are configured to run with FrankenPHP in runtime worker mode (Caddyfile `:80`, `php_server { worker /app/public/index.php 2 }`). The bundle is compatible with and tested in this setup.

From bundle root:

```bash
make -C demo/symfony8 up
make -C demo/symfony8 install
# Open http://localhost:8011 (or port from demo .env)
```

## Development

Run tests and QA with Docker: `make up && make install && make test` (or `make test-coverage`, `make qa`). Without Docker: `composer install && composer test`. Full details: [docs/DEVELOPMENT.md](docs/DEVELOPMENT.md).

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Author

Created by [Héctor Franco Aceituno](https://github.com/HecFranco) at [Nowo.tech](https://nowo.tech)
