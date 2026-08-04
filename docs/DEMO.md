# Demo Projects

## Table of contents

- [Quick start with Docker](#quick-start-with-docker)
- [What the demo includes](#what-the-demo-includes)
- [Running demo tests](#running-demo-tests)
- [Release verification](#release-verification)

The bundle ships one in-repo demo application:

- **Symfony 8 Demo**: `demo/symfony8/` (port configurable via `PORT` in `.env`, default **8011**)

The package still supports Symfony **7.4+** and **8** via Composer; only the Docker demo is Symfony 8.

**Docker stack:** The demo uses **FrankenPHP** with **Caddy**. FrankenPHP classic vs worker is selected with **`FRANKENPHP_MODE`** (default **`worker`**; set **`classic`** for hot-reload) — independent of `APP_ENV`. See [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md). HTTP is served on port 80 inside the container; the host port is mapped via `docker-compose` (e.g. `8011:80`).

## Quick start with Docker

From the **bundle root**:

```bash
make -C demo/symfony8 up
make -C demo/symfony8 install
# Open http://localhost:8011 (or port from demo/symfony8/.env)
```

Or from the `demo/` directory:

```bash
cd demo
make up-symfony8
make install-symfony8
# or: make -C symfony8 setup
```

`install` (or `setup`) runs `composer install`, creates the database, runs migrations and loads fixtures (menus and menu items). After that you can:

- Open the home page to see the rendered menus (sidebar, context resolution examples).
- Open `/admin/menus` to use the dashboard (list, create, edit, copy menu, manage items).

## What the demo includes

- Independent `docker-compose.yml` and Makefile (`up`, `down`, `install`, `setup`, `test`, `test-coverage`, `update-bundle`, `verify`, etc.).
- **FrankenPHP** with Caddy (HTTP on port 80 in container; default **`FRANKENPHP_MODE=worker`**; use **`classic`** for Caddyfile.dev / no worker).
- Data fixtures: menus (e.g. `sidebar`, `footer`) and multilingual menu items; examples of **context resolution** (same code, different JSON context).
- Web Profiler (Symfony debug toolbar) and translations for the demo UI.

## Running demo tests

From the demo directory:

```bash
cd demo/symfony8
make test
make test-coverage
```

Or from `demo/`: `make test-symfony8` (or `make test DEMO=symfony8`).

## Release verification

From the bundle root, `make release-check` runs root QA and then `make -C demo release-verify`, which starts the demo, runs its `verify` target (e.g. HTTP health check), and stops it. This ensures the demo starts and responds correctly before a release.
