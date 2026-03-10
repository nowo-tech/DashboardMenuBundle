# Changelog

## [Unreleased]

### Added

- Initial release: Menu and MenuItem entities with Gedmo Translatable and Tree (nested set).
- Config: connection, table_prefix, permission_checker, api.
- MenuTreeLoader: single-query load with TranslationWalker hint, tree build in PHP, optional permission filter.
- Twig: `dashboard_menu_tree()`, `dashboard_menu_href()`, `menu.html.twig` include.
- JSON API: `GET /api/menu/{code}`.
- Demos: symfony7 and symfony8 with FrankenPHP + MySQL.
