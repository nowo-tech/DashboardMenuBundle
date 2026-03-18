# Security

Report vulnerabilities to the maintainers privately. Do not open public issues for security issues.

## Dashboard in sensitive environments

For production or sensitive setups, configure:

- **Access control:** Set `nowo_dashboard_menu.dashboard.required_role` (e.g. `ROLE_ADMIN`) so that all dashboard routes require that role. This uses Symfony SecurityBundle; ensure your firewall and user provider are configured. Alternatively, protect the dashboard path in your app’s `security.yaml` with `access_control` and leave `required_role` unset.

- **Rate limiting:** Set `import_export_rate_limit` under `dashboard` with `limit` and `interval` (e.g. `{ limit: 10, interval: 60 }`) to limit how often each user or IP can call import and export. Limits are applied per authenticated user (when SecurityBundle is available) or per client IP. When exceeded, the app returns HTTP 429 (Too Many Requests).

- **Import size:** `import_max_bytes` (default 2 MiB) caps the size of JSON import uploads to reduce DoS risk.
