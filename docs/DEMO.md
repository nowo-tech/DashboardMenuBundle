# Demo Projects

The bundle includes two demo applications, one per supported Symfony version. Each demo has its own `docker-compose.yml` and can be run independently:

- **Symfony 7 Demo**: `demo/symfony7/` (port configurable via `PORT` in `.env`, e.g. 8010)
- **Symfony 8 Demo**: `demo/symfony8/` (port configurable via `PORT` in `.env`, e.g. 8011)

**Docker stack:** Demos use **FrankenPHP** with **Caddy** as the web server (worker mode). HTTP is served on port 80 inside the container; the host port is mapped via `docker-compose` (e.g. `8010:80` or `8011:80`). See each demo’s `Caddyfile` and `docker-compose.yml` for details.

## Quick start with Docker

From the **bundle root**:

```bash
# Symfony 7
make -C demo/symfony7 up
make -C demo/symfony7 install
# Open http://localhost:8010 (or port from demo/symfony7/.env)

# Symfony 8
make -C demo/symfony8 up
make -C demo/symfony8 install
# Open http://localhost:8011 (or port from demo/symfony8/.env)
```

Or from the `demo/` directory:

```bash
cd demo
make -C symfony7 up
make -C symfony7 install
# or: make -C symfony7 setup

make -C symfony8 up
make -C symfony8 install
# or: make -C symfony8 setup
```

Each demo’s `install` (or `setup`) will run `composer install`, create the database, run migrations and load fixtures (menus and menu items). After that you can:

- Open the home page to see the rendered menus (sidebar, context resolution examples).
- Open `/admin/menus` to use the dashboard (list, create, edit, copy menu, manage items).

## What each demo includes

- Independent `docker-compose.yml` and Makefile (`up`, `down`, `install`, `setup`, `test`, `test-coverage`, `update-bundle`, `verify`, etc.).
- **FrankenPHP** with Caddy (HTTP on port 80 in container; worker mode).
- Data fixtures: menus (e.g. `sidebar`, `footer`) and multilingual menu items; examples of **context resolution** (same code, different JSON context).
- Web Profiler (Symfony debug toolbar) and translations for the demo UI.

## Running demo tests

From the demo directory:

```bash
cd demo/symfony8
make test
make test-coverage
```

Or from `demo/`: `make -C symfony8 test` and `make -C symfony7 test`.

## Release verification

From the bundle root, `make release-check` runs root QA and then `make -C demo release-verify`, which starts each demo, runs its `verify` target (e.g. HTTP health check), and stops it. This ensures demos start and respond correctly before a release.
