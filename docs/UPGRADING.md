# Upgrading

This document describes breaking changes and upgrade notes between versions.

## From 0.0.1 to 0.1.0

No breaking changes. New features: configurable cache for menu tree, `icon_library_prefix_map`, two-query SQL path in `MenuRepository::findMenuAndItemsRaw()`, Web Profiler panel “Dashboard menus”, and Doctrine table quoting via `Platform::quoteSingleIdentifier()`. Optional: configure `cache.ttl` / `cache.pool` and `icon_library_prefix_map` in your config (see [CONFIGURATION](CONFIGURATION.md)).

## 0.0.1 (first release)

No upgrade path; this is the first stable release. Requirements: PHP >= 8.2 &lt; 8.6, Symfony ^7.0 || ^8.0, Doctrine ORM. No Gedmo or Stof extensions are required.

Future breaking changes will be documented here (e.g. "From 0.x to 1.0").
