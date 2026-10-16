# Project Progress

Keep only current, durable progress notes here. Move completed milestones to `CHANGELOG.md` when they describe a user-visible release.

## 2026-10-14

- **Status:** 1.0.3 bug-fix/hardening work (plan items B1–B10, B12–B19, F1–F4, F12) implemented and recorded under `[Unreleased]` in `CHANGELOG.md`. Repository cleanup is done: stale ZIPs removed, ownership docs corrected, `.gitattributes`, `bin/build-zip.sh`, and the CI lint workflow added. Work is committed on branch `fix/1.0.3-hardening` (not pushed).
- **Next:** Verify the 1.0.3 changes manually in a WordPress install with the free plugin active, then bump to 1.0.3 and prepare the release (owner approval required to publish). Then F5 (date-range analytics).
- **Updater:** GitHub updater standard v1.0.0 ported on `feature/github-updater-standard` (plan U1), with the version bumped to 1.0.3.
- **Blockers:** The repository is private, so sites cannot download releases (plan U2). Owner decisions are pending on a `Tested up to` version (U3) and on adding PHPCS to the Composer dev tooling. The free plugin's analytics page does not yet display the new `interactions` data.
