# Dashboard Menu Bundle Demo - Symfony 7

Demo app for `nowo-tech/dashboard-menu-bundle` with FrankenPHP (worker mode) and MySQL.

## Run

```bash
make up
make setup
```

Open http://localhost:8010. Menu is rendered in the layout; JSON API: http://localhost:8010/api/menu/sidebar.

## Commands

- `make up` - Start containers (FrankenPHP + MySQL)
- `make down` - Stop containers
- `make setup` - Install deps, create DB, load fixtures
- `make update-bundle` - Update bundle from path repo
- `make verify` - Lint container and basic health check
