# Concurrency (REQ-RUNTIME-003)

How the bundle stays correct when two admins, two tabs or two imports write the same menu at the same time. Since 2.23.

## Table of contents

- [Scope](#scope)
- [Write paths](#write-paths)
- [Write unit](#write-unit)
- [Menu row lock](#menu-row-lock)
- [Conflict answer and codes](#conflict-answer-and-codes)
- [Stale edits and double submits (2.24)](#stale-edits-and-double-submits-224)
- [Unique code and context](#unique-code-and-context)
- [Host options](#host-options)
- [Worker mode and the host entity manager](#worker-mode-and-the-host-entity-manager)
- [Tests](#tests)
- [Residual risks](#residual-risks)

## Scope

Only the admin side writes: the dashboard controller, the Live item form and the importer. The public JSON API, the
Twig functions and the menu hub only read. The bundle has no Messenger handler and no console command that writes. The
entities are `Menu` and `MenuItem`.

## Write paths

| Path | Transaction | Control | Conflict answer |
| --- | --- | --- | --- |
| `menu_new` (create menu) | One write unit | Pre-check + unique key `uniq_menu_code_context` with the violation caught | Lock conflict: 409 `CONFLICT_001`. Duplicate: the form error (422), no conflict code |
| `menu_edit` (form) | One write unit (the form binds into the managed menu) | Pre-check + unique key with the violation caught; menu row lock, then the form's `_base_version` against the stored `edit_version` (2.24) | As `menu_new`; a stale form: flash "changed by someone else", nothing stored, no conflict code |
| `menu_delete` | One write unit | Menu row lock | 409 `CONFLICT_001` / `CONFLICT_002` |
| `menu_copy` (menu + every item, two flushes) | One write unit | Pre-check + unique key with the violation caught | Lock conflict: 409. Duplicate: the flash error and the form, no conflict code |
| `item_new` (append) | One write unit (insert, then sibling re-index) | Menu row lock, then `MAX(position) + 1` read under it; the form's one-time `_submit_token` stored in the unique `submit_token`, checked under the lock (2.24) | 409 `CONFLICT_001` / `CONFLICT_002`; a form posted twice: "Item already created" flash, one item, no conflict code |
| `item_edit` (form; config section may move the item) | One write unit (save, then re-index of the old and new sibling groups) | Menu row lock; appended position read under it; the form's `_base_version` against the stored `edit_version` (2.24) | 409; a stale form: flash "changed by someone else", nothing stored, no conflict code |
| `item_delete` | One write unit | Menu row lock | 409 |
| `item_move_up` / `item_move_down` | One write unit | Menu row lock; the item is refreshed and the siblings read after the lock | 409 |
| `items_reindex_positions` | One write unit | Menu row lock | 409 |
| `items_reorder_tree` (drag and drop) | One write unit | Menu row lock | 409; an invalid layout rolls back and shows its flash |
| `import` (file of one or more menus, `skip_existing` / `replace`) | One write unit for the whole file (`MenuImporter::import()`) | Replace: menu row lock + the old items read with `FOR UPDATE`; create: unique key with the violation caught | Lock conflict: 409. Duplicate: the import error `MenuImporter::CONCURRENT_CREATE_ERROR`, nothing imported |
| Live item form `save` | One write unit | Edit: `PESSIMISTIC_WRITE` on the item row, then the `baseVersion` LiveProp (the version of the first render) against the stored `edit_version`; new item: the `submitToken` LiveProp, unique `submit_token` (2.24) | 409; stale or posted twice: the same flashes as the forms |
| `MenuImporter::import()` called by host code | One write unit | As `import` | `TransientStorageFailure` (the host answers) |

`items_check_parent_cycles`, the exports and every GET page only read. `grep -rn "flush()" src/` finds only flushes
inside a write unit: the unit's own, and the inner ones of `item_new`, `item_edit`, `menu_copy` and the importer, which
need the new ids before the unit goes on.

## Write unit

`Nowo\DashboardMenuBundle\Concurrency\DashboardMenuTransaction` runs one unit: begin, run, flush, commit
(REQ-RUNTIME-003 rule 1). It is built on `nowo-tech/transaction-doctrine` ^1.6:

- with the bundle's own manager (`doctrine.entity_manager` set to a manager that is not the host's default):
  `DoctrineTransactionRunner::forEntityManager()`, which closes and resets that manager after a storage failure;
- with the host's manager (the default): transaction-doctrine's connection runner (nesting level repaired to the entry
  level, savepoints for nested units, a lost savepoint never taken as success). The manager is never cleared, closed
  or reset while it is open. See [Worker mode and the host entity manager](#worker-mode-and-the-host-entity-manager).

A deadlock, lock wait timeout or serialization failure leaves the unit as `Nowo\TransactionContract\TransientStorageFailure`,
with the storage error as previous. There is no automatic replay: a dashboard action is a form post, and the answer
tells the admin to retry (rule 7: nothing is replayed, so nothing can run twice).

Side effects after the commit (rule 1): the menu tree cache version is bumped by Doctrine listeners during the flush,
inside the transaction. A concurrent reader could cache the old tree under the new version before the commit, so the
unit bumps the same codes again once it has ended (`MenuTreeCacheInvalidator::invalidatePendingAfterCommit()`).
The listener that bumps them (`MenuCacheInvalidationSubscriber`) is registered on every connection since 2.23 (it
was never wired before). After a failed unit the code lookup memo of `MenuRepository` is cleared, so a retry in the same
request never gets a menu entity the failure detached.

## Menu row lock

`MenuRepository::lockForWrite()` takes `SELECT … FOR UPDATE` on the menu row (rule 4). Every write to a menu's item tree
takes it first, inside its unit, and it is the only lock the bundle takes, so:

- two appends to one sibling group run one after the other; the second reads the first's position and never gets the
  same `MAX(position) + 1`;
- moves, re-index and reorder read positions that no other bundle writer is changing;
- the bundle's own writers cannot deadlock each other (one lock per unit, always the menu row).

The lock waits at most the server's lock wait timeout (`innodb_lock_wait_timeout` on MySQL / MariaDB, 50 s by default;
`lock_timeout` on PostgreSQL, unlimited by default: set it for the web role, see [Host options](#host-options)). No
HTTP call, mail or file I/O runs while it is held (rule 9). SQLite has no row locks; its writers are serialised by the
database lock.

Under MySQL / MariaDB REPEATABLE READ the lock is the first statement of the unit, so the positions read after it come
from a snapshot taken after the lock. The import "replace" reads the old items with a locking read
(`MenuItemRepository::findAllForMenuForUpdate()`), which always sees the latest committed rows.

## Conflict answer and codes

`Nowo\DashboardMenuBundle\Concurrency\DashboardMenuConflictAnswer` turns a lock conflict on the bundle's own routes (the
dashboard controller and the Live item form, never a host route) into an `HttpException` rendered by the application's
error page (rule 6):

| Situation | Status | Headers | Message |
| --- | --- | --- | --- |
| Lock conflict, nothing of the request committed | 409 (or 503) | `X-Conflict-Code: CONFLICT_001`, `Retry-After: 1` | `Another change to this menu is in progress. Retry in a moment.` |
| Lock conflict after part of the request committed | 409 (or 503) | `X-Conflict-Code: CONFLICT_002`, no `Retry-After` | `Part of the change may already be saved. Reload the page before trying again.` |

- Lock conflict = deadlock, lock wait timeout or serialization failure (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL
  40001 / 40P01 / 55P03), as classified by transaction-doctrine, or a lost savepoint (the commit outcome is unknown,
  so `CONFLICT_002`).
- "Part committed" is decided per request with `ConflictCode::forRequest()`: anything committed since the request's
  `CommitMark` (transaction-doctrine's request watermark: the host, another bundle or an earlier unit of the same
  request) makes the answer `CONFLICT_002`. The watermark exists when `NowoTransactionDoctrineBundle` is registered with
  `commit_detection: true` (its default); without it the failure alone decides.
- `X-Conflict-Code` carries only `CONFLICT_001` or `CONFLICT_002`. The storage error stays the previous exception, for
  the logs; no SQL or value reaches the client.
- Unique-constraint violations and lost connections are not conflicts: they are never mapped here (see below).

## Stale edits and double submits (2.24)

Rule 4 asks for a deliberate control on every read-modify-write. The tree positions are written under the menu lock
(above); the form fields and the creation of items have their own, added in 2.24:

- **`edit_version`** on `dashboard_menu` and `dashboard_menu_item` (integer, default 1). The edit forms carry the
  version they were built from in the hidden `_base_version` (in the Live item form it is the `baseVersion` LiveProp, so
  a re-render cannot refresh it). Inside the write unit, after the menu lock, the save reads the stored version from the
  database, never from the unit of work (`MenuRepository::storedEditVersion()`, `MenuItemRepository::storedEditVersion()`,
  `PESSIMISTIC_WRITE`), and `EditVersion::assertCurrent()` compares: any other value, a missing field or a missing row is
  a `StaleEditException`. Nothing is saved, the unsaved entity is detached (a later flush cannot store it; the manager
  is never cleared or reset), and the admin gets a flash and the menu page. This is a **domain answer**, not
  `CONFLICT_001` / `CONFLICT_002`: nothing failed, the form was simply older than the data.
- For the same reason `item_edit` recomputes the item's change set (`recomputeSingleEntityChangeSet()`) after it appends
  the moved item to its new sibling group, so the position adds to the form's changes (new parent, other fields)
  instead of replacing them (it did replace them in 2.23). Any other operation that changes, inside the unit, an entity
  the form already changed must do the same.
- The version is bumped together with the form's own changes **before** the write unit starts. The unit computes the
  change sets of the managed menu entities when it begins (to refuse host changes), and Doctrine keeps only the latest
  change set of an entity: a bump made inside the unit would replace the form's changes.
- Item positions (move up / down, re-index, drag and drop, the append of a new item) do not change `edit_version`, so
  reordering by one admin does not make another admin's open edit form stale. The import "replace" bumps the menu's
  version (its fields are overwritten).
- **`submit_token`** (nullable, unique) on `dashboard_menu_item`. A new-item form carries a random one-time
  `_submit_token`; the created item stores it. Inside the unit, after the menu lock, a token that is already stored means
  the same form was posted before: nothing is added and the admin sees "Item already created". A unique violation of
  the same key (two posts that both passed the check; the check cannot see an uncommitted row) gets the same answer.
  Items created without a token (import, copy, host code, a form rendered before 2.24) are created as before.

## Unique code and context

A menu is unique by code and context (`uniq_menu_code_context`). The dashboard and the importer check first, and the
unique key closes the race between the check and the insert. The violation is the expected "only one" race, so it is
caught and answered as the domain result, never as a `CONFLICT_*` code:

- create / edit menu: the form error `dashboard.unique_code_context_error` (422), the same as the pre-check;
- copy menu: the flash `A menu with this code and context already exists.` and the copy form;
- import: the whole import is rolled back and the result is `MenuImporter::CONCURRENT_CREATE_ERROR` (nothing was
  imported; importing again sees the other menu and applies the chosen strategy).

Any other database error is a real error (500 from the host's error page).

## Host options

```yaml
nowo_dashboard_menu:
    conflict_response:
        status: ~        # 409 (default) or 503; null = transaction-doctrine's conflict_response, else 409
        retry_after: ~   # seconds of a CONFLICT_001 answer; null = transaction-doctrine's, else 1
```

- Register `Nowo\TransactionDoctrine\Symfony\NowoTransactionDoctrineBundle` to get the request watermark
  (`CONFLICT_002` for commits made earlier in the request) and one shared `conflict_response` for every bundle.
- Set a lock wait for the web role so a held menu lock fails fast: MySQL / MariaDB `innodb_lock_wait_timeout`,
  PostgreSQL `lock_timeout` (for example `ALTER ROLE app SET lock_timeout = '5s'`).
- Cross-origin clients that read the code must list `X-Conflict-Code` in `Access-Control-Expose-Headers` (the dashboard
  is same-origin HTML, so this is only for hosts that proxy it).

## Worker mode and the host entity manager

FrankenPHP worker mode with kernel reset false (REQ-RUNTIME-002): the unit and the conflict answer are stateless; the
cache invalidator's after-commit list is request state, emptied by its `reset()` at every main request.

On the host's manager (rule 2):

- the manager is never cleared, closed or reset while it is open;
- the dashboard forms bind into managed `Menu` / `MenuItem` entities before the unit starts, so pending changes on
  those two classes are the bundle's own work and the unit flushes them;
- pending changes on any other entity belong to the host: the unit refuses to start with a `LogicException`
  (`DashboardMenuTransaction::HOST_PENDING_MESSAGE`), since its flush would carry them into a transaction that may
  roll back;
- after a failure, a closed manager is replaced through the registry; in an open one only the menu entities are
  detached, so the host can never flush the rolled-back menu state later. The host's own entities are not touched.

With the bundle's own manager, that manager is closed and reset after a storage failure, and
`DashboardMenuOwnEntityManagerSubscriber` keeps clearing it at every unit-of-work boundary (2.21).

## Tests

- `tests/Integration/Database/DatabaseConcurrencyTest.php` (group `database`): MySQL 8.4, MariaDB 11.4 and PostgreSQL 16,
  two real connections (two kernels, or a kernel and a raw DBAL connection), 1-second lock waits in every session:
  - concurrent appends to one sibling group, on the host's manager and on the bundle's own manager: 409
    `CONFLICT_001` + `Retry-After` while the lock is held, then the next position (no duplicate);
  - a host commit earlier in the request: 409 `CONFLICT_002`, no `Retry-After`;
  - `conflict_response.status: 503`;
  - import "replace": a failure in the middle keeps the old tree; a held menu lock gives a lock conflict and changes
    nothing;
  - a concurrent create of the same code and context, from the importer and from the dashboard form: the domain answer,
    no conflict code, nothing half-imported;
  - after every conflict the manager is open and the connection is usable at nesting level 0;
  - 2.24: a double submit of the new-item form while the first holds the menu (409 `CONFLICT_001`, then one item); a stale
    item edit and a stale menu edit: the other admin's save stays, a form read afterwards saves, no conflict code.
- CI job **Databases (MySQL 8.4, MariaDB 11.4, PostgreSQL 16)** on DBAL 4 and DBAL 3, run with
  `--group database --fail-on-skipped`, a required check on `main`. Locally:
  `DM_MYSQL_URL=… DM_MARIADB_URL=… DM_POSTGRES_URL=… vendor/bin/phpunit --group database`.
- Unit tests cover the conflict mapping (001 / 002, 503, hints, unique violations and lost connections not mapped),
  the write unit on both managers and the after-commit cache bump.

## Residual risks

- **Writers outside the bundle.** Host code that writes `dashboard_menu_item` positions directly without
  `MenuRepository::lockForWrite()` is not serialised with the dashboard.
- **Nested in a host transaction.** When the host runs a dashboard write inside its own open transaction, the unit is a
  savepoint: the menu lock is held until the host commits, the after-commit cache bump waits for a later unit (or the
  cache TTL), and under REPEATABLE READ the positions come from the host transaction's snapshot.
