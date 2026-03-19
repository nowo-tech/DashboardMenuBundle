# Dashboard Menu Bundle Demo - Symfony 7

Demo app for `nowo-tech/dashboard-menu-bundle` with FrankenPHP (worker mode) and MySQL.

## Run

```bash
make up
make setup
```

Open http://localhost:8010. Menu is rendered in the layout; JSON API: http://localhost:8010/api/menu/sidebar.

## Frontend (TypeScript + Vite)

Assets are in TypeScript (like the Symfony 8 demo). Entry: `assets/app.ts`; Stimulus controllers in `assets/controllers/*_controller.ts`.

If `assets/` is owned by root (e.g. after Docker), fix and apply TS assets:

```bash
sudo chown -R $(whoami) assets && make ts-assets
```

Then `pnpm install` and `pnpm build` (or run from inside the container).

## Commands

- `make up` - Start containers (FrankenPHP + MySQL)
- `make down` - Stop containers
- `make setup` - Install deps, create DB, load fixtures
- `make ts-assets` - Copy TS entry and bootstrap from `ts-assets-template/` to `assets/`
- `make update-bundle` - Update bundle from path repo
- `make verify` - Lint container and basic health check
