# Feature Specification: Nested menu collapse without Bootstrap JS

**Feature Branch**: `003-menu-collapse-without-bootstrap`  
**Created**: 2026-10-13  
**Status**: Implemented (2.6.0)  
**Input**: Consumer spec `symfony-beacon/specs/138-kit-medium-extractions` FR-020 (KIT-MENU-COLLAPSE) — nested menu items collapse without Bootstrap JS, state remembered under a configurable key. Host reference: Beacon `assets/controllers/menu_nested_collapse_controller.ts` (Stimulus polyfill of the bundle's `data-bs-toggle="collapse"` markup, state under `beacon.navCollapse`).

**Related docs**: [`docs/USAGE.md` — Nested collapse without Bootstrap JS](../../docs/USAGE.md#nested-collapse-without-bootstrap-js), [`docs/CONFIGURATION.md` — menu_collapse](../../docs/CONFIGURATION.md#menu_collapse), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-260)

---

## Summary

`menu.html.twig` renders Bootstrap 5 collapse markup for `collapsible` / `nested_collapsible` menus, which needs Bootstrap's JavaScript. Hosts with their own design system (Beacon) carried a Stimulus controller to polyfill it. 2.6.0 ships that behaviour as a dependency-free bundle script, opt-in, CSP-safe, safe next to Bootstrap JS.

---

## Requirements

- **FR-001 (BC / opt-in)**: with the default config (`menu_collapse.enabled: false`) and no `menuCollapse` include variable, `menu.html.twig` output is unchanged. The script acts only on roots marked `data-dashboard-menu-collapse`.
- **FR-002 (markup)**: when enabled, the outermost menu element (`.dashboard-menu-collapsible` wrapper when the menu is `collapsible`, else the root `<ul>`) gets `data-dashboard-menu-collapse="<code>"`, `data-dashboard-menu-collapse-mode`, `data-dashboard-menu-collapse-storage-key` and (when non-empty) `data-dashboard-menu-collapse-branch-class` (first token of `class_branch_expanded`, default `active-branch`). Include variables `menuCollapse`, `menuCollapseMode`, `menuCollapseStorageKey` override the config.
- **FR-003 (config)**: `nowo_dashboard_menu.menu_collapse.{enabled (bool, false), mode (auto|always, auto), storage_key (string, "nowo.dashboardMenu.navCollapse"; "" = no persistence)}`; invalid mode / non-string key fail config validation. Exposed as parameter `%nowo_dashboard_menu.menu_collapse%` and Twig global `nowo_dashboard_menu_collapse`.
- **FR-004 (toggle)**: one delegated click listener per root on `[data-bs-toggle="collapse"]` / `[data-dashboard-menu-collapse-toggle]`; target from `data-bs-target="#id"`, `href="#id"` or `aria-controls`; only panels inside the root; a toggle / panel belongs to its closest root (nested roots never double-toggle).
- **FR-005 (ARIA)**: `aria-expanded` of every toggle of a panel follows the panel; `aria-controls` added when missing; closed panels get `inert` + `aria-hidden="true"`; the panel `<li>` gets `is-expanded` / `is-collapsed`.
- **FR-006 (state)**: `localStorage[storage_key] = { <menu code>: { <panel id>: bool } }` (Beacon's `beacon.navCollapse` format), restored on connect; ancestors of the current item (branch class) always open; invalid JSON / unavailable storage ignored.
- **FR-007 (Bootstrap present)**: `auto` mode never toggles while `window.bootstrap.Collapse` or `jQuery.fn.collapse` exists (checked on each click, so a later-loaded Bootstrap is honoured); it mirrors `show|shown|hidden.bs.collapse` into `inert` / ARIA / storage. `always` mode removes `data-bs-toggle` from its toggles (marks them `data-dashboard-menu-collapse-toggle`) so Bootstrap's data API ignores them.
- **FR-008 (CSP)**: external script only (`js/menu-collapse.js`, IIFE, no eval / inline handlers); the `_menu_collapse_script.html.twig` partial adds the request `csp_nonce`.
- **FR-009 (lifecycle)**: connects on `DOMContentLoaded`, `turbo:load`, `turbo:frame-load`, idempotent per root; `window.NowoDashboardMenuCollapse.{init, get}`; ES module exports `MenuCollapse`, `initMenuCollapse`, `autoInit`, `readOptions`, `hasBootstrapCollapse` for bundler hosts; event `dashboard-menu:collapse-toggle` (`detail: { menuCode, id, open }`).

## Implementation

- `src/Resources/assets/src/menu-collapse.ts` (logic) + `menu-collapse-boot.ts` (entry) → Vite entry `menu-collapse` → `src/Resources/public/js/menu-collapse.js` (IIFE, `pnpm run build`).
- `Configuration` node `menu_collapse`; `DashboardMenuExtension` parameter `nowo_dashboard_menu.menu_collapse`; `MenuExtension` argument `$menuCollapse` + global `nowo_dashboard_menu_collapse`.
- `menu.html.twig` root attributes; new partial `_menu_collapse_script.html.twig`.
- `menu.html.twig` no longer evaluates `dashboard_menu_config()` when `menuConfig` is passed (Twig `|default()` evaluated its argument eagerly); same result, one call less.

## Tests

- `src/Resources/assets/src/menu-collapse.test.ts` (Vitest, happy-dom, 100% coverage): options / target helpers, Bootstrap detection, initial state + ARIA, toggle + persistence (custom key, empty key, broken storage), branch-current restore, href toggles, nested roots, disconnect, **Bootstrap present** (fake data-API listener: `auto` toggles exactly once and syncs from `*.bs.collapse`; Bootstrap loaded after connect; `always` takes the toggles over), boot / Turbo / global.
- `tests/Unit/Templates/MenuCollapseTemplateTest.php`: default markup unchanged, config / include overrides, collapsible wrapper placement, script partial (nonce).
- `ConfigurationTest`, `DashboardMenuExtensionTest`, `MenuExtensionTest`: defaults, validation, parameter / argument / global wiring.

## Host adoption (Beacon, spec 138 FR-050)

1. `nowo_dashboard_menu.menu_collapse: { enabled: true, storage_key: beacon.navCollapse }` (same store format: users keep their state).
2. Include `@NowoDashboardMenuBundle/_menu_collapse_script.html.twig` (or import `initMenuCollapse` in `stimulus_bootstrap.ts`); remove `data-controller="menu-nested-collapse"` / `data-action` from `_section_nav.html.twig`, delete `menu_nested_collapse_controller.ts` + test and its registration.
3. CSS: `[data-controller~='menu-nested-collapse']:not(.is-nav-collapse-ready) .collapse` → `[data-dashboard-menu-collapse]:not(.is-nav-collapse-ready) .collapse`.
4. Branch class: the template emits the menu's `class_branch_expanded` (Beacon: `is-branch-current`).
