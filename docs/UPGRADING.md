# Upgrading

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.

## From 0.3.26 to 0.3.27

No breaking changes.

- **Profiler configuration diagnostics:** the Dashboard menus collector now includes a richer "Configuration" panel with an explicit effective snapshot and raw merged config JSON.
- **Profiler permission diagnostics:** permission checks table supports sorting and combines selected/resolved checker details in a single column for faster troubleshooting.
- **Checker resolution internals:** checker services are resolved through the tagged locator (`nowo_dashboard_menu.permission_checker`) rather than the full service container.
- **Demos:** Symfony 7/8 fixtures now use both demo checkers in different menus to better demonstrate behaviour.

## From 0.3.25 to 0.3.26

No breaking changes.

- **Import replace behaviour:** replacing an existing menu now removes existing items using repository preloading before re-import. This prevents duplicate links/items when importing the same menu JSON repeatedly.
- **Export payload:** `permissionChecker` (menu) and `permissionKey` (item) are always present in exported JSON, including when their values are `null`.
- **Demo permission expressions:** demo checkers (Symfony 7/8) now support OR/AND with parentheses in `permissionKey` expressions (e.g. `authenticated|admin`, `(path:/admin|path:/operator)&authenticated`).
- **Demo fixtures/docs:** demos include expression examples in fixtures; USAGE documents syntax, precedence and examples.

## From 0.3.24 to 0.3.25

No breaking changes.

- **Dashboard import modal:** import submit now handles redirects reliably after successful `POST` + `302` responses, so the modal no longer appears stuck.
- **Dashboard item list:** rows are rendered in deterministic tree order using item `position` (sibling fallback by `id`), and the table displays each item position under its parent label.
- **Performance:** reduced N+1 risk in dashboard copy/export/edit flows:
  - copy uses a flat preloaded item list (two-pass clone),
  - descendant lookup for item edit uses preloaded items + BFS,
  - import clears link fields with an in-memory has-children map,
  - export-all loads all items in one query grouped by menu in memory.

## From 0.3.22 to 0.3.23

No breaking changes.

- **LiveComponent:** Fix item modal submit crash by avoiding modifications of submitted Form data; per-locale `label_{locale}` fields hydrate reliably.
- **Dashboard UI:** item modal icon field uses a plain text input so the stored icon string is always prefilled during LiveComponent editing.
- **Demos:** Asset build commands run inside the demo Docker container (`make assets` / `make ts-assets`) for consistent `pnpm` behaviour.

## From 0.3.23 to 0.3.24

No breaking changes.

- **Dashboard UI:** menu item labels are rendered using the locale-resolved `MenuItem::getLabelForLocale()` value.
- **Dashboard item forms:** “Add child” modal shows only `label` + per-locale translations (type fixed to Link) and hides icon/position fields.
- **Dashboard item forms:** icon identity editing uses a normal Symfony form (not LiveComponent) to ensure the icon-selector widget refreshes correctly.
- **Dashboard item forms:** label validation accepts either a non-empty base label or at least one non-empty translation; empty `position` values are normalized to `0`.

## From 0.3.21 to 0.3.22

No breaking changes.

- **Config:** new optional `dashboard.icon_size` to control the CSS size of rendered menu item icons (SVG width/height and legacy icon `font-size`).
- **Templates/overrides:** `item.label` is already resolved for the current locale by `MenuTreeLoader`; if you override `menu.html.twig`, prefer rendering `item.label` as-is (avoid an extra `|trans(...)`).

## From 0.3.20 to 0.3.21

No breaking changes.

- **Config/UI:** new `dashboard.item_span_active` option to optionally render an extra wrapper `<span>` around non-section items in `menu.html.twig`.
- **Config:** wrapper class is taken from the first non-empty value in `dashboard.css_class_options.span` (configure it in your `nowo_dashboard_menu.yaml`).

## From 0.3.18 to 0.3.19

No breaking changes.

## From 0.3.19 to 0.3.20

No breaking changes.

- **Config:** new `dashboard.id_options` to drive the dashboard menu form field `ulId` (dropdown vs plain text).
- **Menu entity:** added nullable `Menu.ulId` mapped to DB column `ul_id`. When set, the rendered menu root `<ul>` gets `id="..."`.
- **Database migration:** existing installations must add the nullable `ul_id` column. Use:
  - `php bin/console nowo_dashboard_menu:generate-migration --update`
  - then run the generated Doctrine migration (or apply the SQL manually).

## From 0.3.17 to 0.3.18

No breaking changes.

- **Dashboard UI spacing:** default CSS class options were updated (`gap-2` → `gap-1`) to improve spacing consistency in dashboard templates/components. If you depend on exact default class strings, re-check your expectations.

## From 0.3.16 to 0.3.17

No breaking changes.

- **CSRF:** dashboard item forms explicitly set `csrf_token_id` to `submit` to keep CSRF consistent across Symfony versions (controller + LiveComponent).
- **Dashboard templates:** autocomplete form theme is applied only when `Symfony\UX\Autocomplete` is available; overrides of `_item_form_partial.html.twig`, `item_form.html.twig` or `components/ItemFormLiveComponent.html.twig` should keep the same conditional if you rely on autocomplete.
- **Demo Symfony 7:** sessions + CSRF and `framework.property_info` are enabled to avoid config option errors on older Symfony versions.

## From 0.3.15 to 0.3.16

No breaking changes.

- **Config:** Optional `dashboard.permission_key_choices` (array of strings) to turn the item form permission key field into a select with autocomplete. Demos set it to match fixture keys (e.g. `['authenticated', 'admin', 'path:/']`).
- **Dashboard:** Route name and (when configured) permission key use the Symfony UX Autocomplete form theme; override templates can keep or replace the `form_theme` line.
- **Add child:** Parent ID is sent in the form action URL so the new item is saved as a child; the "Add child" button is disabled for section and divider items.
- **MenuItem:** `itemType` is now nullable (DB and entity); getter/setter handle null. No change needed unless you type-hint or reflect on the property.
- **Permission checkers:** Bundle and demo checkers are explicitly tagged so they appear in the dropdown; if you add a custom checker, tag it with `nowo_dashboard_menu.permission_checker` and optional `label`.
- **Docs:** Override and form-theme behaviour clarified in USAGE; `permission_key_choices` documented in CONFIGURATION.
- **Demo Symfony 7:** Frontend is TypeScript (see demo README: `make ts-assets` after fixing `assets/` ownership if needed).

## From 0.3.14 to 0.3.15

No breaking changes.

- **Dashboard:** Menu and item forms are split into **definition** (pencil) and **configuration** (gear). New menu / new item show only definition; after creation you can edit configuration via the gear button. Edit definition and edit configuration open separate modals with only the relevant fields.
- **Redirect:** After any successful dashboard action (create/update/delete menu or item, copy, import, move), the app redirects to the request **Referer** when it is same-origin; otherwise to the usual route.
- **Import:** The import form can be opened in a modal (AJAX) from the dashboard index; submission redirects to referer on success.
- **MenuItem:** The `label` property is now nullable (for divider items). If you extend or reflect on the entity, update type hints; the getter still returns a string (empty string when null).
- **Twig overrides:** If you override `_menu_form_partial.html.twig` or `_item_form_partial.html.twig`, ensure you handle `form.definition` / `form.config` (menu) and `form.basic` / `form.config` (item), and the `section_focus` variable for section-specific rendering.
- **Dockerfile:** The bundle Dockerfile now installs Node.js, npm and pnpm for building dashboard assets.

## From 0.3.13 to 0.3.14

No breaking changes.

## From 0.3.12 to 0.3.13

No breaking changes.

- New menu option **`nestedCollapsibleSections`**: when disabled, section-type items do not collapse their children (even if nested collapsible is on). Configurable in the menu form and in import JSON.
- Export/import includes `classSectionLabel` and `nestedCollapsibleSections`.
- New required dependency: `symfony/mime` (for the import form file validator).
- `ImportMenuType` constraints updated for Symfony 7/8 (named arguments).
- Sample JSON and UPGRADING doc order updated.

## From 0.3.11 to 0.3.12

No breaking changes.

- Demos now rely on Doctrine Migrations (instead of schema update) and ship a single "create tables" migration matching the current schema.
- The migration generator command supports `--update` to create ALTER migrations for existing installations.

## From 0.3.10 to 0.3.11

No breaking changes.

- The dashboard menu list shows the number of items per menu.
- The dashboard menu items table is more responsive (actions moved left, up/down stacked).
- New required dependency: `symfony/ux-autocomplete`.

## From 0.3.9 to 0.3.10

No breaking changes. Unit tests were updated after replacing `RegisterTwigNamespacePass` with `TwigPathsPass`.

## From 0.3.8 to 0.3.9

No breaking changes. The bundle now registers its Twig views via the compiler pass `TwigPathsPass` (replacing `RegisterTwigNamespacePass`). Application overrides in `templates/bundles/NowoDashboardMenuBundle/` continue to take precedence.

## From 0.3.7 to 0.3.8

No breaking changes. Dashboard export links (export one menu and "Export all") now open in a new browser tab.

## From 0.3.6 to 0.3.7

No breaking changes. New optional security options for sensitive environments:

- **`dashboard.required_role`** — When set (e.g. `ROLE_ADMIN`), all dashboard routes require this role. Requires Symfony SecurityBundle. Leave unset to keep using your app's `access_control` or firewall.
- **`dashboard.import_export_rate_limit`** — Optional rate limit for import and export (e.g. `{ limit: 10, interval: 60 }`). When exceeded, the app returns HTTP 429. Disabled by default.
- **`dashboard.import_max_bytes`** — Max size in bytes for JSON import uploads (default 2 MiB). Existing form validation remains; this adds a controller-level check.

Dashboard delete and move-up/move-down actions now validate CSRF and use POST for move actions; no change needed if you use the bundle's dashboard templates.

## From 0.3.5 to 0.3.6

No breaking changes. The bundle registers the Twig namespace `@NowoDashboardMenuBundle` in a way that keeps the standard override behaviour: templates in your app under `templates/bundles/NowoDashboardMenuBundle/` take precedence.

## From 0.3.4 to 0.3.5

- **Translation domain:** The bundle now uses the domain **NowoDashboardMenuBundle** (no longer `messages` or `validators`). Bundle files are `NowoDashboardMenuBundle.{locale}.yaml`. If you override bundle strings in your app, rename or create `translations/NowoDashboardMenuBundle.{locale}.yaml` (e.g. `NowoDashboardMenuBundle.en.yaml`) and use the same key structure (`dashboard.*`, `form.*`). Remove any app overrides under `messages.*` or `validators.*` that were only for this bundle.
- **Templates and translations override:** The extension no longer prepends paths; your app's `templates/bundles/NowoDashboardMenuBundle/` and translation files take precedence by default. No config change needed unless you relied on the previous prepend order.
- No other breaking changes.

## From 0.3.3 to 0.3.4

No breaking changes. New: dashboard **export/import** (JSON), config **`dashboard.layout_template`** to choose the Twig layout dashboard views extend (default unchanged), and **MenuUrlResolver** now fills missing route path params from the current request and adds a flash message on URL generation failure. Dashboard content block is now `content` (was `dashboard_body`); if you override the bundle's dashboard layout template, ensure it defines `{% block content %}`.

## From 0.3.2 to 0.3.3

No breaking changes. Permission checkers are now **auto-tagged**: any service implementing `MenuPermissionCheckerInterface` is included in the dashboard dropdown without adding the tag in `services.yaml`. Optionally set the label via the class constant `DASHBOARD_LABEL` or the attribute `#[PermissionCheckerLabel('...')]`. You can remove the manual tag from your checker service and, if you use service discovery (e.g. `App\Service\`: `resource: '../src/Service/'`), you do not need to register the checker explicitly.

## From 0.3.1 to 0.3.2

No breaking changes. `permission_checker_choices` now accepts a **list** of service IDs (to order/filter the dropdown) as well as the existing map (service id => label). The bundle includes a new checker: `PermissionKeyAwareMenuPermissionChecker` (structure example). Demos use the list format and are aligned.

## From 0.3.0 to 0.3.1

No breaking changes. New optional config: `permission_checker_choices` (service id => label map) to customize the "Permission checker" dropdown in the dashboard menu form. Demos updated (Symfony 7 has `DemoMenuPermissionChecker`; both demos show permission keys `path:/`, `authenticated`, `admin` in fixtures).

## From 0.2.x to 0.3.0

- **Restored support:** PHP 8.2 and 8.3, and Symfony 6.4 and 7, are supported again. You can downgrade from PHP 8.4 to 8.2 or use Symfony 6.4/7 if needed.
- **Optional dependency:** `nowo-tech/icon-selector-bundle` is no longer required. If you had it installed, it continues to work (dashboard icon field uses the selector). If you remove it, the icon field becomes a plain text input. To install it optionally: `composer require nowo-tech/icon-selector-bundle` (requires Symfony ^7.0 || ^8.0).

## From 0.1.x to 0.3.0

- **New:** Symfony 6.4 (LTS) is supported; requirements are PHP >= 8.2 &lt; 8.6, Symfony ^6.4 || ^7.0 || ^8.0.
- **Optional:** For the icon selector widget in the dashboard item form you can install `nowo-tech/icon-selector-bundle` (suggested; requires Symfony ^7.0 || ^8.0). Without it, the icon field is a text input.

## From 0.0.1 to 0.1.0

No breaking changes. New features: configurable cache for menu tree, `icon_library_prefix_map`, two-query SQL path in `MenuRepository::findMenuAndItemsRaw()`, Web Profiler panel "Dashboard menus", and Doctrine table quoting via `Platform::quoteSingleIdentifier()`. Optional: configure `cache.ttl` / `cache.pool` and `icon_library_prefix_map` in your config (see [CONFIGURATION](CONFIGURATION.md)).

## 0.0.1 (first release)

No upgrade path; this is the first stable release. Requirements: PHP >= 8.2 &lt; 8.6, Symfony ^6.4 || ^7.0 || ^8.0, Doctrine ORM ^2.13 || ^3.0. No Gedmo or Stof extensions are required.

Future breaking changes will be documented here (e.g. "From 0.x to 1.0").
