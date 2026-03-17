# Upgrading

This document describes breaking changes and upgrade notes between versions.

## From 0.2.x to 0.3.0

- **Restored support:** PHP 8.2 and 8.3, and Symfony 6.4 and 7, are supported again. You can downgrade from PHP 8.4 to 8.2 or use Symfony 6.4/7 if needed.
- **Optional dependency:** `nowo-tech/icon-selector-bundle` is no longer required. If you had it installed, it continues to work (dashboard icon field uses the selector). If you remove it, the icon field becomes a plain text input. To install it optionally: `composer require nowo-tech/icon-selector-bundle` (requires Symfony ^7.0 || ^8.0).

## From 0.1.x to 0.3.0

- **New:** Symfony 6.4 (LTS) is supported; requirements are PHP >= 8.2 &lt; 8.6, Symfony ^6.4 || ^7.0 || ^8.0.
- **Optional:** For the icon selector widget in the dashboard item form you can install `nowo-tech/icon-selector-bundle` (suggested; requires Symfony ^7.0 || ^8.0). Without it, the icon field is a text input.

## From 0.0.1 to 0.1.0

No breaking changes. New features: configurable cache for menu tree, `icon_library_prefix_map`, two-query SQL path in `MenuRepository::findMenuAndItemsRaw()`, Web Profiler panel “Dashboard menus”, and Doctrine table quoting via `Platform::quoteSingleIdentifier()`. Optional: configure `cache.ttl` / `cache.pool` and `icon_library_prefix_map` in your config (see [CONFIGURATION](CONFIGURATION.md)).

## 0.0.1 (first release)

No upgrade path; this is the first stable release. Requirements: PHP >= 8.2 &lt; 8.6, Symfony ^6.4 || ^7.0 || ^8.0, Doctrine ORM ^2.13 || ^3.0. No Gedmo or Stof extensions are required.

Future breaking changes will be documented here (e.g. "From 0.x to 1.0").
