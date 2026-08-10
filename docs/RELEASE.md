# Release

## Before releasing

> Current release target: **2.1.0** (`v2.1.0`).

1. **Run full checks**

   ```bash
   make release-check
   ```

   This runs: `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `coverage-check`, `test-ts`, and `release-check-demos` (the Symfony 8 demo is started, HTTP-verified, then stopped).

2. **Update changelog and upgrading notes**

   - Add a new section in `docs/CHANGELOG.md` for the release (e.g. `## [2.0.0] - YYYY-MM-DD`) and move any “Unreleased” entries into it. Update the comparison links at the bottom of the file.
   - Add/update the corresponding section in `docs/UPGRADING.md` (e.g. `From 1.0.x to 2.0.0`).
   - The package version for Packagist is taken from the git tag (e.g. `v2.0.0`); you do not need to set `version` in `composer.json`.

## Creating the release

1. **Commit all changes** (changelog, version, etc.).

2. **Create an annotated tag** (replace `0.0.1` with the version):

   ```bash
   git tag -a v0.0.1 -m "Release 0.0.1"
   ```

   Example for this cycle:

   ```bash
   git tag -a v2.1.0 -m "Release 2.1.0"
   ```

3. **Push the tag**

   ```bash
   git push origin v0.0.1
   ```

   Example for this cycle:

   ```bash
   git push origin v2.1.0
   ```

4. **GitHub Actions** (if `.github/workflows/release.yml` is configured) will create or update the GitHub Release for that tag, using the tag message and the corresponding section from `docs/CHANGELOG.md` as the release body.

## After releasing

- Ensure the new version appears on [Packagist](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) (auto-update from GitHub tags, or trigger manually).
- Bump the development version in `composer.json` if you use a dev version string (e.g. `2.0.1-dev` or `2.1.x-dev` for the next cycle).

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.
