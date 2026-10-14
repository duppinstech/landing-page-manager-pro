# Deployment Workflow

This repository distributes the plugin through GitHub Releases. Deployment means preparing and validating a release package; publication or production deployment always requires explicit owner approval.

## Prepare

- [ ] Confirm the release scope and version.
- [ ] Update `CHANGELOG.md` and keep the plugin header `Version`, `LP_MANAGER_PRO_VERSION`, and the `readme.txt` `Stable tag` identical. The release workflow fails on a tag that does not match all three.
- [ ] Run `composer lint` and `composer test`, plus available behavior checks; document unavailable automation.
- [ ] Commit the release changes, then run `bash bin/build-zip.sh`. Inspect the listed archive contents: a single `landing-page-manager-pro/` folder with the entry point, `includes/`, `assets/`, `uninstall.php`, and `readme.txt`, and no development files.
- [ ] Install the ZIP on a clean supported WordPress site with the free dependency active; verify activation and the changed behavior.
- [ ] Check the missing-dependency behavior when loader or activation behavior changed.
- [ ] Confirm the repository is public, or that another release channel has been chosen. The updater does not support private repositories, so sites get a 404.
- [ ] The tag is `v<version>`. `.github/workflows/release.yml` builds the ZIP and attaches it as `landing-page-manager-pro.zip`, the only asset name the updater installs.
- [ ] Review the complete diff, tag name, release notes, backup, and rollback plan.

## Publish and Verify

- [ ] Obtain explicit owner approval before pushing a release tag or publishing a GitHub Release. Pushing a `v*` tag publishes a release automatically.
- [ ] Publish the approved release and package; do not include secrets or unintended repository files.
- [ ] Verify installation or update from the published release on a test site.
- [ ] Record the release URL, version, verification result, and any follow-up in `memory/progress.md`.

## Rollback

If verification fails, stop further rollout, preserve logs, restore the last known-good plugin package, and document the cause and corrective action before retrying.