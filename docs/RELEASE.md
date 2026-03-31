# Release

## Before releasing

> Current release target: **0.3.34** (`v0.3.34`).

1. **Run full checks**

   ```bash
   make release-check
   ```

   This runs: `composer-sync`, `cs-fix`, `cs-check`, `rector-dry`, `phpstan`, `test-coverage`, and `release-check-demos` (each demo is started, HTTP-verified, then stopped).

2. **Update changelog and upgrading notes**

   - Add a new section in `docs/CHANGELOG.md` for the release (e.g. `## [0.3.15] - YYYY-MM-DD`) and move any “Unreleased” entries into it. Update the comparison links at the bottom of the file.
   - Add/update the corresponding section in `docs/UPGRADING.md` (e.g. `From 0.3.33 to 0.3.34`).
   - The package version for Packagist is taken from the git tag (e.g. `v0.3.15`); you do not need to set `version` in `composer.json`.

## Creating the release

1. **Commit all changes** (changelog, version, etc.).

2. **Create an annotated tag** (replace `0.0.1` with the version):

   ```bash
   git tag -a v0.0.1 -m "Release 0.0.1"
   ```

   Example for this cycle:

   ```bash
   git tag -a v0.3.34 -m "Release 0.3.34"
   ```

3. **Push the tag**

   ```bash
   git push origin v0.0.1
   ```

   Example for this cycle:

   ```bash
   git push origin v0.3.34
   ```

4. **GitHub Actions** (if `.github/workflows/release.yml` is configured) will create or update the GitHub Release for that tag, using the tag message and the corresponding section from `docs/CHANGELOG.md` as the release body.

## After releasing

- Ensure the new version appears on [Packagist](https://packagist.org/packages/nowo-tech/dashboard-menu-bundle) (auto-update from GitHub tags, or trigger manually).
- Bump the development version in `composer.json` if you use a dev version string (e.g. `0.0.2-dev` or `1.0.x-dev` for the next cycle).
