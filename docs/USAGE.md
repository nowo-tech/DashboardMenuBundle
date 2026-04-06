# Usage

## Table of contents

- [Twig](#twig)
- [Overriding templates and translations](#overriding-templates-and-translations)
- [Items with children (parent, link vs section)](#items-with-children-parent-link-vs-section)
- [Resolving by context (context sets)](#resolving-by-context-context-sets)
- [JSON API](#json-api)
- [Resolving menu by criteria](#resolving-menu-by-criteria-operatorid-partnerid-menu-name)
- [Permissions](#permissions)
- [Item visibility rules](#item-visibility-rules)
- [Web Profiler diagnostics](#web-profiler-diagnostics)

## Twig

Get the tree for the current locale and optional permission context:

```twig
{% set tree = dashboard_menu_tree('sidebar') %}
{% set menuConfig = dashboard_menu_config('sidebar') %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar', menuConfig: menuConfig } %}
```

If you omit `menuConfig`, the template will call `dashboard_menu_config(menuCode)` itself, so you can also do:

```twig
{% set tree = dashboard_menu_tree('sidebar') %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar' } %}
```

**Render options** (from config): the template uses `menuConfig` for CSS **classes** (menu, item, link, children), **depth_limit** (stops rendering below that level), and **icons** (enabled, use_ux_icons, default). Per-menu options (including classes and icons) are set on the Menu entity in the database (see [CONFIGURATION.md](CONFIGURATION.md)). Icon identifiers (e.g. `bootstrap-icons:house`) are converted using `icon_library_prefix_map` (e.g. to `bi:house`) before being passed to `ux_icon()`.

Generate href for an item:

```twig
{{ dashboard_menu_href(item) }}
```

## Overriding templates and translations

The bundle registers its Twig views so that `@NowoDashboardMenuBundle/...` works, but it adds its path **after** the application paths. Your overrides in **`templates/bundles/NowoDashboardMenuBundle/`** are therefore checked first: you can "pisar" (override) any bundle template by placing a file there with the same relative path. **Overriding is never blocked** — the controller always uses logical names (e.g. `@NowoDashboardMenuBundle/dashboard/show.html.twig`); Twig resolves them via the loader, so your app templates in `templates/bundles/NowoDashboardMenuBundle/` always take precedence. Translations are not prepended, so your app's translation files for the domain `NowoDashboardMenuBundle` take precedence by default.

### Overriding templates (pisar vistas)

Place a file in your project under **`templates/bundles/NowoDashboardMenuBundle/`** with the **same relative path** as inside the bundle. Twig will use your template instead of the bundle’s. The bundle registers its view path after the app paths, so your overrides in `templates/bundles/NowoDashboardMenuBundle/` take precedence.

**Form themes (e.g. autocomplete):** Some bundle templates use `{% form_theme form '@SymfonyUXAutocomplete/autocomplete_form_theme.html.twig' %}` so that route/permission selectors get a searchable dropdown. That reference points to the Symfony UX Autocomplete bundle, not to this bundle. When you override those templates (`item_form.html.twig`, `_item_form_partial.html.twig`, or the Live Component template), you can keep that line to keep the same behaviour, remove it, or replace it with your own form theme; it does not block or lock overrides. In dashboard pages, bundle `dashboard.js` can auto-register UX Autocomplete when Stimulus is available, so bundle templates can still initialize autocomplete even without app-specific JS entrypoints.

**Example:** to override the menu template used when rendering the tree, create:

```
templates/
  bundles/
    NowoDashboardMenuBundle/
      menu.html.twig
```

Copy the original from `vendor/nowo-tech/dashboard-menu-bundle/src/Resources/views/menu.html.twig` and adjust markup, blocks or variables as needed. The bundle passes `menuTree`, `menuCode` and `menuConfig` (and optional `menuConfig` is resolved by the template if omitted).

**Templates you can override:**

| Path | Purpose |
|------|---------|
| `menu.html.twig` | Frontend menu tree (sidebar, nav, etc.). Receives `menuTree`, `menuCode`, `menuConfig`. |
| `dashboard/layout.html.twig` | Layout that all dashboard pages extend. Defines the `content` block. |
| `dashboard/index.html.twig` | Dashboard menu list. |
| `dashboard/show.html.twig` | Single menu detail and item table (links to reorder page). |
| `dashboard/show_items_reorder.html.twig` | Drag-and-drop tree reorder (SortableJS); posts to `items_reorder_tree`. |
| `dashboard/_sortable_tree_macro.html.twig` | Nested list markup for the reorder view. |
| `dashboard/_menu_dashboard_modals.html.twig` | Shared modals for show and reorder pages. |
| `dashboard/_dashboard_item_icon.html.twig` | Optional icon next to item labels in dashboard lists. |
| `dashboard/menu_form.html.twig` | Create/edit menu form. |
| `dashboard/item_form.html.twig` | Create/edit menu item form. |
| `dashboard/copy_menu.html.twig` | Copy menu form. |
| `dashboard/import.html.twig` | Import menus from JSON (standalone page). |
| `dashboard/_import_partial.html.twig` | Partial for import form (loaded in modal). |
| `dashboard/_menu_form_partial.html.twig` | Partial used in menu form. |
| `dashboard/_item_form_partial.html.twig` | Partial used in item form. |
| `dashboard/_copy_menu_partial.html.twig` | Partial used in copy form. |
| `components/ItemFormLiveComponent.html.twig` | Live Component template for the item form (modal). |
| `Collector/dashboard_menu.html.twig` | Web debug toolbar / profiler panel. |

**Dashboard reorder:** the item table (`show`) links to a separate page for drag-and-drop ordering. Symfony registers `nowo_dashboard_menu_dashboard_show_items_reorder` (GET `/{id}/items/reorder`) and `nowo_dashboard_menu_dashboard_items_reorder_tree` (POST `/{id}/items/reorder-tree`, CSRF). Bundle templates use the `dashboard_routes` map (`show_items_reorder`, `items_reorder_tree` keys → those full names) so overrides stay aligned if routing prefixes change.

**Dashboard layout:** besides overriding `dashboard/layout.html.twig` in the bundle path above, you can keep using the bundle layout and only change the **wrapper** via config: set `dashboard.layout_template` in `nowo_dashboard_menu.yaml` to your app layout (e.g. `base.html.twig`) so the dashboard uses your shell (see [CONFIGURATION.md](CONFIGURATION.md#dashboard)). Overriding the file gives full control over the dashboard HTML; the config option only swaps the extended template.

After adding or changing template overrides, clear the Twig cache if needed: `php bin/console cache:clear`.

### Overriding translations

The bundle uses the translation domain **NowoDashboardMenuBundle** for all its strings: dashboard UI (titles, buttons, labels, pagination), form labels and validation messages. Translation files in the bundle are named `NowoDashboardMenuBundle.{locale}.yaml` (e.g. `NowoDashboardMenuBundle.en.yaml`, `NowoDashboardMenuBundle.es.yaml`).

Keys are structured in YAML under `dashboard` (e.g. `dashboard.title`, `dashboard.new_menu`) and under `form` for form labels and validation (e.g. `form.copy_menu_type.code.regex_message`). To override a string, add the same key in your app's translation files for that domain; your app's translations take precedence.

**Override bundle strings** — create or edit `translations/NowoDashboardMenuBundle.{locale}.yaml` in your project (e.g. `NowoDashboardMenuBundle.en.yaml`, `NowoDashboardMenuBundle.es.yaml`):

```yaml
# Override bundle strings – same domain (NowoDashboardMenuBundle) and key structure
dashboard:
  title: My menu admin
  title_suffix: Menus
  menus: Menu list
  new_menu: Create menu
  search_placeholder: "Search…"

form:
  copy_menu_type:
    code:
      regex_message: "Custom validation message."
```

You only need to define the keys you want to change; the rest fall back to the bundle’s translations. Clear the cache after changing translations: `php bin/console cache:clear`.

## Items with children (parent, link vs section)

Hierarchy is defined by the **Parent** field when creating or editing an item in the dashboard: each item can have a parent (or “— Root —” for top-level). No extra “type” is needed to mark something as a parent; if any item has this one as parent, it will have children in the tree.

- **Parent with link:** Use type **Link** and set a route or URL. The item is clickable and, when the menu has **nested collapsible** enabled, it shows a chevron to expand/collapse its children. Children are rendered in a nested list (and stay visible by default when the current route is in that branch).
- **Parent without link:** Use type **Section**. The label is not a link; when it has children and the menu has **nested collapsible** enabled, only the chevron toggles the nested list. Use this for group headers that open/close a block of links.

So: **Link** = clickable, can have children (optional chevron + collapse). **Section** = label only, can have children (optional chevron + collapse). Both support any depth (children, grandchildren, etc.). Enable **nested_collapsible** on the menu (in config or in the dashboard) so that items with children get the expand/collapse control.

## Resolving by context (context sets)

Menus with the same `code` can have different **context** (a JSON key-value map stored on the `Menu` entity, e.g. `{"partnerId": 1, "operatorId": 1}`). You pass an ordered list of context objects; the first menu that matches `code` + context is used. Use `{}` or `null` to match the menu with no context (fallback).

In Twig:

```twig
{% set contextSets = [{ 'partnerId': 1, 'operatorId': 1 }, { 'partnerId': 1 }, {}] %}
{% set tree = dashboard_menu_tree('sidebar', null, contextSets) %}
{% set config = dashboard_menu_config('sidebar', contextSets) %}
{% include '@NowoDashboardMenuBundle/menu.html.twig' with { menuTree: tree, menuCode: 'sidebar', menuConfig: config } %}
```

In the API, send the same list as the `_context_sets` query parameter (JSON-encoded array of objects).

## JSON API

`GET /api/menu/{code}` returns a JSON array of root nodes. Each node has:

- `label` (translated for the request locale)
- `href` (resolved URL)
- `routeName` (if internal route, else null)
- `icon` (resolved icon identifier, e.g. after `icon_library_prefix_map`; null if none)
- `itemType` (e.g. `link`, `section`)
- `children` (same structure, recursively)

Query parameters: `_locale` overrides the request locale; `_context_sets` (JSON array of context objects) resolves which menu variant to use, same as in Twig.

**Link URLs:** Menu hrefs are built from the item’s route name and params. If a route needs path parameters (e.g. `id`, `slug`) that are not set on the item, the bundle fills them from the current request’s route params when available, so links keep the same context (e.g. same entity id). On URL generation failure, an error is added to the flash bag.

## Dashboard export and import

From the dashboard (list of menus) you can:

- **Export all menus** — downloads a single JSON file with every menu and its items (config + tree). Use for backup or moving menus between environments.
- **Export one menu** — same structure for a single menu (e.g. `menu-{code}-export.json`).
- **Import** — upload a JSON file produced by export (from a dedicated page or from the **Import** modal on the index). Choose strategy: **Skip existing** (do not overwrite menus that already exist for the same code+context) or **Replace** (replace items of existing menus). Errors (e.g. invalid format or missing `code`) are shown as flash messages.

**Dashboard forms:** Menu and item forms are split into **definition** (pencil icon: code, name, context, icon for menus; type, icon, labels for items) and **configuration** (gear icon: permission checker, depth, collapsible, CSS for menus; position, parent, link, permission keys for items). New menu and new item show only definition; after saving you can edit configuration via the gear button. When adding a **child** item, the modal fixes the item type (link) and shows only `label` + per-locale translations (no icon/position fields). After any successful action (create, update, delete, copy, import, move), the app redirects to the request **Referer** when it is same-origin, otherwise to the usual list or show page.

The JSON format accepts: one menu as an object with `menu` + `items`; several menus as `menus` (array of `{ menu, items }`); or the **same list of blocks as a root JSON array** `[{ "menu": {...}, "items": [...] }, ...]` (equivalent to `menus`). Item trees and translations are preserved. For permissions, item payloads support both legacy `permissionKey` (single string) and `permissionKeys` (array of strings) plus `isUnanimous` (boolean): `true` = all keys must pass (AND), `false` = any key can pass (OR).

## Resolving menu by criteria (operatorId, partnerId, menu name)

You can resolve which menu to show using custom criteria (e.g. first try operatorId + partnerId + menu name, then partnerId + menu name, then menu name). Implement `Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface` and set `nowo_dashboard_menu.menu_code_resolver` to your service id.

```php
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use Nowo\DashboardMenuBundle\Service\MenuCodeResolverInterface;
use Symfony\Component\HttpFoundation\Request;

final class MyMenuCodeResolver implements MenuCodeResolverInterface
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
    ) {
    }

    public function resolveMenuCode(Request $request, string $hint): string
    {
        $operatorId = $request->attributes->getInt('operatorId', 0);
        $partnerId  = $request->attributes->getInt('partnerId', 0);

        // First try: operator + partner + name
        if ($operatorId > 0 && $partnerId > 0) {
            $code = sprintf('op_%d_partner_%d_%s', $operatorId, $partnerId, $hint);
            if ($this->menuRepository->findOneByCode($code) !== null) {
                return $code;
            }
        }
        // Then: partner + name
        if ($partnerId > 0) {
            $code = sprintf('partner_%d_%s', $partnerId, $hint);
            if ($this->menuRepository->findOneByCode($code) !== null) {
                return $code;
            }
        }
        // Fallback: name only
        return $hint;
    }
}
```

The same resolution is used in Twig (`dashboard_menu_tree('sidebar')`) and in the API (`GET /api/menu/sidebar`). Only links that pass the permission checker are included; parents (and section titles) with no visible children are automatically pruned.

## Permissions

Implement `Nowo\DashboardMenuBundle\Service\MenuPermissionCheckerInterface`:

```php
public function canView(MenuItem $item, mixed $context = null): bool
{
    $keys = $item->getPermissionKeys() ?? [];
    if ($keys === []) {
        return true;
    }

    if ($item->isUnanimous()) {
        foreach ($keys as $key) {
            if (!$this->authorizationChecker->isGranted($key, $context)) {
                return false;
            }
        }

        return true; // AND
    }

    foreach ($keys as $key) {
        if ($this->authorizationChecker->isGranted($key, $context)) {
            return true; // OR
        }
    }

    return false;
}
```

### Demo expression syntax (AND / OR / parentheses)

The demo checker in `demo/symfony7` and `demo/symfony8` supports simple expressions per key, and supports multiple keys via `permissionKeys` + `isUnanimous`:

- `keyA|keyB` → OR (at least one token must pass)
- `keyA&keyB` → AND (all tokens must pass)
- `(keyA|keyB)&keyC` → grouping with parentheses

Supported demo tokens:

- `authenticated`
- `admin`
- `path:/prefix` (matches current request path prefix)

Examples:

```text
authenticated|admin
path:/admin&(authenticated|admin)
(path:/operator|path:/admin)&authenticated
```

Notes:

- The demo checker evaluates parentheses first, then `&` (AND), then `|` (OR).
- Unknown tokens are denied by default in the demo implementation.
- For production logic, create your own checker and resolve tokens with your authorization system.

**Auto-registration:** Any service whose class implements `MenuPermissionCheckerInterface` is automatically included in the dashboard "Permission checker" dropdown; you do not need to add the tag in `services.yaml`. The label in the dropdown can be set in either of these ways (optional):

- **Class constant:** `public const string DASHBOARD_LABEL = 'Your label';`
- **Attribute:** `#[PermissionCheckerLabel('Your label')]` on the class (use `Nowo\DashboardMenuBundle\Attribute\PermissionCheckerLabel`)

If neither is set, the service id (e.g. FQCN) is used as the label. You can discover your checker via a service directory (e.g. `App\Service\`: `resource: '../src/Service/'`) so you do not need to register it explicitly in `services.yaml`. You can still order or override labels via config: `permission_checker_choices` as a list of service IDs or a map (service id => label) in `nowo_dashboard_menu.yaml` (see [CONFIGURATION](CONFIGURATION.md#permission_checker_choices)). Assign a checker to a menu in the dashboard (per-menu) or leave the menu with no checker for default allow-all behaviour. Manually tagging with `nowo_dashboard_menu.permission_checker` in `services.yaml` or using `#[AsTaggedItem]` remains supported.

## Item visibility rules

The bundle applies the following rules in order to decide which items appear in the rendered tree. All rules are enforced in `MenuTreeLoader` (see `buildTree` + `pruneEmptySections`).

### 1. Permission check (per item, all types)

Every item — regardless of type — is evaluated once by the configured `MenuPermissionCheckerInterface::canView($item, $context)`. If the checker returns `false`, the item is excluded from the tree; none of its children are rendered either.

The checker receives the full `MenuItem` object and can inspect `$item->getPermissionKeys()` and `$item->isUnanimous()` to implement OR / AND logic. The bundle does not enforce that logic itself; it is the checker's responsibility.

### 2. Link (leaf) — always visible when the checker allows

A link with **no children in the database** (`had_children = false`) is included as-is if the checker allows it.

```
visible = canView(item)
```

### 3. Link with children — visible only when it has at least one visible child

A link that **has children in the database** (`had_children = true`) is pruned from the tree when all its children are hidden by the permission checker (or are themselves pruned recursively).

```
visible = canView(item) AND count(visible_children) > 0
```

Rationale: a parent link with no visible children renders as a clickable element with nowhere useful to go and an empty submenu.

### 4. Section — visible only when it has at least one visible child

A section (`itemType = section`) that **has children in the database** (`had_children = true`) is pruned when all its children are hidden by the permission checker.

```
visible = canView(item) AND count(visible_children) > 0
```

A section that **has no children in the database** (`had_children = false`) is always kept when the checker allows it (it may be an intentional standalone heading).

### 5. Divider — visible when the checker allows

Dividers (`itemType = divider`) follow the same rule as leaf links: visible if and only if `canView` returns `true`. Dividers have no children.

### Summary table

| Item type | Has DB children | Checker passes | Visible? |
|-----------|-----------------|---------------|----------|
| Link (leaf) | No | Yes | ✅ |
| Link (leaf) | No | No | ❌ |
| Link (parent) | Yes | Yes | Only if ≥ 1 child visible |
| Link (parent) | Yes | No | ❌ |
| Section | No | Yes | ✅ |
| Section | No | No | ❌ |
| Section | Yes | Yes | Only if ≥ 1 child visible |
| Section | Yes | No | ❌ |
| Divider | — | Yes | ✅ |
| Divider | — | No | ❌ |

> **Note:** pruning is applied recursively from the deepest level up. A grandchild being hidden can cause a parent link or section to be pruned if all its children end up hidden.

## Web Profiler diagnostics

In `dev`, the **Dashboard menus** profiler panel includes:

- **Menus**: rendered menus, resolved context, root count, query segment counts and a compact tree summary.
- **Configuration**: effective bundle snapshot (key values) plus a raw merged config JSON block.
- **Permission checks**: per-link checker evaluation (`menu + link`, `permission key`, resolved checker, `result`) with sortable columns.
- **Tab deep-linking**: collector tabs persist in the URL hash (`#nowo-dm-tab-menus`, `#nowo-dm-tab-configuration`, `#nowo-dm-tab-permission-checks`) and are restored after refresh.

These diagnostics are request-scoped and intended for troubleshooting runtime selection (e.g. checker fallback, context resolution, visibility pruning).
