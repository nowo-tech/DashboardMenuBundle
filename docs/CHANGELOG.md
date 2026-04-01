# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.36] - 2026-04-01

### Fixed

- **Dashboard sortable tree:** removed unnecessary margin on nested rows in `_sortable_tree_macro.html.twig` so the reorder list aligns more tightly.

### Changed

- **Code style / static analysis:** use imported `InvalidArgumentException` in dashboard controller and `MenuItemRepository`; `static` closures where safe (sortable tree builder, parent `EntityType` query builder); explicit `use function` imports in `ParentRelationCycleDetector`; remove unused `is_array` import in `MenuItemBasicType`; align form option arrays in menu/item types; `GoogleSyncTranslationsCommand` uses explicit `ENT_*` / `JSON_THROW_ON_ERROR` imports; `MenuImporter` phpdoc key order (`array<int|string, mixed>`).
- **Demos:** regenerated `demo/symfony7` and `demo/symfony8` `config/reference.php` stubs include `declare(strict_types=1);` at the top.

## [0.3.35] - 2026-04-01

### Added

- **Dashboard tree reorder (SortableJS):** dedicated page `GET .../{id}/items/reorder` (route `show_items_reorder`); `POST .../{id}/items/reorder-tree` (`items_reorder_tree`, CSRF) applies parent/position from a nested list payload. Menu detail **table** remains on `GET .../{id}` with a link to the reorder view. `MenuItemRepository::applyTreeLayout()` persists the tree; root `package.json` includes `sortablejs` (run `npm run build` when rebuilding bundle `dashboard.js` from source).
- **`dashboard_routes.show_items_reorder`** exposed via `MenuDashboardController::getDashboardRoutes()` for apps overriding dashboard Twig.
- **Twig partials:** `dashboard/show_items_reorder.html.twig`, `dashboard/_sortable_tree_macro.html.twig`, `dashboard/_menu_dashboard_modals.html.twig`, `dashboard/_dashboard_item_icon.html.twig` (optional UX Icons next to item labels in dashboard lists).
- **Bundle logger:** `announce()` for always-on console lines; Sortable move diagnostics when dashboard debug is enabled (`__nowoDashboardMenuConfig.debug`).
- **Sections at root only:** `MenuItemRepository::TREE_LAYOUT_SECTION_MUST_BE_ROOT`; tree apply, Sortable guards, and item forms keep `section` items at root; user-facing flash when a reorder is rejected (`dashboard.reorder_tree_section_not_root`).
- **Dashboard (menu detail):** action **Check parent cycles** next to re-index positions — POST with CSRF — reports via flash whether the persisted parent chain forms a loop (item ids shown).
- **Translations tooling:** new command `nowo_dashboard_menu:translations:google-sync` audits missing keys per locale using a base locale and can auto-translate missing strings with Google Translate API (`--translate-missing`, optional `--write`).
- **Import:** JSON import accepts a **root-level array** of menu blocks `[{ "menu": {...}, "items": [...] }, ...]`, equivalent to `{ "menus": [ ... ] }`. `MenuImporter::normalizeImportPayload()` performs the normalization; dashboard validation messages for invalid root arrays are translated (`dashboard.import_format_*`).

### Changed

- **Divider items:** optional label/translations are allowed (null or empty); persistence normalizes whitespace and no longer clears user-provided names. Dashboard forms show the label fields for dividers with a hint that a name is recommended but optional.
- **Dashboard menu detail (links column):** no longer resolves each item’s route URL via `dashboard_menu_href` in the table. The column shows `routeName` and `routeParams` only, avoiding Symfony router failures that added one flash error per row when mandatory parameters were missing.
- **Parent selector (item config):** disabled Symfony UX Autocomplete / Tom Select on the `parent` `EntityType` so choices always come from `query_builder` (excludes self and subtree). Remote autocomplete rebuilt the form without the editing item and could list the item as its own parent. Validation `validateParentNoCircular` now treats same parent/item by numeric id as well as strict `===`.

### Fixed

- **Sortable init vs. show-page guard:** `initMenuSortablePanel()` runs before the `__dmDashboardMenuShowBound` short-circuit so drag-and-drop reorder still works after in-app navigation (e.g. Turbo) from the table view to the reorder page.

## [0.3.34] - 2026-03-31

### Added

- **Repository governance files:** added `.github/CODEOWNERS`, `.github/PULL_REQUEST_TEMPLATE.md`, `.github/SECURITY.md`, and `.github/workflows/sync-releases.yml` to align with bundle standards and release operations.
- **Translation validation tooling:** added `.scripts/validate-translations.php` and root Makefile target `validate-translations` to validate bundle translation YAML files during QA/release checks.
- **Demo env policy files:** committed `demo/symfony7/.env.test` and `demo/symfony8/.env.test`, and expanded demo `.env.example` with `DEFAULT_URI` and documented `DATABASE_URL` guidance.

### Changed

- **Documentation structure:** normalized README `## Documentation` ordering and moved demo-related links under `### Additional documentation` (canonical format).
- **README tests section:** `## Tests and coverage` now explicitly reports PHP and non-applicable language coverage (`TS/JS N/A`, `Python N/A`).
- **Demo release flow:** demo aggregate `release-check` now runs `update-bundle` for all demos before coverage/verify steps.
- **Issue templates:** bug report template now references the correct package (`nowo-tech/dashboard-menu-bundle`).
- **Assets metadata:** added `packageManager` to root `package.json` (`pnpm@9.15.0`) to document/lock the expected package manager.

### Fixed

- **Database container exposure:** mysql services in root and demo compose files no longer expose host ports; access stays on Docker network only.
- **Coverage post-processing:** `make test-coverage` now executes the coverage parser safely and `.scripts/php-coverage-percent.sh` robustly parses ANSI-colored PHPUnit output.
- **Form reverse transform:** `MenuItemIconType` normalizes `position` empty input as string (`'0'`) in form submission, preventing `IntegerType` reverse-transform errors while preserving entity `int` mapping.
- **Demo gitignore policy:** demo `.gitignore` files are now categorized/commented and include archive patterns and local env exclusions required by standards.

### Documentation

- **README** & **docs/DEMO.md** — Demos: default **`APP_ENV=dev`** uses **Caddyfile.dev** (no PHP worker); worker mode documented as production-style. Default ports **8010** (symfony7) / **8011** (symfony8). Clarified that only **symfony7** and **symfony8** demos exist in-repo; Symfony **6.4** remains supported via Composer.
- **docs/DEMO-FRANKENPHP.md** — Example `bundles.php` aligned with **demo/symfony8** (Security, Doctrine, Migrations, Fixtures, Vite, Stimulus, Twig Inspector, UX Icons, Icon Selector, Autocomplete, Twig/Live Component).

## [0.3.33] - 2026-03-24

### Changed
- **Export payload completeness:** menu/item export now keeps schema keys present and exports `null` for empty values (instead of omitting keys), improving downstream parser stability.
- **Export key ordering:** exported associative objects are now emitted with stable alphabetical key order (including nested item objects) for deterministic snapshots and diffs.

### Fixed
- **Permission payload canonicalization:** item export no longer emits legacy `permissionKey`; only `permissionKeys` and `isUnanimous` are exported.
- **Exporter tests:** service tests now validate null-preserving exports and deterministic key ordering.

## [0.3.32] - 2026-03-24

### Added
- **Menu section/divider classes:** menu configuration now supports dedicated class selectors for item types `section` and `divider` (`classSection`, `classDivider`) with YAML-defined options (including `navigation-header` example for sections).
- **Dashboard form support:** menu configuration form now exposes editable fields for section and divider classes, aligned with existing class options UX.
- **Translations:** added bundle translation keys for the new menu config fields (`class section` / `class divider`) across all shipped locales.

### Changed
- **Resolved menu config/classes:** resolved menu classes now merge entity overrides for `section` and `divider`, including defaults from `dashboard.css_class_options`.
- **Menu rendering template:** `menu.html.twig` now applies configured `section` and `divider` classes at render time instead of relying only on hardcoded fallback classes.
- **Demo/recipe defaults:** Symfony 7 demo, Symfony 8 demo and Flex recipe now include/document `dashboard.css_class_options.section` and `dashboard.css_class_options.divider`.

### Fixed
- **Import/export parity:** menu import/export payloads now include `classSection` and `classDivider`, preventing class loss during round-trips.
- **Export permission payload:** item exports no longer emit legacy `permissionKey`; exports now include only `permissionKeys` (plus `isUnanimous`) as the canonical permission model.
- **Migration update flow:** `nowo_dashboard_menu:generate-migration --update` now adds missing DB columns `class_section` and `class_divider` when needed.
- **QA stability:** test suite no longer reports risky tests for dashboard/import/compiler/access scenarios after adding missing assertions.

## [0.3.31] - 2026-03-24

### Fixed
- **Dashboard import UX/performance:** import modal now submits with a normal POST (no AJAX flow), keeps submit-button double-click protection, and reports import format/errors through page flash alerts after redirect.
- **Import payload format guard:** dashboard import validates top-level JSON format before running importer logic; unsupported root arrays or malformed structures are rejected with explicit user-facing errors.
- **Create/import navigation flow:** after creating a menu (and on import redirects), dashboard returns to referer/index instead of forcing navigation to the new menu detail page.
- **Dashboard item list visibility:** menu item table "Route / URL" column now shows `permissionKeys` and unanimity state (`Unanimous` / `Non-unanimous`) to match the runtime permission model.
- **Static analysis/tests reliability:** phpstan errors across `src/` and `tests/` were resolved and coverage run no longer fails on `AutoTagPermissionCheckersPassTest` expectation drift.

## [0.3.30] - 2026-03-24

### Fixed
- **Import duplicate menus:** `menus` payloads that repeated the same `code` + context block are deduplicated (single import). Dashboard `dashboard.js` guards against registering modal listeners twice when the script is loaded more than once, avoiding a double POST on one submit.
- **Base menu protection:** base menus can no longer be deleted from the dashboard. Editing is restricted: when a menu is marked as base, its persisted fields are treated as immutable and only unsetting the `base` flag is allowed.
- **Dashboard actions for base menus:** dashboard menu list and menu detail views hide destructive/configuration actions for base menus (`delete`, `edit config`) to match backend protection rules.

## [0.3.29] - 2026-03-23

### Added
- **Item permissions model:** dashboard item configuration now uses `permissionKeys` (multiselect array) and `isUnanimous` (AND/OR mode) as the primary permissions model.
- **Bundle dashboard JS bootstrap:** `dashboard.js` now auto-registers Symfony UX Autocomplete (`symfony--ux-autocomplete--autocomplete`) on the detected `StimulusAppLike` and auto-loads Tom Select Bootstrap 5 CSS when needed.
- **Autocomplete diagnostics:** dashboard script now emits explicit debug traces for Stimulus app detection, autocomplete controller registration, and Tom Select CSS loading.

### Changed
- **Docs:** USAGE and CONFIGURATION now document multi-key permissions (`permissionKeys`) and aggregation mode (`isUnanimous`) including checker examples and import/export payload notes.
- **Item permission editor:** `permissionKeys` is now consistently configured as a multi-select autocomplete/tag field (`multiple` + Tom Select options), removing conflicting `expanded` behavior.
- **Demos (Symfony 7/8):** dashboard assets now explicitly register UX Autocomplete controller and include Tom Select CSS in app entrypoints.

### Fixed
- **Demo Symfony 8 dashboard layout:** added bundle dashboard layout override with `vite_entry_*('app')` so demo dashboard pages load the demo entrypoint consistently.
- **Integration test stability:** migration command `--dump` test no longer relies on fragile note line wrapping for `nowo_dashboard_menu.doctrine.connection`.

## [0.3.28] - 2026-03-23

### Changed
- **Menu permission checker form:** `MenuConfigType` now persists checker values as service ids/FQCN (ChoiceType `label => value` mapping), preventing human labels from being stored in `dashboard_menu.permission_checker`.
- **Permission checker resolution:** `MenuTreeLoader` now normalizes legacy checker labels to service ids using configured checker choices before resolving services, reducing fallback-to-allow-all in existing installations.
- **Web Profiler (Permission checks tab):** checker column now focuses on the resolved runtime checker (with fallback badge) and removes redundant selected-label text.
- **Web Profiler tabs:** collector panel tabs now update and restore URL hash fragments (`#nowo-dm-tab-*`) for direct links and refresh persistence.

### Fixed
- **Export/import consistency:** exports no longer propagate mislabeled checker values created via dashboard form submissions; newly saved menu configs keep canonical checker ids.
- **Tests:** updated form and extension tests for checker choice mapping and richer config snapshot expectations; full test and coverage suites remain green.

## [0.3.27] - 2026-03-23

### Added
- **Web Profiler (Configuration tab):** expanded with an explicit "effective config snapshot" table plus a full raw merged config JSON block, so the selected bundle configuration is unambiguous.
- **Web Profiler (Permission checks tab):** table now supports client-side sorting by key columns and shows combined checker details (`Selected / Resolved checker`) in one column.

### Changed
- **Permission checker resolution:** `MenuTreeLoader` now resolves checkers through a tagged service locator (`nowo_dashboard_menu.permission_checker`) instead of the full service container, improving reliability with private/autoconfigured services.
- **Demo fixtures (Symfony 7/8):** demos now exercise both checkers in different menus (`CustomDemoPermissionChecker` and `DemoMenuPermissionChecker`) to make behaviour and fallback diagnosis clearer.

### Fixed
- **Profiler diagnostics:** permission check records now expose clearer fallback/resolution context for selected checker vs resolved runtime checker.
- **Tests:** extension tests updated for the richer stored config snapshot; full test and coverage suites remain green.

## [0.3.26] - 2026-03-23

### Added
- **Demo permission checker:** support for permission expressions with `|` (OR), `&` (AND), and parentheses in demo checkers (Symfony 7/8), with inline usage examples in code comments.
- **Demo fixtures:** new menu items demonstrating expression-based permission keys (OR/AND + grouped expressions) in Symfony 7 and Symfony 8 demos.
- **Docs:** USAGE now documents demo expression syntax, supported tokens, precedence, and examples.

### Fixed
- **Import replace strategy:** replacing an existing menu now removes the full current item tree via repository lookup before re-import, avoiding duplicated links/items when importing the same menu JSON repeatedly.
- **Export payload stability:** `permissionChecker` (menu) and `permissionKey` (item) are now always present in exported JSON (including `null` values), so downstream tools/import flows do not lose those keys.
- **Tests:** importer/exporter unit tests aligned with the updated replace/export behaviour.

## [0.3.25] - 2026-03-20

### Fixed
- **Dashboard import modal:** AJAX import submit now follows redirects reliably and navigates back to the dashboard index after successful imports, avoiding a stuck modal state on `302` responses.
- **Dashboard item list:** item rows are rendered in deterministic tree order (parent/children traversal with sibling sort by `position`, then `id`) so visual ordering matches stored positions.
- **Dashboard table UX:** item `position` is displayed under the parent label to make ordering/debugging easier.
- **Tests:** migration command integration assertion for `--dump` output is now robust to Symfony console note line-wrapping.

### Changed
- **Performance (N+1 reduction):**
  - menu copy now clones items from a preloaded flat list in two passes (no recursive `getChildren()` lazy traversal),
  - descendant id resolution for item edit uses preloaded menu items + in-memory BFS,
  - import post-processing clears link fields for parents with children using an in-memory `hasChildren` map (no per-item lazy `children->count()`),
  - export-all loads items for all menus in a single repository query and groups in memory.

## [0.3.24] - 2026-03-20

### Fixed
- **Dashboard UI:** menu item labels are rendered using locale-resolved `MenuItem::getLabelForLocale()` (avoids empty base `label` when the user stores the text in per-locale translations).
- **Dashboard item forms:** “Add child” modal hides `type`, `icon` and `position` inputs and shows only `label` + per-locale translations (item type is fixed to Link in the form).
- **Dashboard item forms:** the icon section is rendered with a normal Symfony form (not LiveComponent) so the icon-selector widget refreshes reliably when the modal content changes.
- **Dashboard item forms:** label validation accepts either a non-empty base label or at least one non-empty translation; empty `position` values are normalized to `0` to prevent `null` mapping issues.

### Changed
- **Docs/UX:** item form rendering and documentation were aligned for section-based partial submissions (`section`/`_section`, `section_focus`).

## [0.3.23] - 2026-03-20

### Fixed

- **LiveComponent:** prevent Symfony “submitted form data” exceptions when saving items, and stabilize hydration of per-locale `label_{locale}` fields in the item modal.
- **Dashboard UI:** item modal icon field prefill is normalized; when the optional icon-selector bundle is installed it uses `IconSelectorType`, otherwise it falls back to a plain text input (icon is stored as a string).

### Changed

- **Demos:** run dashboard asset builds (`make assets` / `make ts-assets`) inside the demo Docker container to avoid host `pnpm`/permission issues.

## [0.3.22] - 2026-03-20

### Added

- **Config:** `dashboard.icon_size` — CSS size used to render menu item icons (SVG width/height and legacy icon font-size).

### Fixed

- **Twig/UI:** menu item labels are rendered using the locale-resolved `MenuItem.label` (already resolved by `MenuTreeLoader`), avoiding an extra translation pass in Twig.

## [0.3.21] - 2026-03-20

### Added

- **Config/UI:** wrapper `<span>` for non-section menu items controlled by `dashboard.item_span_active`, with wrapper class configurable via `dashboard.css_class_options.span`.

## [0.3.20] - 2026-03-20

### Added

- **Config:** `dashboard.id_options` — list of HTML id values for the root `<ul>` of each rendered menu. Drives the dashboard field `ulId` (dropdown vs plain text).
- **Menu entity:** new nullable property `Menu.ulId` (DB column `ul_id`).
- **Dashboard UI:** menu configuration includes `ulId`, and the frontend template sets `id="..."` on the root `<ul>` when configured.
- **Import/export:** menu export/import now includes `ulId`.
- **Migration generator:** `nowo_dashboard_menu:generate-migration --update` can add the missing `ul_id` column.

## [0.3.19] - 2026-03-20

### Changed

- **Demo Symfony 7:** update dependencies and configuration to keep the demo aligned.

## [0.3.18] - 2026-03-20

### Changed

- **Dashboard UI spacing:** default CSS class options updated for dashboard menu components (`gap-2` → `gap-1`) to keep spacing consistent across menu templates and forms.

## [0.3.17] - 2026-03-19

### Added

- **UX Autocomplete detection:** new Twig global `nowo_dashboard_ux_autocomplete_available` computed from the presence of `Symfony\UX\Autocomplete\AutocompleteBundle`. Dashboard item form templates apply the autocomplete form theme only when it is available.
- **CSRF consistency:** dashboard menu item forms set `csrf_token_id` to `submit` (controller + LiveComponent) to keep CSRF behaviour aligned across Symfony versions.

### Changed

- **MenuDashboardController:** when creating a child item, uses `_query` to include `parent` id in the generated URL.
- **Templates:** wrap `{% form_theme '@SymfonyUXAutocomplete/autocomplete_form_theme.html.twig' %}` in the new availability guard in:
  - `dashboard/_item_form_partial.html.twig`
  - `dashboard/item_form.html.twig`
  - `components/ItemFormLiveComponent.html.twig`

### Fixed

- Avoid warnings/errors when the Symfony UX Autocomplete bundle is not installed.
- Demo Symfony 7: enable sessions and CSRF support (`framework.csrf_protection.enabled`, `framework.session: true`) and enable `framework.property_info` for older Symfony configs.

## [0.3.16] - 2026-03-20

### Added

- **Config:** `dashboard.permission_key_choices` — optional list of permission keys (strings) for the menu item form. When set, the permission key field becomes a select with autocomplete instead of a plain text input; keys can be translated via `form.menu_item_type.permission_key.choice.{key}` (e.g. `path__` for `path:/`).
- **Dashboard item form:** Route name selector and (when configured) permission key selector use Symfony UX Autocomplete form theme for searchable dropdowns. Item form templates include `@SymfonyUXAutocomplete/autocomplete_form_theme.html.twig`.
- **Dashboard:** "Add child" passes parent ID in the form action URL so the new item is correctly associated; "Add child" button is disabled for items of type section or divider (only link-type items can have children). Translation `dashboard.add_child_disabled`.
- **Permission checkers:** Bundle services `AllowAllMenuPermissionChecker` and `PermissionKeyAwareMenuPermissionChecker` are explicitly tagged with `nowo_dashboard_menu.permission_checker` so they always appear in the dashboard menu form dropdown. Demos tag `DemoMenuPermissionChecker` in `services.yaml`.
- **MenuItem:** Property `itemType` is now **nullable** (database column nullable). Getter returns default `link` when null; setter accepts `?string`.
- **Demo Symfony 7:** TypeScript migration aligned with Symfony 8 demo: `ts-assets-template/` (app.ts, bootstrap.ts, controllers in .ts), single `vite.config.ts` (entry `app.ts`), `make ts-assets` copies to `assets/` and removes old .js. README and Makefile document `sudo chown -R $(whoami) assets && make ts-assets` when `assets/` is root-owned.
- **Docs:** USAGE clarifies that template overrides are never blocked and that using the autocomplete form theme does not lock overrides; CONFIGURATION documents `permission_key_choices`.

### Changed

- **MenuItemBasicType:** Translated label fields (`label_{locale}`) are added in `buildForm()` when `availableLocales` is set, so they render correctly with multiple locales (fix for demos). `MenuConfigType` permission checker field uses `choice_translation_domain` for bundle translation keys.
- **Demo Symfony 7:** Removed `vite.config.js`; build uses only `vite.config.ts`. Controllers in `assets/controllers/` are TypeScript (hello_controller.ts, csrf_protection_controller.ts).

## [0.3.15] - 2026-03-16

### Added

- **Dashboard menu form:** Split into **definition** (code, base, name, context, icon) and **configuration** (permission checker, depth, collapsible, CSS classes). `MenuType` composes `MenuDefinitionType` and `MenuConfigType`. New menu and “edit definition” (pencil) show only definition; “edit configuration” (gear) shows only configuration. Option `section` (`basic` | `config` | `null`) on `MenuType`.
- **Dashboard item form:** Same split. `MenuItemType` accepts option `section`; “add item” and “edit identity” (pencil) show only definition (type, icon, labels); “edit configuration” (gear) shows only configuration (position, parent, link, permission). Partial and Live component support `section_focus`; form only includes the requested section when opened from the corresponding modal.
- **Redirect to referer:** After successful form submission (create/update menu or item, delete, copy, import, move up/down), the controller redirects to the request **Referer** when it is a same-origin URL; otherwise redirects to the usual route. Preserves fragment for move actions (e.g. `#item-123`).
- **Import in modal:** Import form is loaded and submitted via AJAX in a modal; on success the page redirects to referer (or index).
- **Dashboard UI:** Two actions per menu row (pencil = edit definition, gear = edit configuration) and per item row (pencil = edit identity, gear = edit configuration). Modal title and scroll target follow the opened section.
- **Translations:** `dashboard.edit_identity`, `dashboard.edit_config`, `form.menu_type.section_definition`, `form.menu_type.section_config` (en, es, fr).
- **Dockerfile:** Added Node.js, npm and pnpm for building dashboard assets inside the container.

### Changed

- **MenuItem:** Property `label` is now **nullable** (for divider items). Getter `getLabel()` returns `''` when null; setter accepts `?string`. Database column `label` is nullable.
- **Twig:** `_menu_form_partial` and `_item_form_partial` no longer use `field.vars.rendered` (removed in Symfony 6.3+); they iterate and render each field once. Menu and item partials render only the section that matches `section_focus` (and only that block is present in the form when `section` is set).
- **Full-page forms:** `menu_form.html.twig` and `item_form.html.twig` show the config section only when `form.config` is defined (new menu / new item use `section => basic`, so config is omitted).

## [0.3.13] - 2026-03-18

### Added

- **Menu option:** `nestedCollapsibleSections` — when disabled, section-type items do not show a collapse toggle and their children are always visible even if `nested_collapsible` is enabled. Configurable per menu in the dashboard form and in import JSON.
- **Dependency:** `symfony/mime` (required) for the `File` validator used in the import form.

### Changed

- **Export/import:** Menu export and import now include `classSectionLabel` and `nestedCollapsibleSections`. Sample `docs/samples/operator-menu-import.json` updated with full label translations (en, es, fr), `heroicons-outline:*` icon format, and `nestedCollapsibleSections`.
- **Import form:** `ImportMenuType` constraints (`NotBlank`, `File`) use named arguments for Symfony 7/8 compatibility.
- **Docs:** UPGRADING sections reordered from newest to oldest.

### Deprecated

- **Config:** `dashboard.path_prefix` is deprecated. Set the dashboard URL prefix in your app routing when importing `@NowoDashboardMenuBundle/Resources/config/routes_dashboard.yaml` (e.g. in `config/routes.yaml` or the recipe’s `config/routes_nowo_dashboard_menu.yaml`). The Flex recipe now adds `config/routes_nowo_dashboard_menu.yaml`; import it from `config/routes.yaml` to enable the dashboard under `/admin/menus`.

## [0.3.12] - 2026-03-18

### Added

- **Command:** `nowo_dashboard_menu:generate-migration` supports `--update` to generate an ALTER migration for existing installations (adds missing columns such as `class_section_label`).

### Changed

- **Demos:** Both demos are configured to use Doctrine Migrations (instead of `doctrine:schema:update`) and ship a single “create tables” migration that matches the current schema (including `class_section_label`).
- **Demos:** Fixtures use sidebar-compatible CSS classes (`nav-item`, `has-sub`, `d-flex align-items-center`, `menu-title text-truncate`).

## [0.3.11] - 2026-03-18

### Added

- **Dependency:** Added `symfony/ux-autocomplete` as a required dependency.
- **Dashboard:** Menu list now shows the number of items per menu (no N+1: counts are fetched in one query).

### Changed

- **Dashboard UI:** Menu items table is more responsive: actions moved to the left and move up/down controls are stacked vertically to reduce required width.

## [0.3.10] - 2026-03-18

### Fixed

- **Tests:** Updated Twig compiler pass unit tests after replacing `RegisterTwigNamespacePass` with `TwigPathsPass`.

## [0.3.9] - 2026-03-19

### Changed

- **Internal:** Twig view path registration uses compiler pass `TwigPathsPass` (replacing `RegisterTwigNamespacePass`). The bundle’s views path is added at the end of the native loader so that application overrides in `templates/bundles/NowoDashboardMenuBundle/` are always consulted first; no behaviour change for users.
- **Internal:** `TwigPathsPass` resolves the loader via `twig.loader.native` when present, then falls back to `twig.loader.native_filesystem`. Extension docblock clarifies that no Twig paths are prepended.

## [0.3.8] - 2026-03-18

### Changed

- **Dashboard:** Export links (“Export” on menu show, “Export all” on index) now open in a new tab (`target="_blank"` with `rel="noopener noreferrer"`).

## [0.3.7] - 2026-03-18

### Added

- **Security:** CSRF validation in `deleteMenu()` and `deleteItem()`; invalid or missing token returns 403.
- **Security:** Move up/down actions now use **POST** (no longer GET) with CSRF tokens; dashboard forms updated accordingly.
- **Security:** Import size limit: config `dashboard.import_max_bytes` (default 2 MiB); controller rejects larger uploads before reading. Clearer JSON errors via `JSON_THROW_ON_ERROR` and `dashboard.import_json_error` / `dashboard.import_file_too_large` messages.
- **Security (sensitive environments):** Optional `dashboard.required_role` (e.g. `ROLE_ADMIN`) so all dashboard routes require that role (requires SecurityBundle). Optional `dashboard.import_export_rate_limit` with `limit` and `interval` to rate-limit import/export per user or IP (returns 429 when exceeded). See [SECURITY](SECURITY.md) and [CONFIGURATION](CONFIGURATION.md#dashboard).
- **Event subscriber:** `DashboardAccessSubscriber` enforces `required_role` when set.
- **Service:** `ImportExportRateLimiter` (cache-based, fixed window) for import/export actions.
- **Translations:** `dashboard.import_json_error`, `dashboard.import_file_too_large` (en, es).

### Changed

- **Dashboard:** Move up/down buttons in `show.html.twig` are now POST forms with hidden `_token` instead of links.
- **Docs:** [CONFIGURATION](CONFIGURATION.md) documents `import_max_bytes`, `required_role`, `import_export_rate_limit`. [SECURITY](SECURITY.md) explains dashboard hardening for production.

## [0.3.6] - 2026-03-18

### Fixed

- **Twig:** Registered the `@NowoDashboardMenuBundle` namespace via a compiler pass so templates render correctly even when Twig does not auto-register bundle paths.
- **Template overrides:** Bundle Twig paths are added after application paths, so overrides in `templates/bundles/NowoDashboardMenuBundle/` take precedence (standard Symfony behaviour).

### Changed

- **Internal:** Replaced Twig path registration via extension prepend with a compiler pass (`RegisterTwigNamespacePass`) to avoid blocking application template overrides.

## [0.3.5] - 2026-03-18

### Added

- **Translation domain:** Bundle uses domain **NowoDashboardMenuBundle** for all UI strings (dashboard, form labels, validation). Constant `NowoDashboardMenuBundle::TRANSLATION_DOMAIN` for use in code; form types and controller use it.
- **Docs:** [USAGE](USAGE.md#overriding-templates-and-translations) documents overriding templates (`templates/bundles/NowoDashboardMenuBundle/`) and translations (`translations/NowoDashboardMenuBundle.{locale}.yaml` with same key structure).

### Changed

- **Translations (breaking for overrides):** Bundle translation files are now `NowoDashboardMenuBundle.{locale}.yaml` (replacing `messages.*` and `validators.*`). To override strings in your app, create `translations/NowoDashboardMenuBundle.{locale}.yaml` with the same keys (e.g. `dashboard.title`, `form.copy_menu_type.code.regex_message`). See [UPGRADING](UPGRADING.md#from-034-to-035).
- **Extension:** `DashboardMenuExtension` no longer prepends Twig or translator paths; Symfony’s default behaviour applies so your app’s `templates/bundles/NowoDashboardMenuBundle/` and translation files take precedence over the bundle’s.
- **Dashboard layout:** `{% trans_default_domain 'NowoDashboardMenuBundle' %}` set in the bundle’s dashboard layout so all dashboard views use the bundle domain.

### Fixed

- **Tests:** Additional coverage for PermissionCheckerPass (config/order/labels not array), AutoTagPermissionCheckersPass (empty or non-string constant), MenuImporter and MenuRepository edge cases.

## [0.3.4] - 2026-03-18

### Added

- **Dashboard export/import:** Export one menu or all menus as JSON (config + item tree, no internal IDs). Import from JSON with strategy **Skip existing** or **Replace**. See [USAGE](USAGE.md#dashboard-export-and-import) and [CONFIGURATION](CONFIGURATION.md#dashboard) (dashboard routes).
- **Config:** `dashboard.layout_template` — Twig template that dashboard views extend (default: `@NowoDashboardMenuBundle/dashboard/layout.html.twig`). Must define block `content`. Override to use your app base layout. Recipe and demos document the option.
- **MenuUrlResolver:** Fills missing path parameters from the current request’s route params when generating item URLs (e.g. same `id` or `_locale`). On URL generation failure, adds an error message to the session flash bag.
- **Twig global:** `nowo_dashboard_layout_template` — value of `dashboard.layout_template` for use in templates (e.g. `{% extends nowo_dashboard_layout_template %}`).

### Changed

- **Dashboard views:** Main content block renamed from `dashboard_body` to `content` for consistency. Override `templates/bundles/NowoDashboardMenuBundle/dashboard/layout.html.twig` if you extend the bundle layout and use custom blocks.
- **Docs:** CONFIGURATION documents `layout_template`; USAGE documents export/import and URL resolution from current request.

### Fixed

- **MenuExporter:** `exportAll()` no longer passes a locale argument to `exportMenu()` (signature has no locale parameter).
- **Compiler pass:** `AutoTagPermissionCheckersPass` skips service definitions whose class cannot be loaded (e.g. when PhpParser or other optional deps trigger autoload errors during container compile).

## [0.3.3] - 2026-03-17

### Added

- **Auto-tag permission checkers:** Any service whose class implements `MenuPermissionCheckerInterface` is automatically tagged with `nowo_dashboard_menu.permission_checker` (no need to add the tag in `services.yaml`). Label is taken from the class constant `DASHBOARD_LABEL`, the attribute `#[PermissionCheckerLabel('...')]`, or the service id. See [USAGE](USAGE.md#permissions) and [CONFIGURATION](CONFIGURATION.md#permission_checker_choices).
- **Attribute:** `Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel` — use on the checker class to set the dashboard dropdown label without a constant.
- **Tests:** `AutoTagPermissionCheckersPassTest` — auto-tag, label from constant/attribute, no override of existing tag, integration with `PermissionCheckerPass`.

### Changed

- **Bundle checkers:** `AllowAllMenuPermissionChecker` and `PermissionKeyAwareMenuPermissionChecker` now use the `DASHBOARD_LABEL` constant; the tag is no longer defined in the bundle `services.yaml` (added by `AutoTagPermissionCheckersPass`).
- **Demos:** `DemoMenuPermissionChecker` uses `DASHBOARD_LABEL` and is discovered via `App\Service\` resource (no explicit service definition). Both demos document auto-tag and service discovery in `services.yaml`.

## [0.3.2] - 2026-03-17

### Added

- **Config:** `permission_checker_choices` now accepts a **list** of service IDs (to order and filter the dropdown) in addition to the existing map (service id => label). List format uses labels from the service tag; map overrides labels. See [CONFIGURATION](CONFIGURATION.md#permission_checker_choices).
- **Bundle:** `PermissionKeyAwareMenuPermissionChecker` — permission checker that hides items with a non-empty `permission_key` (structure/example for extending or replacing with your own logic). Tagged and available in the dashboard dropdown.
- **Demos:** Both symfony7 and symfony8 demos use the list format for `permission_checker_choices` (AllowAll, PermissionKeyAware, Demo) and are aligned in structure and formatting.

### Changed

- **Docs:** CONFIGURATION.md documents list and map formats for `permission_checker_choices` and mentions `PermissionKeyAwareMenuPermissionChecker`. USAGE.md updated to reference list or map.

## [0.3.1] - 2026-03-17

### Added

- **Config:** `permission_checker_choices` — optional map (service id => label) in `nowo_dashboard_menu.yaml` to add or override labels in the dashboard menu form “Permission checker” dropdown. Merged with services tagged `nowo_dashboard_menu.permission_checker`. See [CONFIGURATION](CONFIGURATION.md#permission_checker_choices).
- **Demos:** Symfony 7 demo now includes `DemoMenuPermissionChecker` (class, service registration with tag, and `permission_checker_choices` in config). Both demos have fixture items demonstrating the three permission key types: `path:/`, `authenticated`, and `admin` (“Admin only” item added under Configuration).

### Changed

- **Docs:** CONFIGURATION.md documents `permission_checker_choices`; USAGE.md references it for permission checker labels. Demo permission checker classes document that the bundle passes the current Request as context when rendering from Twig.

### Fixed

- **Tests:** PHPUnit 10+ compatibility — removed `expectDeprecation()` from `ConfigurationTest` and `DashboardMenuExtensionTest` (deprecation API was removed in PHPUnit 10). Config merge tests no longer use the deprecated `dashboard.path_prefix` option.

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

[Unreleased]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.36...HEAD
[0.3.36]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.35...v0.3.36
[0.3.35]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.34...v0.3.35
[0.3.34]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.33...v0.3.34
[0.3.33]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.32...v0.3.33
[0.3.32]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.31...v0.3.32
[0.3.31]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.30...v0.3.31
[0.3.30]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.29...v0.3.30
[0.3.29]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.28...v0.3.29
[0.3.28]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.27...v0.3.28
[0.3.27]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.26...v0.3.27
[0.3.26]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.25...v0.3.26
[0.3.25]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.24...v0.3.25
[0.3.24]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.23...v0.3.24
[0.3.23]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.22...v0.3.23
[0.3.22]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.21...v0.3.22
[0.3.21]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.20...v0.3.21
[0.3.20]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.19...v0.3.20
[0.3.19]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.18...v0.3.19
[0.3.18]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.17...v0.3.18
[0.3.17]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.16...v0.3.17
[0.3.16]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.15...v0.3.16
[0.3.15]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.14...v0.3.15
[0.3.14]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.13...v0.3.14
[0.3.13]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.12...v0.3.13
[0.3.12]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.11...v0.3.12
[0.3.11]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.10...v0.3.11
[0.3.10]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.9...v0.3.10
[0.3.9]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.8...v0.3.9
[0.3.8]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.7...v0.3.8
[0.3.7]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.6...v0.3.7
[0.3.6]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.5...v0.3.6
[0.3.5]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.4...v0.3.5
[0.3.4]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.3...v0.3.4
[0.3.3]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.2...v0.3.3
[0.3.2]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/nowo-tech/DashboardMenuBundle/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/nowo-tech/DashboardMenuBundle/releases/tag/v0.0.1
