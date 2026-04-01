# Dashboard Menu Bundle Demo - Symfony 8

Demo app for `nowo-tech/dashboard-menu-bundle` with FrankenPHP (worker mode) and MySQL.

## Run

```bash
make up
make setup
```

Open http://localhost:8011. JSON API: http://localhost:8011/api/menu/sidebar.

After pulling bundle changes, reload demo data: `make setup` (or `doctrine:fixtures:load`) so `MenuFixtures` matches the current tree rules (sections need at least one visible child; nested sections demo under Settings).

See `config/packages/nowo_dashboard_menu.yaml` for `cache.ttl`, `dashboard.position_step`, and other bundle options.
