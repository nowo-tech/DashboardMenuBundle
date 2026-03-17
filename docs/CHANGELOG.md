# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Cache**: Configurable tree cache (`cache.ttl` min 60s, `cache.pool`) to avoid N+1 and repeated DB hits; cache key includes menu code, locale and context sets.
- **Icon prefix map**: `icon_library_prefix_map` (e.g. `bootstrap-icons: bi`) so icon identifiers are converted before rendering (e.g. for Symfony UX Icons); resolver accepts normalized config keys (hyphen/underscore).
- **Menu loading**: Two-query SQL path (menu + items) in `MenuRepository::findMenuAndItemsRaw()`; optional cache; legacy path when raw returns null.
- **Web Profiler**: “Dashboard menus” panel with two tabs: “Menus” (summary of menus on page, query count, items tree) and “Configuration” (connection, table prefix, cache, locales, icon map, permission checker services).
- **Doctrine**: Table name quoting uses `Platform::quoteSingleIdentifier()` (avoids deprecated `Connection::quoteIdentifier()`).

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

[Unreleased]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/nowo-tech/DashboardMenuBundle/releases/tag/v0.0.1
