# Feature Specification: Admin chrome hooks (blocks, class map, partial paths)

**Feature Branch**: `002-admin-chrome-hooks`  
**Created**: 2026-10-13  
**Status**: Implemented (2.3.0)  
**Input**: Consumer spec `symfony-beacon/specs/134-kit-upstream-improvements` FR-010 — expose the hooks a host needs so its forks of the dashboard pages shrink to a `layout_template` bridge plus config.

**Related docs**: [`docs/USAGE.md` — Customising the admin chrome](../../docs/USAGE.md#customising-the-admin-chrome), [`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md#dashboard), [`docs/UPGRADING.md`](../../docs/UPGRADING.md#to-230)

---

## Summary

Hosts that embed the dashboard in their own admin shell (sidebar, page heading, design tokens, `<dialog>` modals, data tables) used to copy whole pages into `templates/bundles/NowoDashboardMenuBundle/`. Beacon carried ~1,187 lines of such copies. Most of the difference is **chrome** (classes, header / CTA placement, table vs list, row-action style, modal shell and footers, pagination partial), not behaviour. 2.3.0 exposes that chrome as configuration, blocks and partial paths, keeping the default output identical.

---

## Requirements

- **FR-001 (BC)**: with no `dashboard.ui` config the dashboard pages, partials and modals render the same markup as 2.2.4 (verified by rendering the 2.2.4 and 2.3.0 templates with the same data: only whitespace differs, plus the delete token now taken from the controller's `menu_delete_tokens` instead of a second `csrf_token()` call; both are valid tokens for `delete_menu_<id>`).
- **FR-002 (class map)**: `dashboard.ui.classes.<key>` replaces the class of a chrome element; unset = UiKit macro for `css_framework`; `""` removes it. Keys are a closed list (typos fail config validation): `button_primary`, `button_secondary`, `button_danger`, `button_link`, `button_small`, `page_header`, `toolbar`, `search_form`, `search_input`, `info_panel`, `inline_form`, `empty`, `empty_action`, `muted`, `badge`, `badge_base`, `badge_type`, `badge_unanimous`, `badge_non_unanimous`, `list`, `list_item`, `table_wrap`, `table`, `table_header_row`, `table_row`, `row_actions`, `row_action`, `row_action_danger`, `row_action_disabled`, `sortable_panel`, `sortable_header`, `sortable_body`, `modal_loading`, `modal_form`, `modal_content`, `modal_section`, `modal_message`, `modal_actions`.
- **FR-003 (partials)**: `dashboard.ui.partials.pagination` (variables compatible with UiKit `_pagination`), `row_action_inner` (UiKit `_action_inner` contract: `display`, `icon_name`, `label`, plus bundle `icon`), `modal` (shell used with `{% embed %}`; variables `modal_id`, `modal_label_id`, `modal_title`, `modal_body_id`, `modal_size_class`, `modal_scrollable`, `modal_kind`; block `modal_body`).
- **FR-004 (templates map)**: `dashboard.ui.templates.{index, show, show_items_reorder, menu_form_partial, item_form_partial, copy_menu_partial, import_partial, modals}` — the controller renders (and pages include) the configured template, which may extend the bundle template and override blocks without a `templates/bundles/` copy.
- **FR-005 (blocks)**: pages expose `dashboard_page_header`, `dashboard_header_title`, `dashboard_header_links`, `dashboard_header_actions`, `dashboard_search`, `dashboard_toolbar`, `dashboard_menus`, `dashboard_menus_list`, `dashboard_menus_table`, `dashboard_menu_row_actions`, `dashboard_empty_state`, `dashboard_pagination`, `dashboard_new_menu_modal`, `dashboard_menu_properties`, `dashboard_items_table`, `dashboard_item_cells`, `dashboard_item_row_actions`, `dashboard_sortable`, `dashboard_sortable_header`; the modals partial exposes one `dashboard_modal_*` block per modal.
- **FR-006 (layout placement)**: header links + actions are rendered once per page into `nowo_dashboard_menu_header_actions`; with `header_actions_in_layout: true` the page does not render its header / toolbar and the `layout_template` prints the variable.
- **FR-007 (switches)**: `include_ui_kit_css` (default true), `index_view` (`list` | `table`), `actions_column` (`first` | `last`), `row_action_display` (null | `icon` | `text` | `icon_text`), `region_attribute` (attribute name for `dashboard-menu-menus-table`, `dashboard-menu-items-table`, `dashboard-menu-sortable`), `form_themes` (applied to search, reorder, modal bodies and the Live item form).
- **FR-008 (i18n)**: no hard-coded English in the show page; new keys `dashboard.table_code`, `table_name`, `table_items`, `table_context`, `no_permission_keys`, `unanimous`, `non_unanimous`, `position_short` in all 31 locales.
- **FR-009 (JS contract)**: modal ids, `*-body` ids, `btn-*` hook classes, `data-*` attributes, `#nowo-menu-sortable-*`, `data-import-form`, Live `data-action` are emitted by the bundle in every configuration.

## Implementation

- `Service/DashboardUiConfig` (immutable, worker-safe) built from `%nowo_dashboard_menu.dashboard.ui%`; injected in `MenuExtension` (global `nowo_dashboard_menu_ui`, function `dashboard_menu_ui_class()`) and `MenuDashboardController` (template map).
- `Configuration::dashboardUiNode()` (enum / closed keys).
- Templates: `index`, `show`, `show_items_reorder`, `_menu_dashboard_modals`, `_menu_form_partial`, `_item_form_partial`, `_copy_menu_partial`, `_import_partial`, `components/ItemFormLiveComponent`, `base`; new `_modal`, `_pagination`, `_row_action_inner`, `_menu_properties`, `_delete_confirm_body`; `_icons::by_name()`.

## Tests

- `tests/Unit/Templates/DashboardChromeHooksTest.php` — real Twig environment (`DashboardTemplateRenderer`: UiKit macros, form / translation / routing / asset / CSRF extensions, strict variables): default chrome, host config (Beacon-like), table view + empty state, template-map block override, show / reorder hooks, translated badges (`es`), modal bodies, row-action display modes, modals template map, compile check of every dashboard template.
- `tests/Unit/Service/DashboardUiConfigTest.php` — value object + configuration tree (defaults, validation, typo rejection).
- `tests/Unit/Templates/ChromeTranslationsTest.php` — the new keys in all 31 locales.
- `tests/Unit/Twig/MenuExtensionTest.php`, `tests/Unit/DependencyInjection/DashboardMenuExtensionTest.php` — function / global / parameter wiring.

---

## Beacon fork verdicts (consumer: `symfony-beacon/templates/bundles/NowoDashboardMenuBundle/`)

Diffed against the 2.2.4 vendor templates (identical to this repository's 2.2.4 tag). Classification: **chrome** = classes, button styles, header / CTA placement, panel wrappers, table classes, row actions, pagination partial, empty states, modal shell / footers, form theme; **behaviour** = data, routes, CSRF, JS hooks, product logic.

Host config that replaces the forks (Beacon side, FR-024):

```yaml
nowo_dashboard_menu:
    dashboard:
        layout_template: 'kit/menu_dashboard_layout.html.twig'
        css_framework: tailwind
        ui:
            include_ui_kit_css: false
            header_actions_in_layout: true
            index_view: table
            actions_column: last
            row_action_display: text            # = nowo_ui_kit.row_actions_display
            region_attribute: data-testid       # keeps the E2E selectors
            classes:
                button_primary: btn-primary
                button_secondary: btn-ghost
                button_small: btn-ghost
                button_link: btn-ghost
                button_danger: btn-danger
                search_form: 'beacon-toolbar mb-4'
                search_input: 'input min-w-56 flex-1'
                info_panel: 'panel mb-4 text-sm text-[var(--color-ink)]/75'
                empty: 'text-sm text-[var(--color-ink)]/65'
                empty_action: 'btn-ghost btn-sm align-baseline'
                muted: 'text-[var(--color-ink)]/65'
                badge: 'badge bg-[var(--color-sand)] text-[var(--color-ink)]/75'
                badge_type: 'badge bg-[var(--color-sand)] text-[var(--color-ink)]/75'
                badge_base: 'badge bg-[var(--color-moss)]/15 text-[var(--color-moss)]'
                badge_unanimous: 'badge bg-[var(--color-moss)]/15 text-[var(--color-moss)]'
                badge_non_unanimous: 'badge bg-[var(--color-alert)]/15 text-[var(--color-alert)]'
                table_wrap: 'panel overflow-x-auto'
                table: 'data-table w-full text-left text-sm'
                table_header_row: 'border-b border-[var(--color-sand)] text-[var(--color-ink)]/65'
                table_row: 'row-hover border-b border-[var(--color-sand)]/70 last:border-0'
                row_actions: 'inline-flex max-w-full items-center justify-end gap-1 flex-wrap nowo-ui-row-actions nowo-ui-row-actions--text'
                row_action: 'btn-ghost btn-sm btn-label'
                row_action_danger: 'btn-danger btn-sm btn-label'
                row_action_disabled: opacity-40
                inline_form: inline
                sortable_panel: panel
                sortable_header: 'mb-3 flex flex-wrap items-baseline justify-between gap-2 border-b border-[var(--color-sand)] pb-3'
                sortable_body: ''
                modal_loading: 'kit-modal__lead text-center'
                modal_form: kit-modal__panel
                modal_content: confirm-dialog__content
                modal_section: kit-modal__section
                modal_message: confirm-dialog__body
                modal_actions: confirm-dialog__actions
            partials:
                modal: 'kit/_dashboard_menu_modal.html.twig'   # <dialog class="nowo-ui-modal confirm-dialog"> shell, ~15 lines
                pagination: 'kit/_pagination.html.twig'
```

plus one line in `kit/menu_dashboard_layout.html.twig`: `{% set kit_admin_header_actions = nowo_dashboard_menu_header_actions|default('') %}`.

| Beacon file (lines) | Differences found | Verdict |
|---------------------|-------------------|---------|
| `dashboard/base.html.twig` (51) | No `nowo-ui.css` append (chrome); JSON island / `dashboard.js` tag identical; `csp_nonce()` vs request attribute `csp_nonce` (same value: http-hardening stores the nonce in that attribute); default layout / framework fallbacks unused (config sets them). | **Can be deleted** — `ui.include_ui_kit_css: false`. |
| `dashboard/index.html.twig` (193) | CTAs in `kit_admin_header_actions`; table instead of list; `btn-ghost` / `btn-primary` / `btn-danger`; `_action_inner` text actions; `panel` + `data-table` + `data-testid`; empty row inside the table; `kit/_pagination`; `<dialog>` new-menu modal; search via host `search_query_form()` + `form/_fields` (host REQ-TWIG-005 rule for host templates; the bundle `SearchQueryType` already has placeholder / aria-label); code column shows the raw icon name. All chrome. | **Can be deleted** — `header_actions_in_layout`, `index_view: table`, class map, `region_attribute`, `partials.pagination`, `partials.modal`. Leftovers handled in host CSS: `no-underline` on link buttons, cell padding utilities. If the exact search markup must stay: **shrink to ~12 lines** with `ui.templates.index` overriding `dashboard_search`. |
| `dashboard/show.html.twig` (304) | CTAs (reorder link, export, copy, edit, config, re-index, cycles, add item) in the header; no back link / `<h1>` (layout heading); actions column last; `panel` property boxes; Beacon badge colours; text row actions with flat move buttons; translated "No permission keys" (host key); hard-coded "Unanimous" / "Non-unanimous" (line 186) and "Label (xx)"; `csrf_action_form()` fallbacks for forms the controller always passes. All chrome / i18n. | **Can be deleted** — `header_actions_in_layout`, `actions_column: last`, class map, `row_action_display: text`, `region_attribute`; badges / "No permission keys" / "Pos" now use bundle keys `dashboard.unanimous`, `dashboard.non_unanimous`, `dashboard.no_permission_keys`, `dashboard.position_short` (FR-022: drop the host `dashboard_menu_admin.no_permission_keys` message). Nuances: "Edit menu" uses `button_primary` (Beacon: ghost) and the move buttons stay in a stacked group (`.nowo-dashboard-menu-move-stack { flex-direction: row }` in host CSS). For a ghost "Edit menu": **shrink to ~45 lines** overriding `dashboard_header_actions` via `ui.templates.show`. |
| `dashboard/show_items_reorder.html.twig` (110) | Same header CTAs (+ "Table view"); `panel` instead of card; `<h3>` title; `data-testid="dashboard-menu-sortable"`; reorder form through `form_row` + host `form/_fields`; `csrf_action_form()` fallback. Chrome. | **Can be deleted** — `header_actions_in_layout`, `sortable_*` classes, `region_attribute`. Title tag (`<span class="fw-semibold">` vs `<h3>`) via CSS, or **shrink to ~6 lines** overriding `dashboard_sortable_header`. |
| `dashboard/_menu_dashboard_modals.html.twig` (94) | `<dialog class="nowo-ui-modal confirm-dialog">` shells with panel / header / body; `confirm-dialog--wide` for the item modal; loading lead; delete forms wrap the whole panel with Beacon footer; `csrf_action_form('#')` fallbacks when the page did not pass the delete forms. Chrome (ids, body ids and form ids preserved). | **Can be deleted** — `partials.modal` (one shell, reusable by the other kits; `modal_size_class == 'modal-lg'` → `confirm-dialog--wide`) + `modal_loading`, `modal_message`, `modal_actions`, `button_*`. Nuance: the delete `<form>` sits inside the body instead of wrapping the panel (same look with the footer class). |
| `dashboard/_menu_form_partial.html.twig` (40) | Form / content / section / footer classes; Cancel button; `flat_hidden_fields()` instead of the raw `_section` hidden input (host template rule only). Chrome. | **Can be deleted** — `modal_form`, `modal_content`, `modal_section`, `modal_actions`. Section spacing (`flex flex-col gap-3`, `hr` colour) in host CSS. |
| `dashboard/_item_form_partial.html.twig` (151) | Same as the menu partial; field ids / JS toggles unchanged. Chrome. | **Can be deleted** — same keys. |
| `dashboard/_copy_menu_partial.html.twig` (18) | Form / content / footer classes, help text inside the content, no `max-width`. Chrome. | **Can be deleted** — same keys (`max-width` is dropped when `modal_form` is set). |
| `dashboard/_import_partial.html.twig` (25) | Form / content / footer classes; `flat_hidden_fields()` for `_modal`; `form/_fields` include; help text placement. Chrome. | **Can be deleted** — same keys (`data-import-form` kept). |
| `components/ItemFormLiveComponent.html.twig` (201) | Form / content / section / footer classes + Cancel; `flat_hidden_fields()` for `_section`; Live `data-action` / `data-model` unchanged. Chrome. | **Can be deleted** — same keys; the bundle Live template renders Cancel inside `modal_actions`. |
| `kit/menu_dashboard_layout.html.twig` (60) | Layout bridge: Administration shell, heading + intro (`admin.hub.menus`), CSP-safe boot JSON for `assets/kit-admin.ts`, modal body portals, Stimulus / icon-selector scripts, modals include (wrap mode does not render `nowo_dashboard_menu_content`). | **Must stay** (product shell / host JS boot). Add the `kit_admin_header_actions` line above. |
| `kit/_kit_admin_layout.html.twig` (45) | Shared shell for every kit (wrap / inline modes, styles, boot JSON slots). | **Must stay** (product chrome shared by all kits). |

Result: the 1,187 forked lines under `templates/bundles/NowoDashboardMenuBundle/` can go; the host keeps the two `kit/` layouts, one ~15-line modal shell and ~50 lines of YAML. The host translation override `translations/NowoDashboardMenuBundle.*.yaml` (`table_*` keys) is also no longer needed.
