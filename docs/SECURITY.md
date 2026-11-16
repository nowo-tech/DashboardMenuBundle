# Security

Report vulnerabilities to the maintainers privately. Do not open public issues for security issues.

## Table of contents

- [Scope and attack surface](#scope-and-attack-surface)
- [Threat model](#threat-model)
- [Secrets](#secrets)
- [Logging policy](#logging-policy)
- [Dependencies](#dependencies)
- [Dashboard in sensitive environments](#dashboard-in-sensitive-environments)
- [Menu JSON API](#menu-json-api-apimenucode)
- [Content Security Policy (CSP)](#content-security-policy-csp)
- [AI security audit (REQ-SEC-004)](#ai-security-audit-req-sec-004)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope and attack surface

| Surface | Exposure | Controls |
| --- | --- | --- |
| Admin dashboard (`dashboard.path_prefix`, default `/admin/menus`): CRUD, copy, reorder, import / export | Authenticated admins | `security.access_roles` / `access_checker` gate (fails closed), form CSRF (`CsrfProtectionPass`), `import_max_bytes`, optional `import_export_rate_limit` |
| Menu JSON API `GET /api/menu/{code}` | Public unless the host restricts it | `api.enabled`, host `access_control`, menu permission checker, bounded `_context_sets` (400 above the limit) |
| Twig functions / Live Component rendering menus | Every page that renders a menu | Menu permission checker per item, `ExternalUrlPolicy` on `externalUrl`, CSP nonces on inline blocks |
| CLI `nowo_dashboard_menu:translations:google-sync` | Operators only | Google API key from option or env, never stored |
| CLI `nowo_dashboard_menu:generate-migration` | Operators only | Writes a migration file; no network |

## Threat model

- **Unauthorised menu administration** — mitigated by the dashboard gate (default `ROLE_ADMIN`, fail-closed when misconfigured) and the `access_control` lock documented below.
- **Cross-site request forgery** on dashboard mutations — Symfony form CSRF, build fails when CSRF is unavailable.
- **Information disclosure** through the JSON API or rendered menus (admin links, route names) — host restricts the API path and assigns a real permission checker; `AllowAllMenuPermissionChecker` is for public menus only.
- **Script injection via menu data** (`externalUrl`, labels, icons) — URL scheme allow-list, Twig auto-escaping, no inline event handlers.
- **Denial of service** through large imports or repeated import / export — `import_max_bytes`, rate limiter (HTTP 429).
- **Cross-request leaks under FrankenPHP worker mode** — request-scoped memos cleared at request boundaries (see [`FRANKENPHP-WORKER-AUDIT.md`](FRANKENPHP-WORKER-AUDIT.md)).

## Secrets

- The bundle ships no secrets. The only credential it reads is the Google Translate API key of the `google-sync` command, taken from `--api-key` or `GOOGLE_TRANSLATE_API_KEY` at runtime; it is never logged, cached or persisted.
- Demo `.env.example` values are throwaway placeholders; real values belong in `.env.local` / the host secret store.

## Logging policy

| Event | Level | Context | Never logged |
| --- | --- | --- | --- |
| Import / export rate limit exceeded | `warning` | `bundle`, `action`, `limit`, `interval` | tokens, session ids, request bodies |
| Permission-context values dropped for a value-object checker | `warning` | `bundle`, `menu`, dropped key names | the dropped values |

Logging goes through the injected `Psr\Log\LoggerInterface` (no `dump` / `error_log`).

## Dependencies

- CI runs `composer audit --locked` on every push and pull request; Dependabot watches Composer, GitHub Actions and npm manifests.
- Runtime dependencies are limited to Symfony components, Doctrine, `nowo-tech/ui-kit-bundle` and `nowo-tech/form-kit-bundle`; QA tools stay in `require-dev`.

## Dashboard in sensitive environments

For production or sensitive setups, configure:

- **Access control (REQ-UI-002):** The **admin dashboard gate** already uses `nowo_dashboard_menu.security.access_roles` (default `[ROLE_ADMIN]`) so CRUD requires an authorized user. Optionally provide `security.access_checker` (service implementing `DashboardMenuAccessCheckerInterface`). Also lock `dashboard.path_prefix` in the host `security.yaml` with `access_control`. Keep `security.allow_unauthenticated: false` in production (demos may set `true`). The gate fails closed: `access_roles: []` without a custom checker denies everyone, and with `dashboard.enabled: false` the dashboard routes answer 404.
- **CSRF (fails closed):** every dashboard mutation (CRUD forms, delete / move / reindex / copy action forms, import) is validated by Symfony's form CSRF extension. `CsrfProtectionPass` makes the container refuse to compile when the dashboard is enabled without `security.csrf.token_manager` + `form.type_extension.csrf`, or with `framework.form.csrf_protection: false` — action forms declaring their own CSRF options would otherwise accept tokenless cross-site POSTs.
- **External URLs:** menu item `externalUrl` is validated in the dashboard form (`http`/`https`) and, at render and import time, restricted to `http`, `https`, `mailto`, `tel` or relative URLs (`Nowo\DashboardMenuBundle\Util\ExternalUrlPolicy`); other schemes such as `javascript:` render as `#`.

- **Menu item visibility (host must wire):** `AllowAllMenuPermissionChecker` is the default **menu-item** permission checker (what each user sees in a rendered menu tree). It does **not** weaken the admin gate above. **In production**, assign `PermissionKeyAwareMenuPermissionChecker` (or your own `MenuPermissionCheckerInterface`) on any menu that exposes sensitive links, and set permission keys on items. Flex recipe documents this requirement and keeps `security.access_roles: [ROLE_ADMIN]` under `when@prod`.

- **Legacy:** `dashboard.required_role: ROLE_X` still maps to `security.access_roles: [ROLE_X]` but is deprecated.

- **Rate limiting:** Set `import_export_rate_limit` under `dashboard` with `limit` and `interval` (e.g. `{ limit: 10, interval: 60 }`) to limit how often each user or IP can call import and export. Limits are applied per authenticated user (when SecurityBundle is available) or per client IP. When exceeded, the app returns HTTP 429 (Too Many Requests) and logs a warning without secrets.

- **Import size:** `import_max_bytes` (default 2 MiB) caps the size of JSON import uploads to reduce DoS risk.

- **Logging:** Operational warnings (e.g. rate-limit exceeded) use `Psr\Log\LoggerInterface` with structured context (`bundle`, `action`). Do not log tokens, passwords, or session identifiers.

## Menu JSON API (`/api/menu/{code}`)

The API is enabled by default and the bundle applies no access control to it: anyone who can reach it can read a
menu by code (labels, hrefs, route names). If a menu holds admin or private entries, either set `api.enabled: false`
or restrict the path in `access_control` (`{ path: ^/api/menu, roles: ROLE_USER }`), and use a menu permission
checker instead of `AllowAllMenuPermissionChecker`. The API passes the `Request` as the permission context (like
the Twig functions) and accepts at most 8 `_context_sets` of at most 16 scalar values (400 otherwise).

## Content Security Policy (CSP)

Every inline `<script>` / `<style>` rendered by the bundle templates carries `nonce="…"` taken from the request attribute **`csp_nonce`** when it is present (nothing is emitted otherwise). Your CSP listener should set it before rendering, e.g. `$request->attributes->set('csp_nonce', $nonce)`, and send the same value in `script-src 'nonce-…'` / `style-src 'nonce-…'`. External scripts (`src=…`) and JSON islands need no nonce, and templates use no inline event handlers (`onclick`, `onsubmit`, …). `tests/Unit/Templates/InlineBlocksDeclareNonceTest.php` enforces this.

`dashboard.js` copies the nonce (from its own `<script>` tag or any nonce'd element) onto the icon-selector `<script>` it injects when a modal form reloads. The dashboard layout's `tailwind` option loads the Tailwind Play CDN, which injects `<style>` without a nonce; use a prebuilt stylesheet under a strict `style-src`.

## AI security audit (REQ-SEC-004)

| Field | Value |
| ----- | ----- |
| Date | 2026-08-20 (re-audit; prior 2026-07-28) |
| Grade | **Pass (good)** — overall **Low** |
| Method | Static review of access_roles defaults, Flex access_control, item-checker residual documented as host-owned |
| Open residuals | Host must not leave AllowAll on menus with sensitive links |

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|--------|
| **SECURITY.md** | This document is current and linked from the README where applicable. |
| **`.gitignore` and `.env`** | `.env` and local env files are ignored; no committed secrets. |
| **No secrets in repo** | No API keys, passwords, or tokens in tracked files. |
| **Recipe / Flex** | Default recipe or installer templates do not ship production secrets. |
| **Input / output** | Inputs validated; outputs escaped in Twig/templates where user-controlled. |
| **Dependencies** | `composer audit` run; issues triaged. |
| **Logging** | Logs do not print secrets, tokens, or session identifiers unnecessarily. |
| **Cryptography** | If used: keys from secure config; never hardcoded. |
| **Permissions / exposure** | Routes and admin features documented; roles configured for production. |
| **Limits / DoS** | Timeouts, size limits, rate limits where applicable. |

Record confirmation in the release PR or tag notes.

