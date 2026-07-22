# Demo applications with FrankenPHP (development and production)

This document describes how the bundle's demo applications run under **FrankenPHP** in Docker, and how to reproduce **development** (no cache, changes visible on refresh) and **production** (worker mode, cache enabled) configurations. The same approach can be used in other Symfony bundles or applications that ship a FrankenPHP-based demo.

## Contents

- [Overview](#overview)
- [What the demos include](#what-the-demos-include)
- [Development configuration](#development-configuration)
- [Production configuration](#production-configuration)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Reproducing in another bundle](#reproducing-in-another-bundle)
- [Troubleshooting](#troubleshooting)

---

## Overview

**The `demo/` folder is not shipped when the bundle is installed** (e.g. via `composer require nowo-tech/dashboard-menu-bundle`). It is excluded from the Composer package (via `archive.exclude` in the bundle's `composer.json`). The demo applications exist only in the bundle's source repository and are intended for development, testing, and documentation. To run or modify the demos, use a clone of the bundle repository.

The demos use:

- **FrankenPHP** (Caddy + PHP) in a single container.
- **Docker Compose** with the app and the parent bundle mounted as volumes (`../..` → `/var/dashboard-menu-bundle`).
- **Two Caddyfiles**: `Caddyfile` (**worker**) and `Caddyfile.dev` (**classic** / no worker).
- An **entrypoint** (`docker/entrypoint.sh`) that selects classic vs worker from **`FRANKENPHP_MODE`** (`classic` \| `worker`, default **`worker`** in `.env.example`). Independent of `APP_ENV`.

There are demos for **Symfony 7** and **Symfony 8** (e.g. **demo/symfony7**, **demo/symfony8**). Each has its own Dockerfile, docker-compose.yml and Makefile. From the bundle root you run e.g. `make -C demo/symfony8 up` (see the demo's README for the URL and port).

Compose demos run with `APP_ENV=dev` and default **`FRANKENPHP_MODE=worker`** so the worker Caddyfile is exercised while Symfony stays in debug mode. Set `FRANKENPHP_MODE=classic` for hot-reload-friendly classic PHP.

| Aspect | Development (`APP_ENV=dev`) | Production (`APP_ENV=prod`) |
|--------|-----------------------------|------------------------------|
| FrankenPHP | Controlled by **`FRANKENPHP_MODE`** (see below), not by `APP_ENV` | Same |
| Twig cache | **Off** (`config/packages/dev/twig.yaml`) | **On** (default) |
| OPcache revalidation | Every request (`docker/php-dev.ini`) | Default (e.g. 2 seconds) |
| HTTP cache headers | `no-store`, `no-cache` (in `Caddyfile.dev` / classic) | Omitted or cache-friendly |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | `prod` / `0` |

**Ports:** Each demo uses `PORT` from its `.env` (default **8010** for symfony7, **8011** for symfony8). To run multiple demos at once, set a different `PORT` per demo.

---

## What the demos include

The demo applications are configured for **local development and debugging**:

- **Symfony Web Profiler** and **Debug bundle** — enabled in `dev` and `test` environments.
- **Dashboard Menu Bundle** (`Nowo\DashboardMenuBundle\NowoDashboardMenuBundle`) — the bundle under test; enabled in the demos.

Example `config/bundles.php` (aligned with **demo/symfony8**):

```php
<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class            => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class              => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class             => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class     => ['dev' => true, 'test' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                      => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                    => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class        => ['dev' => true, 'test' => true],
    Pentatrion\ViteBundle\PentatrionViteBundle::class                => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class                  => ['all' => true],
    Nowo\DashboardMenuBundle\NowoDashboardMenuBundle::class          => ['all' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class          => ['dev' => true, 'test' => true],
    Symfony\UX\Icons\UXIconsBundle::class                            => ['all' => true],
    Nowo\IconSelectorBundle\NowoIconSelectorBundle::class            => ['all' => true],
    Symfony\UX\Autocomplete\AutocompleteBundle::class                => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class              => ['all' => true],
    Symfony\UX\LiveComponent\LiveComponentBundle::class              => ['all' => true],
];
```

In **production** (`APP_ENV=prod`), only bundles registered for `all` or `prod` are loaded.

---

## Development configuration

Goal: Symfony stays in `APP_ENV=dev` (profiler, Twig cache off, OPcache revalidate). FrankenPHP classic vs worker is separate — use **`FRANKENPHP_MODE=classic`** when you want every PHP/Twig change visible on refresh without long-lived workers.

### 1. Caddyfile (classic)

The classic Caddyfile is **docker/frankenphp/Caddyfile.dev** in each demo. It uses plain `php_server` (no worker) and cache-busting headers. The entrypoint copies it over `/etc/frankenphp/Caddyfile` when **`FRANKENPHP_MODE=classic`**. Mount it in docker-compose so you can edit it without rebuilding.

### 2. PHP configuration (development)

The demos include **docker/php-dev.ini** with `opcache.revalidate_freq=0`. Mount it in docker-compose: `./docker/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini:ro`.

### 3. Twig configuration (development)

The demos use **config/packages/dev/twig.yaml** with `twig.cache: false` so template changes are visible on refresh (especially useful with classic mode).

### 4. Docker Compose (development)

Each demo's **docker-compose.yml** sets `APP_ENV=dev`, `APP_DEBUG=1`, and **`FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}`**, and mounts the app, the bundle (`../..:/var/dashboard-menu-bundle`), `docker/frankenphp/Caddyfile.dev`, and `docker/php-dev.ini`.

### 5. Start the demo (development)

From the bundle root: `make -C demo/symfony8 up` (or `make -C demo/symfony7 up`). Or from the demo directory: `make up`.

---

## Production configuration

Use the worker Caddyfile (`FRANKENPHP_MODE=worker`, the default). Set `APP_ENV=prod` and `APP_DEBUG=0`. Do not mount `php-dev.ini`. See [TwigInspectorBundle DEMO-FRANKENPHP](https://github.com/nowo-tech/TwigInspectorBundle/blob/main/docs/DEMO-FRANKENPHP.md) for the full production Caddyfile and steps.

---

## Switching classic vs worker (`FRANKENPHP_MODE`)

Demos select the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`, hot-reload friendly) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.

---

## Reproducing in another bundle

See [TwigInspectorBundle DEMO-FRANKENPHP](https://github.com/nowo-tech/TwigInspectorBundle/blob/main/docs/DEMO-FRANKENPHP.md) section "Reproducing in another bundle" for the full checklist. Prefer selecting classic vs worker with **`FRANKENPHP_MODE`** (not `APP_ENV`) so debug Symfony and worker FrankenPHP can be combined.

---

## Troubleshooting

- **Changes not visible:** Set `FRANKENPHP_MODE=classic` (Caddyfile.dev has no `worker`), ensure `config/packages/dev/twig.yaml` and `docker/php-dev.ini` are in place, recreate the container (`docker compose up -d`), hard-refresh the browser.
- **Web Profiler not visible:** Check `APP_ENV=dev` and `APP_DEBUG=1`, and that WebProfilerBundle is enabled for `dev` in bundles.php.
- **Demo times out:** Check port is free, container logs (`docker-compose logs php`), and required env vars (e.g. APP_SECRET). For DashboardMenuBundle demos, ensure MySQL is healthy.
