# Upgrading

This document describes breaking changes and upgrade notes between versions.

## From 0.3.4 to 0.3.5

- **Translation domain:** The bundle now uses the domain **NowoDashboardMenuBundle** (no longer `messages` or `validators`). Bundle files are `NowoDashboardMenuBundle.{locale}.yaml`. If you override bundle strings in your app, rename or create `translations/NowoDashboardMenuBundle.{locale}.yaml` (e.g. `NowoDashboardMenuBundle.en.yaml`) and use the same key structure (`dashboard.*`, `form.*`). Remove any app overrides under `messages.*` or `validators.*` that were only for this bundle.
- **Templates and translations override:** The extension no longer prepends paths; your app’s `templates/bundles/NowoDashboardMenuBundle/` and translation files take precedence by default. No config change needed unless you relied on the previous prepend order.
- No other breaking changes.

## From 0.3.5 to 0.3.6

No breaking changes. The bundle registers the Twig namespace `@NowoDashboardMenuBundle` in a way that keeps the standard override behaviour: templates in your app under `templates/bundles/NowoDashboardMenuBundle/` take precedence.

## From 0.3.9 to 0.3.10

No breaking changes. Unit tests were updated after replacing `RegisterTwigNamespacePass` with `TwigPathsPass`.

## From 0.3.8 to 0.3.9

No breaking changes. The bundle now registers its Twig views via the compiler pass `TwigPathsPass` (replacing `RegisterTwigNamespacePass`). Application overrides in `templates/bundles/NowoDashboardMenuBundle/` continue to take precedence.

## From 0.3.7 to 0.3.8

No breaking changes. Dashboard export links (export one menu and “Export all”) now open in a new browser tab.

## From 0.3.6 to 0.3.7

No breaking changes. New optional security options for sensitive environments:

- **`dashboard.required_role`** — When set (e.g. `ROLE_ADMIN`), all dashboard routes require this role. Requires Symfony SecurityBundle. Leave unset to keep using your app’s `access_control` or firewall.
- **`dashboard.import_export_rate_limit`** — Optional rate limit for import and export (e.g. `{ limit: 10, interval: 60 }`). When exceeded, the app returns HTTP 429. Disabled by default.
- **`dashboard.import_max_bytes`** — Max size in bytes for JSON import uploads (default 2 MiB). Existing form validation remains; this adds a controller-level check.

Dashboard delete and move-up/move-down actions now validate CSRF and use POST for move actions; no change needed if you use the bundle’s dashboard templates.

## From 0.3.3 to 0.3.4

No breaking changes. New: dashboard **export/import** (JSON), config **`dashboard.layout_template`** to choose the Twig layout dashboard views extend (default unchanged), and **MenuUrlResolver** now fills missing route path params from the current request and adds a flash message on URL generation failure. Dashboard content block is now `content` (was `dashboard_body`); if you override the bundle’s dashboard layout template, ensure it defines `{% block content %}`.

## From 0.3.2 to 0.3.3

No breaking changes. Permission checkers are now **auto-tagged**: any service implementing `MenuPermissionCheckerInterface` is included in the dashboard dropdown without adding the tag in `services.yaml`. Optionally set the label via the class constant `DASHBOARD_LABEL` or the attribute `#[PermissionCheckerLabel('...')]`. You can remove the manual tag from your checker service and, if you use service discovery (e.g. `App\Service\`: `resource: '../src/Service/'`), you do not need to register the checker explicitly.

## From 0.3.1 to 0.3.2

No breaking changes. `permission_checker_choices` now accepts a **list** of service IDs (to order/filter the dropdown) as well as the existing map (service id => label). The bundle includes a new checker: `PermissionKeyAwareMenuPermissionChecker` (structure example). Demos use the list format and are aligned.

## From 0.3.0 to 0.3.1

No breaking changes. New optional config: `permission_checker_choices` (service id => label map) to customize the “Permission checker” dropdown in the dashboard menu form. Demos updated (Symfony 7 has `DemoMenuPermissionChecker`; both demos show permission keys `path:/`, `authenticated`, `admin` in fixtures).

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
