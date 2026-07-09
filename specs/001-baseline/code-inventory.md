# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/dashboard-menu-bundle`  
**Last audited**: 2026-07-07

This file proves that **every production source artifact** under `src/` is referenced by the baseline specification. Test-only files under `tests/` and demo trees are out of Packagist scope unless promoted in the spec.

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoDashboardMenuBundle.php` | Bundle entry, compiler passes | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/DashboardMenuExtension.php` | DI extension, parameters | FR-CFG-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace & overrides | FR-TWIG-001 |
| `DependencyInjection/Compiler/AutoTagPermissionCheckersPass.php` | Permission checker autotag | FR-PLUG-001 |
| `DependencyInjection/Compiler/AutoTagMenuLinkResolversPass.php` | Link resolver autotag | FR-PLUG-001 |
| `DependencyInjection/Compiler/PermissionCheckerPass.php` | Checker locator wiring | FR-PLUG-001 |
| `DependencyInjection/Compiler/MenuLinkResolverPass.php` | Link resolver locator wiring | FR-PLUG-001 |

## Attributes

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Attribute/PermissionCheckerLabel.php` | Dashboard dropdown labels | FR-ATTR-001 |
| `Attribute/MenuLinkResolverLabel.php` | Link resolver dropdown labels | FR-ATTR-001 |

## CLI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/GenerateDashboardMenuMigrationCommand.php` | Schema migration generator | FR-CLI-001 |
| `Command/GoogleSyncTranslationsCommand.php` | Translation sync via Google API | FR-CLI-002 |

## API controller

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/Api/MenuApiController.php` | JSON menu tree API | FR-API-001 |

## Dashboard controllers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/Dashboard/MenuDashboardController.php` | Admin CRUD, import/export, reorder | FR-DASH-001 |
| `Controller/Dashboard/DashboardControllerTrait.php` | Shared dashboard helpers | FR-DASH-001 |
| `Controller/Dashboard/DashboardRoutes.php` | Stable route name constants | FR-DASH-002 |

## DataCollector & query profiling

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DataCollector/DashboardMenuDataCollector.php` | Web Profiler panel | FR-PROF-001 |
| `DataCollector/MenuQueryCounter.php` | Menu SQL query counter | FR-PROF-002 |
| `DataCollector/MenuQueryCountMiddleware.php` | DBAL middleware registration | FR-PROF-002 |
| `DataCollector/ChainedSqlLogger.php` | SQL logger chain (dev) | FR-PROF-002 |
| `DataCollector/Dbal/MenuQueryCountConnection.php` | Connection wrapper | FR-PROF-002 |
| `DataCollector/Dbal/MenuQueryCountDriver.php` | Driver wrapper | FR-PROF-002 |
| `DataCollector/Dbal/MenuQueryCountStatement.php` | Statement wrapper | FR-PROF-002 |

## Entities & persistence

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Entity/Menu.php` | Menu aggregate root | FR-ENTITY-001 |
| `Entity/MenuItem.php` | Tree item, translations JSON | FR-ENTITY-001 |
| `Entity/TranslatableInterface.php` | Translatable label contract | FR-ENTITY-002 |
| `Repository/MenuRepository.php` | Menu persistence queries | FR-REPO-001 |
| `Repository/MenuItemRepository.php` | Item persistence queries | FR-REPO-001 |
| `EventSubscriber/TablePrefixSubscriber.php` | Configurable table prefix | FR-ENTITY-003 |

## Event subscribers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/DashboardAccessSubscriber.php` | Dashboard role gate | FR-SEC-001 |

## Forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/MenuType.php` | Menu create/edit | FR-FORM-001 |
| `Form/MenuConfigType.php` | Per-menu config fields | FR-FORM-001 |
| `Form/MenuDefinitionType.php` | Menu definition subset | FR-FORM-001 |
| `Form/MenuItemType.php` | Item create/edit | FR-FORM-001 |
| `Form/MenuItemBasicType.php` | Item basic fields | FR-FORM-001 |
| `Form/MenuItemConfigType.php` | Item config fields | FR-FORM-001 |
| `Form/MenuItemIconType.php` | Icon selector fields | FR-FORM-001 |
| `Form/CopyMenuType.php` | Copy menu workflow | FR-FORM-001 |
| `Form/ImportMenuType.php` | JSON import upload | FR-FORM-001 |
| `Form/DataTransformer/JsonToArrayTransformer.php` | JSON form transformer | FR-FORM-002 |

## Live Component

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `LiveComponent/ItemFormLiveComponent.php` | Modal item form (UX Live) | FR-LIVE-001 |

## Menu resolution services

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Service/MenuTreeLoader.php` | Tree load, cache, permissions | FR-MENU-001 |
| `Service/MenuConfigResolver.php` | Per-menu render config | FR-MENU-002 |
| `Service/MenuCodeResolverInterface.php` | Menu code resolution contract | FR-MENU-003 |
| `Service/DefaultMenuCodeResolver.php` | Default code resolver | FR-MENU-003 |
| `Service/MenuLocaleResolver.php` | Locale whitelist & fallback | FR-MENU-004 |
| `Service/MenuUrlResolver.php` | Route/URL href generation | FR-MENU-005 |
| `Service/MenuIconNameResolver.php` | Icon library prefix map | FR-MENU-006 |
| `Service/CurrentRouteTreeDecorator.php` | Active branch CSS state | FR-MENU-007 |

## Permissions & link resolvers

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Service/MenuPermissionCheckerInterface.php` | Item visibility contract | FR-PERM-001 |
| `Service/AllowAllMenuPermissionChecker.php` | No-op checker (default) | FR-PERM-001 |
| `Service/PermissionKeyAwareMenuPermissionChecker.php` | Permission-key filter | FR-PERM-002 |
| `Service/MenuLinkResolverInterface.php` | Custom link type contract | FR-PLUG-002 |
| `Service/MenuExporter.php` | Menu JSON export | FR-IMPORT-001 |
| `Service/MenuImporter.php` | Menu JSON import | FR-IMPORT-001 |
| `Service/ImportExportRateLimiter.php` | Import/export rate limit | FR-IMPORT-002 |

## Twig PHP

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/MenuExtension.php` | Twig functions & globals | FR-TWIG-002 |

## Util

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Util/ParentRelationCycleDetector.php` | Parent cycle validation | FR-ENTITY-004 |

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Core service wiring | FR-DI-001 |
| `Resources/config/services_dev.yaml` | Dev profiler wiring | FR-DI-001, FR-PROF-001 |
| `Resources/config/services_live_component.yaml` | Live Component services | FR-DI-001, FR-LIVE-001 |
| `Resources/config/routes.yaml` | JSON API routes | FR-ROUT-001 |
| `Resources/config/routes_dashboard.yaml` | Dashboard CRUD routes | FR-ROUT-002 |

## TypeScript sources

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/dashboard.ts` | Dashboard UX (modals, SortableJS) | FR-UI-001 |
| `Resources/assets/src/logger.ts` | Namespaced debug logger | FR-UI-002 |
| `Resources/assets/src/stimulus-live.ts` | Stimulus bootstrap for Live modal | FR-UI-003 |

## Built JavaScript (published)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/dashboard.js` | Compiled dashboard bundle | FR-BUILD-001 |
| `Resources/public/js/stimulus-live.js` | Compiled Stimulus bootstrap | FR-BUILD-001 |

## Translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoDashboardMenuBundle.ar.yaml` | Arabic UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.ca.yaml` | Catalan UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.cs.yaml` | Czech UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.da.yaml` | Danish UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.de.yaml` | German UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.el.yaml` | Greek UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.en.yaml` | English UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.es.yaml` | Spanish UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.fi.yaml` | Finnish UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.fr.yaml` | French UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.he.yaml` | Hebrew UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.hu.yaml` | Hungarian UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.id.yaml` | Indonesian UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.it.yaml` | Italian UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.ja.yaml` | Japanese UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.ko.yaml` | Korean UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.nb.yaml` | Norwegian Bokmål UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.nl.yaml` | Dutch UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.pl.yaml` | Polish UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.pt.yaml` | Portuguese UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.pt_BR.yaml` | Brazilian Portuguese UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.ro.yaml` | Romanian UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.ru.yaml` | Russian UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.sk.yaml` | Slovak UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.sv.yaml` | Swedish UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.th.yaml` | Thai UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.tr.yaml` | Turkish UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.uk.yaml` | Ukrainian UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.vi.yaml` | Vietnamese UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.zh_CN.yaml` | Simplified Chinese UI strings | FR-I18N-001 |
| `Resources/translations/NowoDashboardMenuBundle.zh_TW.yaml` | Traditional Chinese UI strings | FR-I18N-001 |

## Frontend menu template

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/menu.html.twig` | Public menu tree renderer | FR-TWIG-003 |

## Dashboard Twig views

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/dashboard/layout.html.twig` | Dashboard base layout | FR-DASH-003 |
| `Resources/views/dashboard/index.html.twig` | Menu list | FR-DASH-003 |
| `Resources/views/dashboard/show.html.twig` | Menu detail & items table | FR-DASH-003 |
| `Resources/views/dashboard/show_items_reorder.html.twig` | Drag-and-drop reorder page | FR-DASH-003 |
| `Resources/views/dashboard/menu_form.html.twig` | Menu form page | FR-DASH-003 |
| `Resources/views/dashboard/item_form.html.twig` | Item form page | FR-DASH-003 |
| `Resources/views/dashboard/copy_menu.html.twig` | Copy menu page | FR-DASH-003 |
| `Resources/views/dashboard/import.html.twig` | Import page | FR-DASH-003 |
| `Resources/views/dashboard/_menu_form_partial.html.twig` | Menu form modal partial | FR-DASH-003 |
| `Resources/views/dashboard/_item_form_partial.html.twig` | Item form modal partial | FR-DASH-003 |
| `Resources/views/dashboard/_item_form_live_partial.html.twig` | Live item form partial | FR-DASH-003, FR-LIVE-002 |
| `Resources/views/dashboard/_copy_menu_partial.html.twig` | Copy menu modal partial | FR-DASH-003 |
| `Resources/views/dashboard/_import_partial.html.twig` | Import modal partial | FR-DASH-003 |
| `Resources/views/dashboard/_menu_dashboard_modals.html.twig` | Shared modals | FR-DASH-003 |
| `Resources/views/dashboard/_sortable_tree_macro.html.twig` | Reorder tree macro | FR-DASH-003 |
| `Resources/views/dashboard/_dashboard_item_icon.html.twig` | Dashboard item icon partial | FR-DASH-003 |

## Live Component template

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/components/ItemFormLiveComponent.html.twig` | Live Component item form | FR-LIVE-002 |

## Web Profiler views

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/Collector/dashboard_menu.html.twig` | Profiler panel UI | FR-PROF-003 |
| `Resources/views/Collector/icon.svg` | Toolbar icon glyph | FR-PROF-003 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle & DI | 8 | 8 |
| Attributes | 2 | 2 |
| CLI | 2 | 2 |
| API controller | 1 | 1 |
| Dashboard controllers | 3 | 3 |
| DataCollector & query profiling | 7 | 7 |
| Entities & persistence | 6 | 6 |
| Event subscribers | 1 | 1 |
| Forms | 10 | 10 |
| Live Component (PHP) | 1 | 1 |
| Menu resolution services | 8 | 8 |
| Permissions & link resolvers | 7 | 7 |
| Twig PHP | 1 | 1 |
| Util | 1 | 1 |
| Symfony config | 5 | 5 |
| TypeScript sources | 3 | 3 |
| Built JavaScript | 2 | 2 |
| Translations | 31 | 31 |
| Frontend menu template | 1 | 1 |
| Dashboard Twig views | 16 | 16 |
| Live Component template | 1 | 1 |
| Web Profiler views | 2 | 2 |
| **Total production sources** | **119** | **119** |

Built JavaScript is documented as published output of TypeScript sources; maintainers rebuild when TS changes.
