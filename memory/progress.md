# Project Progress

Keep only current, durable progress notes here. Move completed milestones to `CHANGELOG.md` when they describe a user-visible release.

## 2026-10-14

- **Status:** 1.0.3 bug-fix/hardening work (plan items B1–B10, B12–B19, F1–F4, F12) implemented and recorded under `[Unreleased]` in `CHANGELOG.md`. Repository cleanup is done: stale ZIPs removed, ownership docs corrected, `.gitattributes`, `bin/build-zip.sh`, and the CI lint workflow added. Work is committed on branch `fix/1.0.3-hardening` (not pushed).
- **Next:** Verify the 1.0.3 changes manually in a WordPress install with the free plugin active, then bump to 1.0.3 and prepare the release (owner approval required to publish). Then F5 (date-range analytics).
- **Updater:** GitHub updater standard v1.0.0 ported on `feature/github-updater-standard` (plan U1), with the version bumped to 1.0.3.
- **Blockers:** The repository is private, so sites cannot download releases (plan U2). Owner decisions are pending on a `Tested up to` version (U3) and on adding PHPCS to the Composer dev tooling. The free plugin's analytics page does not yet display the new `interactions` data.

## 2026-10-16

- **Released:** v1.0.3, https://github.com/duppinstech/landing-page-manager-pro/releases/tag/v1.0.3 (tag on `main` at c0c3e78, merged via PR #1 into `fix/1.0.3-hardening` and PR #2 into `main`). Release workflow passed (build and publish), provenance attestation verifies, anonymous GitHub API returns v1.0.3.
- **Verification:** fresh install via `tests/integration/run.sh` (43 checks); 1.0.2 → 1.0.3: auto-update fails safely because of a 1.0.2 updater bug (plan B32), manual ZIP install works and hands over to the new updater.
- **Repository:** public; history rewritten to remove a client name (plan L3); GitHub Support purge requested by the owner (L7).
- **Next:** owner updates managed 1.0.2 sites by ZIP once; then R10 (free plugin display) and the approved roadmap (F6, F5, F7/F8, F9/F11).
- **Released:** v1.0.4, https://github.com/duppinstech/landing-page-manager-pro/releases/tag/v1.0.4 (tag on `main` at a520afc, PR #4), after Landing Page Manager v1.0.7. Tracking options now live in Pro Settings; new Pro Analytics screen; Conversions folded into it. 1.0.3 → 1.0.4 auto-update verified on a test site.
- **Next:** owner updates any remaining 1.0.2 sites by ZIP (B32), then confirms so the free plugin can drop its tracking.js; then F6 (scalable storage).

