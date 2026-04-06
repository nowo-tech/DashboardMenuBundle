# Development & Testing

## Table of contents

- [Using Docker (recommended)](#using-docker-recommended)
- [Without Docker](#without-docker)
- [Testing](#testing)
- [Code quality](#code-quality)
- [Release checks](#release-checks)
- [CI/CD](#cicd)

## Using Docker (recommended)

```bash
# Start the container
make up

# Install dependencies
make install

# Run tests
make test

# Run tests with coverage
make test-coverage

# Run all QA checks (cs-check + test)
make qa
```

Targets that run inside the container (`install`, `test`, `test-coverage`, `validate-translations`, `assets`, `cs-check`, `cs-fix`, `rector`, `phpstan`, `qa`) depend on `ensure-up`: if the container is not running, it is started and Composer (and pnpm for frontend assets) are installed before the command.

## Without Docker

```bash
composer install
composer test
composer test-coverage
composer qa
```

## Testing

Tests live in `tests/`. Run them with:

```bash
composer test
composer test-coverage
```

Coverage is printed in the terminal. Automated tests are **PHPUnit-only** (PHP). TypeScript sources are built with Vite to committed JS under `src/Resources/public/js/`; use `make assets` (inside Docker) or `pnpm install && pnpm run build` on the host to rebuild them.

## Code quality

The bundle uses **PHP-CS-Fixer** (PSR-12 + Symfony canonical), **Rector** and **PHPStan**.

```bash
# Check code style
make cs-check
# or: composer cs-check

# Fix code style
make cs-fix
# or: composer cs-fix

# Rector (dry-run)
make rector-dry

# Rector (apply)
make rector

# PHPStan
make phpstan
```

## Release checks

Before tagging a release, run:

```bash
make release-check
```

This runs: `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `test-coverage`, `release-check-demos` (each demo is started, verified with an HTTP check, then stopped), and `assets` (Vite production build for dashboard and Stimulus live entry points).

## CI/CD

GitHub Actions (`.github/workflows/ci.yml`) run on push and pull requests: tests on the supported PHP and Symfony matrix, code style, and optional demo verification. See [RELEASE.md](RELEASE.md) for release and tagging steps.

For contribution guidelines, see [CONTRIBUTING.md](CONTRIBUTING.md).
