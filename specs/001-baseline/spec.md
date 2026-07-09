# Feature Specification: DashboardMenuBundle baseline (100% code coverage)

**Feature Branch**: `001-baseline`  
**Created**: 2026-07-07  
**Status**: Active  
**Input**: Backfill GitHub Spec Kit baseline documenting 100% of production code in `src/`.

**Related docs**: [`docs/SPEC-DRIVEN-DEVELOPMENT.md`](../../docs/SPEC-DRIVEN-DEVELOPMENT.md), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/USAGE.md`](../../docs/USAGE.md)  
**Code inventory (traceability)**: [`code-inventory.md`](code-inventory.md)

---

## Summary

**Package**: `nowo-tech/dashboard-menu-bundle`  
**Configuration root**: `nowo_dashboard_menu`

Symfony bundle for **database-backed dashboard menus**: hierarchical items (parent/position), **JSON translations** per locale, **context-aware resolution** (same menu code, different partner/operator context), **permission filtering** via pluggable checkers, **Twig helpers** and a **JSON API** for SPAs. Optional **admin dashboard** provides CRUD, drag-and-drop reorder, copy, and JSON import/export. Uses Doctrine DBAL/ORM without external ORM extensions. Tree loading is optimized (two-query path, optional PSR-6 cache) with dev-only Web Profiler diagnostics.

---

## User Scenarios & Testing

### US-01 — Render tree-structured menus in Twig (Priority: P1)

As a Symfony integrator, I render a localized menu tree in Twig so dashboard navigation reflects my app hierarchy (sections, links, dividers, nested children).

**Why this priority**: Core consumer-facing value.

**Independent Test**: In a demo app with fixture menus, call `dashboard_menu_tree('sidebar')` and include `menu.html.twig`; output contains nested `<ul>/<li>` with translated labels and resolved hrefs.

**Acceptance Scenarios**:

1. **Given** a menu with items ordered by `position`, **When** `MenuTreeLoader::loadTree()` runs, **Then** root nodes and nested `children` reflect parent/child relationships.
2. **Given** `itemType=section` with children, **When** the menu template renders, **Then** section labels and collapsible branches follow menu config (`nested_collapsible`, `sectionCollapsible`).
3. **Given** `nowo_dashboard_menu.cache.pool` configured, **When** the same `(menuCode, locale, contextSets)` is requested again within TTL, **Then** cached raw menu+items are reused.

---

### US-02 — Localize labels via JSON translations (Priority: P1)

As an integrator, I store per-locale labels on menu items so copy changes without redeploying templates.

**Acceptance Scenarios**:

1. **Given** `MenuItem` translations JSON and request locale `es`, **When** the tree loads, **Then** `MenuLocaleResolver` picks `es` when listed in `nowo_dashboard_menu.locales`, else falls back to `default_locale` or first locale.
2. **Given** missing translation for a locale, **When** label resolves, **Then** fallback chain uses configured default before empty string.

---

### US-03 — Resolve menus by context sets (Priority: P1)

As an integrator with multi-tenant data, I pass ordered context objects so the same menu code resolves to the correct menu row.

**Acceptance Scenarios**:

1. **Given** two menus sharing code `sidebar` with different `context` JSON, **When** `contextSets=[{partnerId:1}, {}]` is passed to `dashboard_menu_tree()`, **Then** the first matching menu row is selected.
2. **Given** `_context_sets` query param on `GET /api/menu/{code}`, **When** API is called, **Then** `MenuApiController` parses JSON and applies the same resolution order as Twig.

---

### US-04 — Consume menus from Twig or JSON API (Priority: P1)

As an integrator, I use Twig functions or the JSON API so menus work in server-rendered pages and SPAs.

**Acceptance Scenarios**:

1. **Given** `nowo_dashboard_menu.api.enabled=true`, **When** `GET {path_prefix}/{code}` is requested, **Then** response is a JSON array of nodes with `label`, `href`, `routeName`, `icon`, `itemType`, `children`.
2. **Given** Twig extension registered, **When** templates call `dashboard_menu_href(item)`, **Then** `MenuUrlResolver` produces internal route URLs or external links.
3. **Given** `dashboard_menu_config(code)`, **When** called, **Then** merged render config (CSS classes, depth limit, icons) is returned for `menu.html.twig`.

---

### US-05 — Filter items by permissions (Priority: P1)

As an integrator, I register `MenuPermissionCheckerInterface` services so items hidden per user without forking the bundle.

**Acceptance Scenarios**:

1. **Given** a menu references checker service id `App\Security\MyChecker`, **When** tree loads, **Then** `MenuTreeLoader` invokes the checker from the compiler-built locator and omits denied items.
2. **Given** no checker configured on menu, **When** tree loads, **Then** `AllowAllMenuPermissionChecker` keeps all items visible.
3. **Given** items with `permissionKey`, **When** `PermissionKeyAwareMenuPermissionChecker` is selected, **Then** items without granted keys are filtered.

---

### US-06 — Manage menus in admin dashboard (Priority: P2)

As a maintainer, I enable the bundled dashboard to CRUD menus/items, reorder trees, copy menus, and import/export JSON.

**Acceptance Scenarios**:

1. **Given** `nowo_dashboard_menu.dashboard.enabled=true` and routes imported with prefix, **When** user opens index, **Then** paginated menu list renders with create/edit/delete/copy actions.
2. **Given** reorder page, **When** user drags items and POSTs to `items_reorder_tree`, **Then** positions update using configured `position_step`.
3. **Given** `dashboard.required_role=ROLE_ADMIN`, **When** unauthorized user hits dashboard route, **Then** `DashboardAccessSubscriber` denies access.

---

### US-07 — Import and export menu definitions (Priority: P2)

As a maintainer, I export menus to JSON and import them in another environment with rate limiting and size caps.

**Acceptance Scenarios**:

1. **Given** export action on a menu, **When** triggered, **Then** `MenuExporter` streams JSON with menus, items, and translations.
2. **Given** upload exceeding `import_max_bytes`, **When** import form submits, **Then** validation fails before parsing.
3. **Given** `import_export_rate_limit` enabled, **When** limit exceeded, **Then** `ImportExportRateLimiter` blocks further import/export for the interval.

---

### US-08 — Generate database migration (Priority: P2)

As an integrator, I generate a migration reflecting configured connection and table prefix.

**Independent Test**: `php bin/console nowo_dashboard_menu:generate-migration --dump` outputs SQL for `dashboard_menu` and `dashboard_menu_item` with prefix applied.

**Acceptance Scenarios**:

1. **Given** custom `doctrine.connection` and `table_prefix`, **When** command runs, **Then** generated migration documents connection/prefix in header and creates prefixed tables.
2. **Given** `--dump`, **When** option passed, **Then** SQL prints without writing a file.

---

### US-09 — Diagnose menu queries in Web Profiler (Priority: P3)

As a developer in `dev`, I inspect menu resolution metrics in the profiler panel.

**Acceptance Scenarios**:

1. **Given** `kernel.environment=dev`, **When** a page renders menus, **Then** `DashboardMenuDataCollector` records resolved codes, locales, cache hits, and tree timings.
2. **Given** DBAL middleware available, **When** menu SQL runs, **Then** `MenuQueryCounter` increments and panel shows query count.

---

### US-10 — Maintain translations (Priority: P3)

As a bundle maintainer, I sync missing translation keys across locale files.

**Acceptance Scenarios**:

1. **Given** English keys added, **When** `nowo_dashboard_menu:translations:google-sync --dry-run` runs, **Then** missing keys per locale are reported.
2. **Given** Google API key and `--apply`, **When** command runs, **Then** locale YAML files are updated in `Resources/translations/`.

---

### Edge Cases

- **Parent cycles**: Item forms use `ParentRelationCycleDetector` to reject parent assignments that would create loops.
- **Multiple menus same code**: First matching context set wins; empty context `{}` matches menus with null/empty context JSON.
- **Disabled API**: When `api.enabled=false`, API routes should not expose menu JSON (route import remains integrator responsibility).
- **Missing UX LiveComponent**: Item form falls back to non-live partial; extension sets `item_form_live_component_enabled` parameter accordingly.
- **Table prefix**: `TablePrefixSubscriber` rewrites entity table names from `doctrine.table_prefix` at runtime.
- **Custom link types**: Services implementing `MenuLinkResolverInterface` resolve non-route links via tagged locator + dashboard dropdown ordering.

---

## Requirements

### Bundle & DI

- **FR-BUNDLE-001**: `NowoDashboardMenuBundle` MUST register compiler passes (`AutoTagPermissionCheckersPass`, `AutoTagMenuLinkResolversPass`, `TwigPathsPass`, `PermissionCheckerPass`, `MenuLinkResolverPass`), expose `TRANSLATION_DOMAIN`, and return `DashboardMenuExtension` as container extension (alias `nowo_dashboard_menu`).
- **FR-DI-001**: `services.yaml` MUST wire autowired services; `services_dev.yaml` adds profiler/collector wiring in dev; `services_live_component.yaml` loads when UX LiveComponent is present.
- **FR-CFG-001**: `Configuration` MUST define `nowo_dashboard_menu` tree: `project`, `doctrine` (connection, table_prefix), `cache` (ttl, pool), `icon_library_prefix_map`, `locales`, `default_locale`, `permission_checker_choices`, `menu_link_resolver_choices`, `api`, `dashboard` (enabled, layout, pagination, modals, CSS class options, import limits, rate limit, permission keys, etc.).
- **FR-CFG-002**: `DashboardMenuExtension` MUST load service YAML, set `%nowo_dashboard_menu.*%` parameters, register `MenuConfigResolver`, alias `MenuCodeResolverInterface`, optionally register `DashboardAccessSubscriber` and DBAL middleware, and prepend LiveComponent defaults when UX bundle exists.
- **FR-TWIG-001**: `TwigPathsPass` MUST prepend app override path `templates/bundles/NowoDashboardMenuBundle/` when present, then `addPath()` bundle views so integrator overrides win.
- **FR-PLUG-001**: Compiler passes MUST auto-tag permission checkers and link resolvers, merge YAML `*_choices` ordering, and build locators consumed by `MenuTreeLoader`.
- **FR-ATTR-001**: `PermissionCheckerLabel` and `MenuLinkResolverLabel` MUST supply human-readable dashboard dropdown labels for tagged services.

### Persistence & entities

- **FR-ENTITY-001**: `Menu` and `MenuItem` entities MUST persist menu metadata (code, context JSON, CSS classes, permission checker id, collapsible options) and item tree fields (parent, position, route/url, translations, itemType, icon, permission key).
- **FR-ENTITY-002**: `TranslatableInterface` MUST define contract for JSON translation storage on entities.
- **FR-ENTITY-003**: `TablePrefixSubscriber` MUST apply configured table prefix to menu entity metadata.
- **FR-ENTITY-004**: `ParentRelationCycleDetector` MUST detect parent relation cycles before persist.
- **FR-REPO-001**: `MenuRepository` and `MenuItemRepository` MUST provide queries for code+context resolution and ordered item fetch.

### Menu resolution

- **FR-MENU-001**: `MenuTreeLoader` MUST resolve menu by code and context sets, load items in bounded queries, apply permission checkers and link resolvers, build nested tree, optional PSR-6 cache, and record dev collector metrics.
- **FR-MENU-002**: `MenuConfigResolver` MUST merge YAML defaults with per-menu DB config for rendering.
- **FR-MENU-003**: `MenuCodeResolverInterface` / `DefaultMenuCodeResolver` MUST allow request-based menu code override (e.g. query/header) before tree load.
- **FR-MENU-004**: `MenuLocaleResolver` MUST enforce configured locale whitelist and fallback.
- **FR-MENU-005**: `MenuUrlResolver` MUST generate hrefs for route names, parameters, and external URLs.
- **FR-MENU-006**: `MenuIconNameResolver` MUST map icon library names via `icon_library_prefix_map` (e.g. `bootstrap-icons:house` → `bi:house`).
- **FR-MENU-007**: `CurrentRouteTreeDecorator` MUST mark active branch/current item CSS classes on tree nodes for Twig rendering.

### Permissions & import/export

- **FR-PERM-001**: `MenuPermissionCheckerInterface` with `AllowAllMenuPermissionChecker` MUST filter items per menu's configured checker service.
- **FR-PERM-002**: `PermissionKeyAwareMenuPermissionChecker` MUST hide items when permission key is not granted (reference implementation).
- **FR-PLUG-002**: `MenuLinkResolverInterface` MUST allow custom item link resolution strategies via tagged services.
- **FR-IMPORT-001**: `MenuExporter` and `MenuImporter` MUST serialize/deserialize menu graphs including items and translations.
- **FR-IMPORT-002**: `ImportExportRateLimiter` MUST throttle import/export per configured limit/interval using cache pool.

### HTTP — JSON API

- **FR-API-001**: `MenuApiController` MUST expose `GET {api.path_prefix}/{code}` returning nested JSON tree with locale and `_context_sets` support when API enabled.
- **FR-ROUT-001**: `routes.yaml` MUST declare API routes guarded by `api.enabled` parameter.

### HTTP — Admin dashboard

- **FR-DASH-001**: `MenuDashboardController` MUST provide menu/item CRUD, copy, import/export, delete with CSRF, and tree reorder endpoints; shared logic in `DashboardControllerTrait`.
- **FR-DASH-002**: `DashboardRoutes` MUST expose stable route name constants for templates and tests.
- **FR-DASH-003**: Dashboard Twig views MUST render list/detail/forms/modals/reorder UI using translation domain `NowoDashboardMenuBundle` and configurable layout/CSS/modal sizes.
- **FR-ROUT-002**: `routes_dashboard.yaml` MUST declare dashboard routes (integrator sets URL prefix on import).
- **FR-SEC-001**: `DashboardAccessSubscriber` MUST require configured `dashboard.required_role` on dashboard routes when SecurityBundle is present.

### Forms

- **FR-FORM-001**: Form types (`MenuType`, `MenuItemType`, `CopyMenuType`, `ImportMenuType`, and partial types) MUST back dashboard CRUD with validation, route selectors, permission checker dropdowns, and icon fields.
- **FR-FORM-002**: `JsonToArrayTransformer` MUST convert JSON text fields to arrays for entity mapping.

### Live Component

- **FR-LIVE-001**: `ItemFormLiveComponent` MUST provide live-validated item form in modal when Symfony UX LiveComponent is installed.
- **FR-LIVE-002**: Live Component Twig templates/partials MUST render form with Stimulus/Live wiring and modal-safe Tom Select options (`TOM_SELECT_MODAL_DROPDOWN`).

### Twig consumer API

- **FR-TWIG-002**: `MenuExtension` MUST expose functions `dashboard_menu_tree`, `dashboard_menu_href`, `dashboard_menu_config`, globals for dashboard layout and UX autocomplete availability.
- **FR-TWIG-003**: `menu.html.twig` MUST render tree with configurable CSS classes, depth limit, icons (UX Icons), collapsible sections, and optional label span wrapper.

### Web Profiler (dev)

- **FR-PROF-001**: `DashboardMenuDataCollector` MUST register profiler panel with menu resolution diagnostics.
- **FR-PROF-002**: Query profiling stack (`MenuQueryCounter`, middleware, DBAL wrappers, `ChainedSqlLogger`) MUST count SQL statements issued during menu load in dev.
- **FR-PROF-003**: Collector Twig panel and toolbar `icon.svg` MUST display metrics and bundle branding.

### CLI

- **FR-CLI-001**: `nowo_dashboard_menu:generate-migration` MUST generate Doctrine migration class for menu tables respecting connection and table prefix (`--path`, `--namespace`, `--dump`).
- **FR-CLI-002**: `nowo_dashboard_menu:translations:google-sync` MUST diff locale YAML keys against English and optionally auto-translate via Google REST API.

### Frontend assets

- **FR-UI-001**: `dashboard.ts` MUST power dashboard modals, SortableJS reorder UX, and optional UX Autocomplete registration.
- **FR-UI-002**: `logger.ts` MUST provide namespaced debug logging for dashboard scripts.
- **FR-UI-003**: `stimulus-live.ts` MUST bootstrap Stimulus for Live Component modal when no custom `stimulus_script_url` is configured.
- **FR-BUILD-001**: Published `Resources/public/js/*.js` MUST be kept in sync with TypeScript sources before release.

### Internationalization

- **FR-I18N-001**: Translation files `NowoDashboardMenuBundle.{locale}.yaml` MUST cover dashboard UI, form labels, and validation messages for all bundled locales (31 files).

---

## Key Entities

- **Menu**: Identified by `code` + optional `context` JSON; stores render config, permission checker reference, collapsible/icon options.
- **MenuItem**: Tree node with `parent`, `position`, `itemType` (link/section/divider), route/url, translations map, icon, permission key.
- **Tree node** (runtime array): `{ item: MenuItem, children: [...] }` produced by `MenuTreeLoader`.
- **Context set**: Ordered map (e.g. `{partnerId: 1}`) tried until a menu row matches.

---

## Success Criteria

- **SC-001**: 100% of production files in `src/` appear in [`code-inventory.md`](code-inventory.md) with requirement IDs (**119/119** mapped).
- **SC-002**: Twig render and JSON API return equivalent tree structures for the same code/locale/context in demo apps.
- **SC-003**: Configuration keys in [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md) match `Configuration.php`.
- **SC-004**: PHPUnit, PHPStan, and documented QA targets pass in CI (`composer qa`, `make release-check`).
- **SC-005**: Dashboard import/export respects size and rate limits; no silent data loss on copy/import cycles.

---

## Assumptions

- Integrators run Doctrine migrations (or bundle generate-migration command) before using menus.
- Menu definitions live in the database; YAML configures global defaults and dashboard/API toggles only.
- Symfony UX Icons (optional) renders icons when `use_ux_icons` enabled on menu config.
- Admin dashboard is opt-in (`dashboard.enabled=false` by default); route prefix is set in app routing.
- Demos under `demo/` illustrate integration but are not Packagist API.

---

## Configuration reference (normative roots)

| Area | Key paths | Notes |
| --- | --- | --- |
| Doctrine | `doctrine.connection`, `doctrine.table_prefix` | Non-default connection requires migrate `--conn` |
| Cache | `cache.ttl`, `cache.pool` | Empty pool disables tree cache |
| Locales | `locales`, `default_locale` | Whitelist request locale for labels |
| API | `api.enabled`, `api.path_prefix` | Default prefix `/api/menu` |
| Dashboard | `dashboard.enabled`, `dashboard.layout_template`, `dashboard.required_role`, `dashboard.import_max_bytes`, `dashboard.css_class_options`, … | See CONFIGURATION.md |
| Plugins | `permission_checker_choices`, `menu_link_resolver_choices` | Order/merge tagged services |

---

## Explicit non-goals

- Authorization system beyond pluggable item checkers and optional dashboard role (integrator-owned voters/policies).
- Visual menu builder outside bundled dashboard (integrators may build custom UIs using entities/API).
- Guaranteeing Google Translate quality or availability for maintainer translation sync command.
- Committing rebuilt JS without running the asset pipeline when TS sources change.

---

## Validation

| Check | Command |
| --- | --- |
| Full QA | `composer qa` or `make release-check` |
| PHP tests | `vendor/bin/phpunit` |
| Static analysis | `vendor/bin/phpstan analyse` |
| Code inventory | Row count in `code-inventory.md` MUST equal `find src -type f \| sort \| wc -l` (**119**) |
| Config parity | Compare `Configuration.php` with `docs/CONFIGURATION.md` |

When changing behavior, update this spec, `code-inventory.md` if files are added/removed, tests, and integrator docs (`USAGE.md`, `CONFIGURATION.md`, `CHANGELOG.md`).
