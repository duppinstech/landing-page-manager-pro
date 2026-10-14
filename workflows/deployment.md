# Deployment Workflow

This repository distributes the plugin through GitHub Releases. Deployment means preparing and validating a release package; publication or production deployment always requires explicit owner approval.

## Prepare

- [ ] Confirm the release scope and version.
- [ ] Update `CHANGELOG.md` and keep the plugin header and runtime version consistent.
- [ ] Run available PHP syntax and behavior checks; document unavailable automation.
- [ ] Build a ZIP with the expected plugin directory, entry point, and `includes/` files. Inspect the archive and exclude development-only files as appropriate.
- [ ] Install the ZIP on a clean supported WordPress site with the free dependency active; verify activation and the changed behavior.
- [ ] Check the missing-dependency behavior when loader or activation behavior changed.
- [ ] Verify the GitHub release asset is compatible with the existing update checker.
- [ ] Review the complete diff, tag name, release notes, backup, and rollback plan.

## Publish and Verify

- [ ] Obtain explicit owner approval before pushing a release tag or publishing a GitHub Release.
- [ ] Publish the approved release and package; do not include secrets or unintended repository files.
- [ ] Verify installation or update from the published release on a test site.
- [ ] Record the release URL, version, verification result, and any follow-up in `memory/progress.md`.

## Rollback

If verification fails, stop further rollout, preserve logs, restore the last known-good plugin package, and document the cause and corrective action before retrying.