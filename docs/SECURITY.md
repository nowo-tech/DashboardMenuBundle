# Security

Report vulnerabilities to the maintainers privately. Do not open public issues for security issues.

## Dashboard in sensitive environments

For production or sensitive setups, configure:

- **Access control (REQ-UI-002):** Set `nowo_dashboard_menu.security.access_roles` (default `[ROLE_ADMIN]`) so dashboard CRUD requires an authorized user. Optionally provide `security.access_checker` (service implementing `DashboardMenuAccessCheckerInterface`). Also lock `dashboard.path_prefix` in the host `security.yaml` with `access_control`. Keep `security.allow_unauthenticated: false` in production (demos may set `true`).

- **Legacy:** `dashboard.required_role: ROLE_X` still maps to `security.access_roles: [ROLE_X]` but is deprecated.

- **Rate limiting:** Set `import_export_rate_limit` under `dashboard` with `limit` and `interval` (e.g. `{ limit: 10, interval: 60 }`) to limit how often each user or IP can call import and export. Limits are applied per authenticated user (when SecurityBundle is available) or per client IP. When exceeded, the app returns HTTP 429 (Too Many Requests) and logs a warning without secrets.

- **Import size:** `import_max_bytes` (default 2 MiB) caps the size of JSON import uploads to reduce DoS risk.

- **Logging:** Operational warnings (e.g. rate-limit exceeded) use `Psr\Log\LoggerInterface` with structured context (`bundle`, `action`). Do not log tokens, passwords, or session identifiers.

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

