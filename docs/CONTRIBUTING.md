# Contributing

See the main [README](../README.md). Run `make qa` (cs-check + tests) and `make release-check` before opening a PR.

## Table of contents

- [Code of Conduct](#code-of-conduct)
- [Cursor Agent](#cursor-agent)
- [Git hooks (REQ-GIT-001)](#git-hooks-req-git-001)
- [Igor worker audit (REQ-CS-008)](#igor-worker-audit-req-cs-008)
- [End-to-end tests and screenshots (REQ-DEMO-013)](#end-to-end-tests-and-screenshots-req-demo-013)

## Code of Conduct

This project follows the [Contributor Covenant Code of Conduct](../CODE_OF_CONDUCT.md). By participating, you are expected to uphold it. Please report unacceptable behavior to **hectorfranco@nowo.tech**.

## Cursor Agent

Contributors using Cursor Agent on this repository should enable these user-level settings (REQ-IDE-006):

1. **Submit with Ctrl + Enter** (macOS: **⌘ + Enter**) — Cursor Settings → Agents / Chat. Enter inserts a newline; Ctrl/⌘+Enter submits.
2. **Queue follow-ups** in user `settings.json` (Linux `~/.config/Cursor/User/settings.json`, Windows `%APPDATA%\Cursor\User\settings.json`, macOS `~/Library/Application Support/Cursor/User/settings.json`):

```json
{
  "cursor.composer.queueMessageDefaultBehavior": "queue"
}
```

Do not use `"steer"` as the default: it interrupts a running agent turn.

## Git hooks (REQ-GIT-001)

Do **not** add `Co-authored-by: Cursor` or `cursoragent@cursor.com` trailers, nor `Co-Authored-By: Claude …` / `noreply@anthropic.com` ones, to commit messages.

```bash
make setup-hooks
make check-no-cursor-coauthor
```

`make setup-hooks` installs `.githooks/commit-msg` (or sets `core.hooksPath` to `.githooks`). Run it once per clone before your first commit.
If CI fails because trailers are already on the remote, see [GITHUB_CI.md](GITHUB_CI.md) (REQ-GIT-001) and run `make strip-cursor-coauthor-from-history` before `git push --force-with-lease`.

Maintainers run `make check-no-cursor-coauthor` **after** creating a release commit and **before** `git push` (REQ-GIT-001): an earlier `release-check` run does not cover the release commit itself.

## Igor worker audit (REQ-CS-008)

Run `make igor` (or `composer igor`) before release. Igor audits package `src/` for FrankenPHP worker-state issues. It is require-dev only.

## End-to-end tests and screenshots (REQ-DEMO-013)

The Symfony 8 demo ships a Playwright suite in `demo/symfony8/e2e/` (official Playwright image, nothing to install on the host):

```bash
make -C demo/symfony8 test-e2e          # starts the demo if needed, runs the happy-path specs
make -C demo/symfony8 demo-screenshots  # recaptures docs/images/demo/*.webp (PNG → WebP via sharp)
```

The happy path opens the menus list, the "User menu" detail with its items table and the item label dialog with its locale tabs. Run `make -C demo/symfony8 up` first on a fresh clone (database + fixtures). Refresh the screenshots when the dashboard UI changes and commit only the `.webp` files.
