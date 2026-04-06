# Upgrading

This document describes breaking changes and upgrade notes between versions. Sections are ordered from newest to oldest.

## From 0.3.36 to 0.3.37

No intentional breaking changes to the public HTTP API or route names.

- **Database:** new nullable columns (e.g. `link_resolver` on menu items, `section_collapsible`, menu CSS class fields). Run `bin/console nowo_dashboard_menu:generate-migration` (or your own migration) against the bundle schema and migrate before using the new features in production.
- **Config:** optional `menu_link_resolver_choices` (service id => label) when you register `MenuLinkResolverInterface` implementations; auto-tagging applies tag `nowo_dashboard_menu.menu_link_resolver` when enabled.
- **Custom `MenuItemBasicType` service:** if you copied the bundle definition, remove the `$translator: '@translator'` argument — the type no longer accepts `TranslatorInterface`.
- **Internal PHP classes:** `MenuCrudController` and `MenuItemController` were removed (they were never wired by the bundle’s `routes_dashboard.yaml`). If you forked them, keep your copy in the app namespace.
- **Templates / integrations:** prefer `DashboardRoutes` (or the `dashboard_routes` map) for route names; `MenuDashboardController::ROUTE_*` still work as aliases.

## From 0.3.35 to 0.3.36

No breaking changes.

- **Patch release:** Twig spacing fix for the sortable reorder tree, internal CS/static-analysis cleanups (imports, `static` closures), and demo `reference.php` stub alignment. No configuration or public API changes.

## From 0.3.34 to 0.3.35

No breaking changes.

- **Dashboard URLs:** menu item **table** stays on `GET .../{id}`; **drag-and-drop reorder** is on `GET .../{id}/items/reorder` (route name `show_items_reorder`). Apply tree: `POST .../{id}/items/reorder-tree` (`items_reorder_tree`). Override templates can use `dashboard_routes.show_items_reorder` from the controller’s `getDashboardRoutes()` payload where the bundle already passes `dashboard_routes`.
- **Assets:** if you rebuild dashboard JavaScript from this bundle’s sources (fork or path repository), run `npm install` and `npm run build` at the bundle root so `dashboard.js` includes SortableJS.
- **Section items:** `section` rows must remain at **root** (no nested sections). Persisted trees that violate this will get a validation error when applying reorder; fix parent/positions in the dashboard or import before using the tree endpoint.

## From 0.3.33 to 0.3.34

No breaking changes.

- **Release/governance files:** repository now includes CODEOWNERS, PR template, SECURITY policy, and `sync-releases` workflow.
- **Makefile tooling:** new `validate-translations` target validates bundle translation YAML files via `.scripts/validate-translations.php`.
- **Demo env policy:** demos now include `.env.test`, documented `DEFAULT_URI` in `.env.example`, categorized `.gitignore` blocks, and release flow runs `update-bundle` before demo checks.
- **Coverage parser hardening:** `.scripts/php-coverage-percent.sh` now supports ANSI-colored PHPUnit output and keeps compatibility with `make test-coverage`.
- **Form input normalization:** `MenuItemIconType` now normalizes empty `position` as `'0'` at submit level to satisfy `IntegerType` reverse transform.

## From 0.3.32 to 0.3.33

No breaking changes.

- **Export payload shape:** menu/item exports now preserve declared keys and emit `null` when values are empty (instead of dropping keys).
- **Deterministic ordering:** exported associative objects are alphabetically sorted by key, including nested item objects.
- **Permission key export:** legacy `permissionKey` is no longer exported; canonical fields are `permissionKeys` and `isUnanimous`.

## From 0.3.31 to 0.3.32

No breaking changes.

- **Menu CSS classes by item type:** menus now support dedicated class settings for `section` and `divider` item types (`classSection`, `classDivider`) resolved from menu config and applied in Twig rendering.
- **Configuration options:** `dashboard.css_class_options` now includes `section` and `divider` option lists (demos and recipe include examples such as `navigation-header` for section).
- **Dashboard form:** menu config UI includes fields for the new section/divider class options.
- **Import/export and persistence:** JSON import/export includes `classSection`/`classDivider`; database schema includes nullable `class_section`/`class_divider` columns (migration generator `--update` can add them to existing installations).
- **Translations:** new config field labels/placeholders are available across bundle locales.
- **Export payload cleanup:** exported items no longer include legacy `permissionKey`; only `permissionKeys` and `isUnanimous` are exported. Import remains backward-compatible and still accepts `permissionKey`.
- **QA:** risky-test cases were converted into assertion-based tests; test + coverage + cs-fix/check + rector + phpstan are green.

## From 0.3.30 to 0.3.31

No breaking changes.

- **Import flow:** dashboard import now uses a normal POST submit path (no AJAX roundtrip), with flash-based error/success feedback after redirect.
- **Import validation:** top-level JSON format is validated before import execution; unsupported payload roots are rejected early with explicit messages.
- **Navigation after create/import:** successful create/import redirects return to referer/index instead of always opening the created menu detail page.
- **Base menu safeguards:** base-menu protections remain in place (no delete, immutable while base except unsetting the `base` flag), with UI actions aligned to backend constraints.
- **Dashboard item diagnostics:** item list now displays `permissionKeys` and unanimity badge in the Route/URL column.

## From 0.3.29 to 0.3.30

No breaking changes.

- **Base menu deletion:** dashboard no longer allows deleting menus marked as `base`.
- **Base menu edition policy:** base menus are now treated as immutable in dashboard edit flows; only unsetting the `base` flag is allowed. Any other submitted field changes are ignored while the menu remains base.
- **Dashboard actions:** base menus hide delete and configuration-edit actions in menu list/detail views to align UI with backend constraints.

## From 0.3.28 to 0.3.29

No breaking changes.

- **Item permissions UI/model:** item configuration now uses `permissionKeys` as a multiselect and `isUnanimous` to define aggregation mode.
- **Runtime compatibility:** `permissionKey` remains supported as a legacy single-key fallback; runtime reads `permissionKeys` first.
- **Import/export payloads:** item payloads include `permissionKeys` and `isUnanimous` (and keep `permissionKey` for compatibility with older integrations).
- **Dashboard JS autocomplete bootstrap:** bundle `dashboard.js` now self-registers Symfony UX Autocomplete and Tom Select CSS when Stimulus is available, so bundle dashboard views do not depend on app entrypoints for autocomplete initialization.
- **Demos:** Symfony 7/8 dashboard assets now register UX Autocomplete controller explicitly; Symfony 8 dashboard layout now includes Vite entrypoint tags in bundle override.

## From 0.3.27 to 0.3.28

No breaking changes.

- **Permission checker persistence:** dashboard menu configuration now stores checker service ids/FQCN (not labels) in `dashboard_menu.permission_checker`.
- **Legacy checker values:** runtime checker resolution now normalizes legacy label-based values to configured checker service ids before resolving services.
- **Profiler permission tab:** checker column now displays the resolved runtime checker with fallback indication; selected-label text was removed.
- **Profiler tab links:** collector tabs now persist in URL hash (`#nowo-dm-tab-*`) and restore the selected tab on refresh.

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
