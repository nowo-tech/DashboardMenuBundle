# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` / `composer coverage-check` enforce **≥ 99%** line coverage on the PHPUnit includable `src/` set (`REQ-TEST-003`).

Published README percentage must match the latest `coverage-output.txt` / CI artifact.

## Justified exclusions

None: `phpunit.xml.dist` no longer excludes any path from the PHP coverage set (REQ-TEST-003). The dashboard controller, the Compiler passes, the repositories, the tree loader, the form types, the Google sync and migration commands, `MenuItem` and `Configuration` are all measured.

A few lines stay uncovered because they cannot be reached through the public API (defensive guards, e.g. `MenuItemRepository::findIdsInSubtreeStartingAt()` skipping a row that is not an array, `GoogleSyncTranslationsCommand` when the bundle has no translation file).

## TypeScript / JavaScript coverage gate

`pnpm run test:coverage` (Vitest + v8, `make test-ts`, CI job `frontend-tests`) enforces per-file thresholds in `vitest.config.ts`: 100 % on `config.ts`, `logger.ts`, `menu-collapse.ts` and `menu-collapse-boot.ts`, and >= 95 % lines on the entries `dashboard.ts` and `stimulus-live.ts` (REQ-TEST-003 asks for >= 90 %). The CDN imports of the entries (Stimulus, UX Live, UX Autocomplete) and `sortablejs` are replaced by local doubles in `src/Resources/assets/test-support/`.

## How to refresh

```bash
make coverage-check
# or
composer coverage-check
```
