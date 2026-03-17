# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-03-17

### Added

- **Symfony 6.4 LTS:** Bundle supports Symfony ^6.4 || ^7.0 || ^8.0 again; CI tests PHP 8.2–8.5 × Symfony 6.4, 7.0 and 8.0 (Symfony 8 job overrides platform to PHP 8.4).

### Changed

- **nowo-tech/icon-selector-bundle** is now **optional** (moved from `require` to `suggest`). The dashboard item form uses `IconSelectorType` when the bundle is installed (Symfony ^7.0 || ^8.0); otherwise the icon field is a plain text input. This allows using the bundle on Symfony 6.4 without that dependency.
- **Composer:** `config.platform.php` set to `8.2` so the lock file is resolvable on the minimum supported PHP; CI overrides to 8.4 for the Symfony 8.0 job.
- **Docs:** INSTALLATION notes that the bundle does not require icon-selector-bundle; README and requirements reflect Symfony 6.4 | 7 | 8.

### Fixed

- **CI:** Symfony 6.4 and 7.0 jobs no longer fail on dependency resolution (icon-selector-bundle was requiring ^7.0||^8.0 and blocking 6.4).

## [0.2.0] - 2026-03-18

### Changed (breaking)

- **PHP:** Minimum version is now **8.4** (8.2 and 8.3 no longer supported).
- **Symfony:** Bundle requires **Symfony ^8.0** only; Symfony 7 support has been dropped.
- **Doctrine:** `doctrine/doctrine-bundle` **^3.0** and `doctrine/orm` **^3.0** only (2.x no longer supported).

### Fixed

- **Doctrine config:** Removed deprecated `auto_generate_proxy_classes` from test and demo configs for compatibility with Doctrine Bundle 2.16+ (PHP 8.4+ native lazy objects).

### Changed

- **CI:** Test matrix is PHP 8.4–8.5 × Symfony 8.0; code-style and coverage jobs use PHP 8.4.
- **Docker:** Root Dockerfile uses `php:8.4-cli-alpine` for dev and tests.
- **Docs:** README, INSTALLATION, and UPGRADING updated for PHP 8.4+ and Symfony 8.

## [0.1.0] - 2026-03-17

### Added

- **Cache**: Configurable tree cache (`cache.ttl` min 60s, `cache.pool`) to avoid N+1 and repeated DB hits; cache key includes menu code, locale and context sets.
- **Icon prefix map**: `icon_library_prefix_map` (e.g. `bootstrap-icons: bi`) so icon identifiers are converted before rendering (e.g. for Symfony UX Icons); resolver accepts normalized config keys (hyphen/underscore).
- **Menu loading**: Two-query SQL path (menu + items) in `MenuRepository::findMenuAndItemsRaw()`; optional cache; legacy path when raw returns null.
- **Web Profiler**: "Dashboard menus" panel with two tabs: "Menus" (summary of menus on page, query count, items tree) and "Configuration" (connection, table prefix, cache, locales, icon map, permission checker services).
- **Doctrine**: Table name quoting uses `Platform::quoteSingleIdentifier()` (avoids deprecated `Connection::quoteIdentifier()`).
- **Demos**: Twig Inspector (`nowo-tech/twig-inspector-bundle`) in symfony7 and symfony8 demos for development.

## [0.0.1] - 2026-03-10

### Added

- **Entities:** `Menu` and `MenuItem` with Doctrine ORM (no Gedmo/Stof). Tree via parent + position; labels with optional JSON translations per locale (`translations` field).
- **Config:** `project`, `locales`, `default_locale`, `defaults` (connection, table_prefix, permission_checker, cache, classes, depth_limit, icons, collapsible options), `menus.{code}` overrides, `menu_code_resolver`, `api` (enabled, path_prefix). Dashboard: enabled, path_prefix, route_name_exclude_patterns, pagination, modals (Bootstrap 5 sizes).
- **MenuTreeLoader:** Single-query load; labels resolved by request locale from JSON translations; tree built in PHP; optional permission filter via `MenuPermissionCheckerInterface`.
- **Twig:** `dashboard_menu_tree(code, permissionContext?, contextSets?)`, `dashboard_menu_href(item)`, `dashboard_menu_config(code, contextSets?)`; include `@NowoDashboardMenuBundle/menu.html.twig` (Bootstrap 5–friendly, collapsible support).
- **JSON API:** `GET /api/menu/{code}` with optional `_locale` and `_context_sets` query params.
- **Dashboard:** CRUD at configurable path (default `/admin/menus`): list menus, create/edit/copy menu, manage items (add, edit, reorder, delete); route selector for item links; modal sizes configurable (normal/lg/xl).
- **Context resolution:** Menus with same `code` can have different JSON `context`; pass an ordered list of context sets to resolve the variant (e.g. partnerId/operatorId); empty context = fallback.
- **Base menu:** Option to mark a menu as "base" so its code cannot be changed after creation.
- **Translations:** Bundle messages for dashboard and forms in English, Spanish and French (`messages.en.yaml`, `messages.es.yaml`, `messages.fr.yaml`); validators in en, es, fr.
- **Demos:** Symfony 7 and Symfony 8 with FrankenPHP + Caddy (worker mode), MySQL; locales en (default), es, fr; fixtures for sidebar, aside, footer, dropdown, locale switcher and context-resolution examples.
- **CI:** GitHub Actions workflow (tests PHP 8.2–8.5 × Symfony 7.0/8.0, code style, coverage; release workflow on tag `v*`).
- **Recipe:** Symfony Flex recipe for config and routes.
- **Docs:** INSTALLATION, CONFIGURATION, USAGE, CONTRIBUTING, CHANGELOG, UPGRADING, RELEASE, SECURITY, ENGRAM, DEMO, DEVELOPMENT.

[Unreleased]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/nowo-tech/DashboardMenuBundle/releases/tag/v0.0.1
