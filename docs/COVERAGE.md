# Coverage policy

## Table of contents

- [PHP line coverage gate](#php-line-coverage-gate)
- [Justified exclusions](#justified-exclusions)
- [How to refresh](#how-to-refresh)

## PHP line coverage gate

`make coverage-check` / `composer coverage-check` enforce **≥ 99%** line coverage on the PHPUnit includable `src/` set (`REQ-TEST-003`).

Published README percentage must match the latest `coverage-output.txt` / CI artifact.

## Justified exclusions

Paths under `<source><exclude>` in `phpunit.xml.dist` are covered primarily by integration / demo smoke rather than aggregate Clover:

| Path | Reason |
| --- | --- |
| `src/Command/GoogleSyncTranslationsCommand.php` | External Google Translate REST CLI |
| `src/Controller/Dashboard/` | Thin HTTP + CSRF dashboard UI |
| `src/DependencyInjection/Compiler/` | Compile-time wiring (unit-tested separately) |
| `src/Repository/MenuItemRepository.php` | Doctrine helpers |
| `src/Service/MenuTreeLoader.php` | Tree load / cache; integration |
| `src/Form/MenuItemConfigType.php`, `MenuItemIconType.php` | Form types coupled to UX |
| `src/Entity/MenuItem.php` | Entity getters/setters |
| `src/Command/GenerateDashboardMenuMigrationCommand.php` | Migration generator CLI |
| `src/DependencyInjection/Configuration.php` | Config tree (covered via ConfigurationTest) |

Do **not** add new `@codeCoverageIgnore` in production code without updating this document.

## How to refresh

```bash
make coverage-check
# or
composer coverage-check
```
