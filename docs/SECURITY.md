# Security

Report vulnerabilities to the maintainers privately. Do not open public issues for security issues.

## Dashboard in sensitive environments

For production or sensitive setups, configure:

- **Access control:** Set `nowo_dashboard_menu.dashboard.required_role` (e.g. `ROLE_ADMIN`) so that all dashboard routes require that role. This uses Symfony SecurityBundle; ensure your firewall and user provider are configured. Alternatively, protect the dashboard path in your app’s `security.yaml` with `access_control` and leave `required_role` unset.

- **Rate limiting:** Set `import_export_rate_limit` under `dashboard` with `limit` and `interval` (e.g. `{ limit: 10, interval: 60 }`) to limit how often each user or IP can call import and export. Limits are applied per authenticated user (when SecurityBundle is available) or per client IP. When exceeded, the app returns HTTP 429 (Too Many Requests).

- **Import size:** `import_max_bytes` (default 2 MiB) caps the size of JSON import uploads to reduce DoS risk.

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

