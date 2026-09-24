# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/dashboard-menu-bundle` (`symfony-bundle`) |
| Audited revision | `v2.1.13` (worker B remediation) |
| Audit date | 2026-09-24 |
| Method | Manual review of every service registered by the bundle under `src/` (services, repositories, Twig extension, controllers, Doctrine listeners, event subscriber, data collector, DBAL middleware, Live Component, form types, commands), `src/Resources/config/*.yaml`, the DI extension and compiler passes |
| Remediation (2026-09-24) | W-01…W-06 addressed on top of `v2.1.12`: request-scoped href memo (`WeakMap<Request, …>`, no memo for id-less items), `DashboardMenuWorkerStateSubscriber` resets menu / version / href memos and both dev collectors at every main request, and recovers a closed entity manager. Regression tests simulate consecutive requests without `reset()`. |
| **Verdict** | ✅ **100% bundle-owned state is viable under scenario B** (`reset_kernel: false` / no `services_resetter`). Residual **host** responsibility only: clearing the Doctrine identity map between requests (W-04) and using a shared cache pool across workers. |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `MenuUrlResolver::$hrefMemo` is a `WeakMap` keyed by the current `Request` and is also cleared at every main request; `MenuRepository::$byCodeContext` and `MenuTreeCacheInvalidator::$versionMemo` are cleared at every main request by `DashboardMenuWorkerStateSubscriber`; all other services are `readonly` or have no properties |
| Static properties / `static` locals | ✅ | None. Only pure static helpers (`Menu::canonicalContextKey()`, `ParentRelationCycleDetector::findFirstCycle()`, `MenuTreeCacheInvalidator::versionKey()`) |
| `ResetInterface` / `kernel.reset` coverage | ✅ | Every mutable service implements `ResetInterface` (scenario A) and is additionally reset per main request by a bundle-owned subscriber (scenario B) |
| Request / user / locale captured in services | ✅ | `RequestStack` is read at call time; nothing is captured in constructors. Request-derived hrefs are memoized per request object only |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | Only `$_SERVER` / `getenv` in `GoogleSyncTranslationsCommand` (CLI only) |
| Doctrine / EntityManager | ✅ (host clears identity map) | `Menu` memo lives for one request; a closed manager is reset at the next main request; the tree path reads raw DBAL rows (fresh); identity-map clearing stays the application's job |
| Output, headers, `exit`, shutdown functions | ✅ | Only `echo` inside `StreamedResponse` callbacks (export), which is correct |
| Resources (files, sockets, cURL) held open | ✅ | None in the HTTP path |
| Memory growth across requests | ✅ | Memos keyed by menu codes / context sets from the public JSON API are bounded by one request (URL length); the dev collector is emptied at every main request |
| Blocking I/O and timeouts | ✅ | HTTP path only uses DBAL and the PSR-6 pool; the Google Translate command (CLI) sets a 20 s timeout |
| Third-party static state | ✅ | FormKit `FormOptionsTrait` keeps a bound builder but restores it in `finally`; UX Live Component services are non-shared |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` runs `php_server` with a `worker` block (`file /app/public/index.php`, `watch`). `Caddyfile.dev` uses classic mode.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `Service\MenuUrlResolver` | yes (public) | `$hrefMemo` (`WeakMap<Request, …>`, items with id only); `ResetInterface` via autoconfigure; reset per main request | ✅ | ✅ |
| `Repository\MenuRepository` | yes (public) | `$byCodeContext` (memoized `Menu` entities / `null`); explicit `kernel.reset` tag; reset per main request | ✅ | ✅ |
| `Service\MenuTreeCacheInvalidator` | yes | `$versionMemo` (cache version per menu code); `ResetInterface` via autoconfigure; reset per main request | ✅ | ✅ |
| `EventSubscriber\DashboardMenuWorkerStateSubscriber` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\MenuTreeLoader` | yes (public) | none (`final readonly`) | ✅ | ✅ |
| `Service\MenuConfigResolver` | yes | none (`final readonly`), reads the request-scoped `MenuRepository` memo | ✅ | ✅ |
| `Service\CurrentRouteTreeDecorator` | yes | none (`final readonly`) | ✅ | ✅ |
| `Service\MenuLocaleResolver`, `MenuIconNameResolver`, `DefaultMenuCodeResolver` | yes | none | ✅ | ✅ |
| `Service\AllowAllMenuPermissionChecker`, `PermissionKeyAwareMenuPermissionChecker` | yes | none | ✅ | ✅ |
| `Service\ImportExportRateLimiter` | yes | none (`final readonly`; state lives in the PSR-6 pool) | ✅ | ✅ |
| `Service\MenuExporter`, `MenuImporter` | yes | none (`final readonly`) | ✅ | ✅ (closed manager recovered) |
| `Repository\MenuItemRepository` | yes (public) | none | ✅ | ✅ |
| `Twig\MenuExtension` | yes | none (`readonly` promoted properties; globals are compile-time config) | ✅ | ✅ |
| `Controller\Api\MenuApiController` | yes (public) | none (`final readonly`) | ✅ | ✅ |
| `Controller\Dashboard\MenuDashboardController` | yes (public) | none (`readonly` promoted properties) | ✅ | ✅ (closed manager recovered) |
| `EventSubscriber\DashboardAccessSubscriber`, `MenuCacheInvalidationSubscriber`, `TablePrefixSubscriber` | yes | none (`final readonly`) | ✅ | ✅ |
| `Security\ConfigurableDashboardMenuAccessChecker`, `AllowAllDashboardMenuAccessChecker` | yes | none; `isGranted()` is called per request | ✅ | ✅ |
| `LiveComponent\ItemFormLiveComponent` | **no** (TwigComponentPass sets `shared: false`) | LiveProps, per instance | ✅ | ✅ |
| 11 form types (`Form\*Type`) + `JsonToArrayTransformer` | yes | only `readonly` config and FormKit trait fields (see Info) | ✅ | ✅ |
| `DataCollector\DashboardMenuDataCollector` (dev only) | yes | `$menuLoads`, `$permissionChecks`, `$menuRelatedQueryCount`; reset by the profiler and per main request | ✅ | ✅ |
| `DataCollector\MenuQueryCounter` (dev only) | yes | `$count`, `$segmentStart`, `$wrapped`; explicit `kernel.reset` tag; reset per main request | ✅ | ✅ |
| `DataCollector\MenuQueryCountMiddleware` + DBAL wrappers (dev only) | yes | none (`readonly`) | ✅ | ✅ |
| `Command\GenerateDashboardMenuMigrationCommand`, `GoogleSyncTranslationsCommand` | CLI only | n/a | n/a | n/a |

Entities (`Menu`, `MenuItem`) and enums are value holders created per request by Doctrine or by `MenuTreeLoader`; the `MenuRepository` memo keeps them for one request at most (W-02).

## Findings

### W-01 — Href memo stores request-dependent URLs, keyed only by item id (Medium)

- **Where:** `src/Service/MenuUrlResolver.php:31-32` (`$hrefMemo`), `:46-57` (`getHref()` returns the memo before resolving), `:242-247` (key = item id or `'o' . spl_object_id($item)` + reference type). Reset by `reset()` at `:59-62`; the `kernel.reset` tag comes only from autoconfiguration (`_defaults: autoconfigure: true` in `src/Resources/config/services.yaml:4`, definition at `:97-101`).
- **Worker impact:** the resolved href depends on the current request: missing path variables are copied from the main request's `_route_params` (`:88-105`), `_locale` is injected from the request (`:113-115`), `ABSOLUTE_URL` uses the request host (`:204-210`), and `itemType: service` items call the app's `MenuLinkResolverInterface::resolveHref()` with the request (`:182-185`). The cache key contains none of this.
  - Scenario A: safe, the memo is cleared after every request.
  - Scenario B: request N+1 gets hrefs computed for request N — wrong locale, wrong route parameters (for example another user's `/partner/{id}` value), wrong host, and service-resolver URLs that may be user-specific. Dynamic child links (no id) use `spl_object_id()`, which PHP reuses once objects are freed, so across requests a dynamic link can receive the href of an unrelated dynamic link from a previous user. This is a cross-user data leak, limited to scenario B.
- **Recommendation:** keep `services_resetter` enabled. To make the service B-safe, either drop the memo or scope it to the current main request (for example store the `Request` object id or `spl_object_id` alongside the memo and clear it when it changes), and never memoize items without an id.
- **Status:** Resolved — `src/Service/MenuUrlResolver.php` stores the memo in a `WeakMap<Request, array<string, string>>` keyed by `RequestStack::getCurrentRequest()` (hrefs depend on the main and the current request; the main request is fixed for the lifetime of the current one). A new request starts with an empty memo and the entry is freed with its request object; `spl_object_id()` of the request was not used because ids are reused after garbage collection. Items without id and calls without a request (CLI) are never memoized. `DashboardMenuWorkerStateSubscriber` also calls `MenuUrlResolver::reset()` at every main request. Tests: `MenuUrlResolverTest::testHrefMemoDoesNotLeakRequestParamsOrLocaleIntoTheNextRequestWithoutReset`, `::testItemsWithoutIdAndCallsWithoutRequestAreNeverMemoized`; `DashboardMenuWorkerStateSubscriberTest::testMainRequestResetsHrefMemoAndDevQueryCounter`.

### W-02 — Menu and cache-version memos go stale across requests (Medium)

- **Where:** `src/Repository/MenuRepository.php:31-32`, `:56-66` (`findOneByCodeAndContext()` memoizes the `Menu` entity or `null`), reset at `:68-71`, tagged in `src/Resources/config/services.yaml:7-11`. `src/Service/MenuTreeCacheInvalidator.php:28-29`, `:50-65` (`getVersionForMenuCode()` memoizes the version read from the pool), reset at `:67-70`.
- **Worker impact:** `MenuCacheInvalidationSubscriber` (`src/EventSubscriber/MenuCacheInvalidationSubscriber.php:66-80`) clears these memos only in the worker that performs the write.
  - Scenario A: safe; both memos start empty on every request.
  - Scenario B: a worker keeps returning the `Menu` it saw first (or `null` for a code that did not exist yet) from `MenuConfigResolver::getConfig()` when no menu is passed (`src/Service/MenuConfigResolver.php:67-68`, used by the `dashboard_menu_config()` Twig function in `src/Twig/MenuExtension.php:111-115`), from the legacy tree path (`src/Service/MenuTreeLoader.php:123-126`) and from `MenuImporter` (`src/Service/MenuImporter.php:129`). The held entity may also be detached or belong to an EntityManager that was replaced. The version memo ignores bumps done by other workers, so the tree cache key does not change; the stale tree is served until the cache item expires (`cache.ttl`, default 60 s).
- **Recommendation:** rely on `kernel.reset` (scenario A). For B-safety the repository memo should store ids or be scoped to the main request, and the version memo should be read once per request instead of once per worker.
- **Status:** Resolved — new `src/EventSubscriber/DashboardMenuWorkerStateSubscriber.php` (`KernelEvents::REQUEST`, priority 4096, main request only; registered in `services.yaml`) calls `MenuRepository::reset()` and `MenuTreeCacheInvalidator::reset()`. The version is therefore read once per request from the shared PSR-6 pool, so a bump made by another worker changes the tree cache key on the next request (requires a pool shared by all workers, e.g. Redis; APCu/array pools are per process). A `Menu` held in the memo lives for one request. Under B the entity returned by `findOneBy()` can still carry stale fields from a long-lived identity map; the tree/permission path does not depend on it (it hydrates from raw DBAL rows or the versioned PSR-6 entry), and clearing the identity map stays the application's job (see W-04). `HINT_REFRESH` was not added to `findOneByCodeAndContext()` because `MenuImporter` flushes several times while holding that entity and a refresh between flushes could overwrite pending changes. Test: `DashboardMenuWorkerStateSubscriberTest::testSecondRequestSeesMenusAndCacheVersionsWrittenByAnotherWorkerWithoutReset`.

### W-03 — Public JSON API can grow the memos without limit (Medium)

- **Where:** `src/Resources/config/routes.yaml` (`GET /api/menu/{code}`, no auth by default), `src/Controller/Api/MenuApiController.php:34-41` and `:54-69` (`_context_sets` is decoded from the query string with no size limit). `MenuTreeLoader::loadTree()` calls `getVersionForMenuCode($menuCode)` for every code (`src/Service/MenuTreeLoader.php:72`) and falls back to `findForCodeWithContextSets()` when the menu is not found (`:94-97`, `:125`), which memoizes one `null` entry per code/context pair.
- **Worker impact:** Scenario A: growth is limited to one request (bounded by URL length). Scenario B: every request with a new code or context set adds entries to `MenuRepository::$byCodeContext` and `MenuTreeCacheInvalidator::$versionMemo` that are never removed; an unauthenticated client can grow worker memory until it is restarted.
- **Recommendation:** keep the resetter; if the API is exposed publicly, protect or rate-limit it, and set FrankenPHP `max_requests` (or `FRANKENPHP_LOOP_MAX`) as a safety net. A code fix would cap the memo size or skip memoizing misses.
- **Status:** Resolved — both memos are emptied at every main request (W-02), so their size is bounded by what a single request can ask for (one code plus the context sets that fit in the URL). No cross-request growth remains. Protecting / rate-limiting the public API is still recommended for load reasons, not for memory.

### W-04 — Non-transactional flushes rely on Doctrine's resetter after a failure (Medium, scenario B only)

- **Where:** `src/Service/MenuImporter.php:144-160` (up to five `flush()` calls per imported menu, no transaction), `src/Controller/Dashboard/MenuDashboardController.php` (`flush()` at lines 227, 355, 478, 689, 732, 771, 806, 1027, 1068, 1136, 1141, 1277, 1284, 1324), `src/LiveComponent/ItemFormLiveComponent.php:337`.
- **Worker impact:** a failed `flush()` (constraint violation, deadlock) closes the EntityManager. Scenario A: DoctrineBundle's `kernel.reset` hook resets the manager, so the next request is fine. Scenario B: the closed EntityManager stays in the container and every later request that touches Doctrine fails ("EntityManager is closed"). This is the standard Doctrine behaviour, not something the bundle adds, but the bundle has no protection against it. A failed import can also leave a menu half-imported.
- **Recommendation:** run with the resetter (scenario A). Wrapping `MenuImporter::importOne()` in `wrapInTransaction()` would also avoid partial imports.
- **Status:** Resolved (closed manager) / Accepted (identity map, partial imports) — `DashboardMenuWorkerStateSubscriber` resets the manager of `Menu` by name when it is closed at the start of the next main request (DoctrineBundle resets the lazy manager service in place, so injected `EntityManagerInterface` / repositories recover); it never clears an open manager. Clearing the identity map between requests remains the **application's responsibility** under scenario B. Partial imports after a failed flush are not worker-specific (they also happen under PHP-FPM) and are accepted for now; wrapping `importOne()` in a transaction is left as a separate change. Tests: `DashboardMenuWorkerStateSubscriberTest::testClosedEntityManagerIsResetByNameOnTheNextMainRequest`, `::testOpenUnknownOrUnnamedManagersAreLeftUntouched`.

### W-05 — Dev data collector accumulates when the profiler is not reset (Low)

- **Where:** `src/DataCollector/DashboardMenuDataCollector.php:31`, `:47`, `:92-102`, `:208-230`; reset at `:160-168`. Registered only in `dev` (`src/Resources/config/services_dev.yaml`, loaded from `src/DependencyInjection/DashboardMenuExtension.php:239-251`).
- **Worker impact:** Scenario A: the profiler resets the collector after each request. Scenario B: menu loads and permission checks from every request pile up in the profile and in memory. Dev environment only.
- **Recommendation:** none for production. In dev worker mode keep the resetter on or restart workers often (`watch` already restarts on file changes).
- **Status:** Resolved — the subscriber receives the optional `@?` references to `DashboardMenuDataCollector` and `MenuQueryCounter` and calls `reset()` on both at every main request. Tests: `DashboardMenuWorkerStateSubscriberTest::testDevCollectorStartsEmptyOnEveryMainRequest`, `::testMainRequestResetsHrefMemoAndDevQueryCounter`.

### W-06 — `spl_object_id()` key collision inside a single request (Low, theoretical)

- **Where:** `src/Service/MenuUrlResolver.php:242-247`, dynamic children created in `src/Service/MenuTreeLoader.php:514` (`MenuItem::createDynamicChildLink()`, no id, href in `runtimeHref`).
- **Worker impact:** if a tree with dynamic children is freed and a second tree is built in the same request, a new dynamic item can get a recycled object id and receive the memoized href of the old one. In normal Twig usage the tree stays referenced until the template finishes, so this is unlikely; it was not reproduced. Affects both scenarios and classic mode.
- **Recommendation:** do not memoize items without an id (same fix as W-01).
- **Status:** Resolved — items without id are never memoized (W-01); the `spl_object_id()` key was removed.

Info / good patterns:

- `MenuTreeLoader` caches only the raw DB rows in the PSR-6 pool (`src/Service/MenuTreeLoader.php:99-104`); permission checks, dynamic service children and current-route decoration run on every request, so the shared cache cannot leak one user's visibility to another.
- `DashboardAccessSubscriber` and `ConfigurableDashboardMenuAccessChecker` evaluate `isGranted()` per request; no security decision is cached.
- `MenuQueryCounter::reset()` clears `$wrapped`, and `wrapConnection()` detects an already installed `ChainedSqlLogger`, so the logger is not wrapped twice after a reset (`src/DataCollector/MenuQueryCounter.php:51-82`).
- FormKit's `FormOptionsTrait::withBuilder()` (vendor) restores the bound builder in a `finally` block, so shared form types do not keep a builder after `buildForm()`.

## Usage recommendations in worker mode

- Bundle-owned state no longer depends on `services_resetter`; keeping it active (default Symfony) is still recommended for framework and Doctrine state. With FrankenPHP `reset_kernel: false` (no resetter), this bundle remains correct for its own memos and recovers a closed menu EntityManager.
- Under scenario B, clear the Doctrine identity map between requests in the application (the bundle only resets a *closed* manager).
- If you expose `/api/menu/{code}`, protect it with a firewall or rate limiter (load protection).
- Custom `MenuLinkResolverInterface`, `MenuPermissionCheckerInterface`, `MenuCurrentMatcherInterface` and `MenuCodeResolverInterface` services must stay stateless, or implement `ResetInterface`. Do not store the request, the user or the resolved hrefs in properties.
- A multi-worker setup should use a shared cache pool (Redis, APCu is per-process) for `cache.pool`, so version bumps from one worker are seen by the others.
- Do not extend `MenuRepository` with extra memos without clearing them in `reset()` (called per main request by `DashboardMenuWorkerStateSubscriber`).

## Re-audit triggers

Re-run this audit when a change adds: a new property or memo to any service (especially `MenuUrlResolver`, `MenuRepository`, `MenuTreeCacheInvalidator`, `MenuTreeLoader`), a new event listener or Doctrine listener that buffers data, caching of permission results or resolved hrefs in the PSR-6 pool, removal of a `ResetInterface` implementation or `kernel.reset` tag, or new public API endpoints.
