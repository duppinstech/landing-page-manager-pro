# Project Plan

This is the living list of repository and release action items. Check items off only after completion; add dated notes for decisions that change scope.

## Deployment Readiness

- [ ] Choose the next release scope and version; confirm the plugin header and runtime version will match.
- [ ] Establish repeatable validation for supported WordPress/PHP versions. This repository currently has no test suite, PHPCS configuration, or CI workflow.
- [ ] Define and verify the release ZIP contents, including the plugin entry point and `includes/`, while excluding development-only files as appropriate.
- [ ] Test installation and update on a clean WordPress site with the free dependency active; verify the missing-dependency path when relevant.
- [ ] Verify the GitHub Release package is usable by the existing plugin updater.
- [ ] Review the changelog, tag, release notes, rollback path, and final Git diff.
- [ ] Publish a GitHub Release only after explicit owner approval; then verify installation/update from the published release.

## Ongoing Maintenance

- [ ] Add focused regression tests when a suitable test harness is introduced.
- [ ] Revisit this plan after each release and remove completed or obsolete items.

## Current Status

Scaffolding only. No release has been prepared or deployed by this plan.