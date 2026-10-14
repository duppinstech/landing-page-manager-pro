# Project Plan

This is the living list of repository and release action items. Check items off only after completion; add dated notes for decisions that change scope. IDs (B1, F1, …) are stable so commits and issues can reference them.

## Current Status

- Published: v1.0.2 (GitHub Release, 2026-03-09). Header, runtime version, and `readme.txt` Stable tag: 1.0.3 (unreleased).
- In progress: 1.0.3, a bug-fix and hardening release (B1–B10, B12–B19, F1–F4, F12) plus the GitHub updater standard port (U1). Not yet tagged or released.
- **Blocked for release delivery:** the repository is private, so sites cannot download releases (U2).

## Repository and SDLC Cleanup

- [x] Remove stale local release ZIPs from the repository root (1.0.0 with no top-level folder, 1.0.1, 1.0.2; the last two remain on GitHub Releases). _2026-10-14_
- [x] Correct `OWNERSHIP.md`, which named the wrong product. _2026-10-14_
- [x] Name Duppins Technology in the LICENSE, README, and plugin header ownership lines. _2026-10-14_
- [x] Backfill `CHANGELOG.md` for 1.0.1 and 1.0.2 and correct the 1.0.0 date (2025 → 2026). _2026-10-14_
- [x] Add `.gitattributes` `export-ignore` rules so GitHub zipballs and `git archive` exclude development files. _2026-10-14_
- [x] Add a repeatable release ZIP build script (`bin/build-zip.sh`). _2026-10-14_
- [x] Add a CI workflow that runs `php -l` on PHP 7.4 and current PHP 8.x. _2026-10-14_
- [ ] Add a PHPCS (WordPress Coding Standards) ruleset and run it in CI. This needs a Composer dev dependency, so get owner approval first.
- [ ] Add a WP integration test harness (wp-env or WP test suite) for the tracking handlers and REST endpoint. PHPUnit itself (dev-only Composer) was added with U1 and covers only the updater.

## GitHub Updater Standard

- [x] **U1. Port the Duppins Technology GitHub updater standard v1.0.0.** `includes/class-lpmanager-pro-github-updater.php` is a verbatim copy of the reference class, renamed only. It is booted from the main file at load time, with an `Update URI` header, `uninstall.php` cleanup, ported PHPUnit tests, `.github/workflows/release.yml`, and the standard `bin/build-zip.sh`. The old `Update_Checker` (B5, B16) was removed. _2026-10-14_
- [ ] **U2. Release delivery while the repository is private.** The updater does not support private repositories and must not ship a token. Owner decision: make the repository public, or choose a separate public release channel.
- [ ] **U3. Choose a `Tested up to` version** for `readme.txt`. The release workflow writes it into the release notes, and the updater reports it to WordPress. It is blank until chosen (see B16).

## 1.0.3: Bug Fixes and Hardening (in progress)

- [x] **B1. First-activation defaults never applied.** `carbon_set_theme_option()` ran at `plugins_loaded`, before Carbon Fields boots or registers fields. It raised a Carbon Fields error (a fatal under `WP_DEBUG`), and the pending flag was deleted first. Defaults now apply once, after `carbon_fields_fields_registered`. A `lpmanager_pro_defaults_applied` marker stops reactivation from overwriting the admin's later choices.
- [x] **B2. Conversions recorded on any post.** `lpmanager_record_conversion` accepted any post ID and wrote meta to non-landing-page posts. It now requires a published `landing_page`, as the other handlers do.
- [x] **B3. Unbounded, autoloaded tracking options.** Unauthenticated AJAX callers (the nonce is public on the page) could add unlimited distinct link entries to `lpmanager_link_clicks`, which is autoloaded on every request. Distinct links per page are now capped, stored `href`/label values are length-limited, and the tracking options are saved with autoload off.
- [x] **B4. Heatmap request size.** The number of cells accepted per heatmap request is now capped.
- [x] **B5. Updater calls the GitHub API on every transient write.** The release lookup is now cached in a site transient (6 hours, 1 hour after a failure). The cache clears on the manual "Check for updates" action.
- [x] **B6. Previews counted as page views.** Post previews no longer increment views. Added the `lpmanager_pro_should_count_page_view` filter.
- [x] **B7. Dashboard chart data keyed by title.** Landing pages with the same title overwrote each other in `visits_per_page` / `conversions_per_page`. Duplicate titles are now made distinct by appending the post ID.
- [x] **B8. Text domain and i18n.** The Conversions submenu used the free plugin's text domain, and the Conversions page strings were not translatable.
- [x] **B9. Recording handlers accepted unpublished pages.** Click, engagement, heatmap, and rage-click handlers (and server-side view counting) accepted draft or private landing pages. They now require `publish` status, and a non-string `href` no longer causes a PHP 8 `TypeError`.
- [x] Bump the header, `LP_MANAGER_PRO_VERSION`, and `readme.txt` Stable tag to 1.0.3. _2026-10-14_
- [ ] Date the changelog entry when the release is cut.
- [ ] Manually verify in WordPress with the free plugin active: activation defaults, each tracking handler, the Conversions page, the REST endpoint, and the update check. Also check the missing-dependency path.

## Known Issues to Fix Next

- [x] **B10. Advertised tracking that was not recorded.** Outbound-click, CTA-visibility, and form-interaction tracking were listed but stored nothing. Fixed by F1. _2026-10-14_
- [x] **B17. Time on page counted repeatedly.** The free plugin's script sent cumulative seconds on every tab hide, inflating `time_count` and skewing averages. Pro's script sends one measurement per page view. _2026-10-14_
- [x] **B18. Heatmap samples double-counted.** The grid was re-sent without being cleared on every tab hide. It is now sent once per page view, and mouse samples are throttled to one per 100 ms (previously every `mousemove` was counted). _2026-10-14_
- [ ] **B11. Lost counts under concurrency.** Counters stored in options and post meta use read-modify-write without locking, so simultaneous hits drop increments. The fix is F6 (custom table with atomic `UPDATE … SET n = n + 1`).
- [x] **B19. Expired nonces on cached pages.** A nonce printed into a cached page could be expired (12–24 hours), so every tracking request failed. The script now gets a fresh nonce from `lpmanager_pro_session` on load and holds events until it arrives (falling back to the printed nonce after an error, a 5 s timeout, or the visitor leaving). _2026-10-14_
- [x] **B12. Page caching hid views.** Fixed by F2. _2026-10-14_
- [x] **B13. Hardcoded client template fallback.** `Pro_Analytics` fell back to the `client-template` template slug when the free plugin's template loader was missing. It now uses `default`. _2026-10-14_
- [x] **B14. Pro class in the free plugin's namespace.** `Tracking_Manager` moved from `LPManager\admin` to `LPManagerPro\`, with a `class_alias` keeping the old name. _2026-10-14_
- [x] **B15. REST `daily_visits` / `daily_conversions` ignored the tracking toggles**, while the dashboard filter respected them. Both now respect them. _2026-10-14_
- [x] **B16. Updater metadata.** Added a `plugins_api` handler ("View details" modal with the GitHub release notes), `Requires at least` / `Requires PHP` headers, requirement fields on update entries, and a `no_update` entry so WordPress shows the auto-update toggle. `tested` stays blank until a tested-up-to version is chosen. _2026-10-14_

## Feature Roadmap (aligned with intent: premium analytics and tracking)

Ordered by suggested priority. Each needs scope confirmation before it starts.

- [x] **F1. Pro-owned tracking script.** Pro now ships `assets/js/tracking.js` (no jQuery dependency) and uses `navigator.sendBeacon` for unload events. Outbound clicks, CTA views, and form starts/submits are recorded through `lpmanager_record_interaction` into fixed post-meta counters, shown on the Conversions page, and returned as `interactions` by the REST endpoint. The free plugin's analytics page does not display them yet; that is a free-plugin change. _2026-10-14_
- [x] **F2. Cache-safe view counting.** The same session request counts the view, so cached pages are counted and visitors without JavaScript (mostly bots) are not. The `lpmanager_pro_page_view_counting` filter (`'client'` by default, or `'server'`) restores counting on `template_redirect`. _2026-10-14_
- [x] **F3. Exclude internal traffic and bots.** New **Landing Pages → Pro Settings** page (option `lpmanager_pro_settings`) with two options, both on by default: exclude logged-in users who can `edit_posts`, and exclude known bot/automation user agents (or no user agent). The session response tells the script to stop, and every recording endpoint also refuses excluded requests. The `lpmanager_pro_exclude_request` filter supports custom rules such as IP ranges. _2026-10-14_
- [x] **F4. Privacy and consent.** Pro Settings adds two options. *Honor privacy signals* (on by default) skips visitors sending Global Privacy Control or Do Not Track, checked in the browser and through the `Sec-GPC`/`DNT` headers on every endpoint. *Require consent* (off by default, because tracking uses no cookies or personal data) waits for WP Consent API "statistics" consent or `window.lpmanagerPro.grantConsent()`; revocation stops tracking. Pro registers with the WP Consent API, and suggested privacy-policy text is added under Settings → Privacy. Filter: `lpmanager_pro_require_consent`. _2026-10-14_
- [ ] **F5. Date-range analytics.** Store per-page daily series (views, conversions, clicks) and accept `from`/`to` on the REST endpoint. Today daily totals are site-wide only.
- [ ] **F6. Scalable storage.** Move counters, link clicks, engagement, and heatmaps into a custom table with a versioned schema and a one-time migration from the existing options and meta. This fixes B11 and removes the remaining large options.
- [ ] **F7. Analytics export.** CSV export of by-page / by-client / by-template data (capability and nonce protected).
- [ ] **F8. Reset analytics.** Per-page and global reset actions with confirmation, capability check, and nonce.
- [ ] **F9. Campaign attribution.** Capture UTM source, medium, and campaign on view and conversion, and report conversions by source.
- [ ] **F10. Uninstall cleanup (opt-in).** An `uninstall.php` that removes Pro data only when the owner enabled "delete data on uninstall".
- [ ] **F11. Scheduled email reports.** Weekly per-client summary to configured recipients (WP-Cron).
- [x] **F12. Updater details.** Delivered with B16. _2026-10-14_

## Deployment Readiness (per release)

- [ ] Choose the release scope and version; confirm the plugin header and runtime version match.
- [ ] Run CI lint and tests (and PHPCS once added); record anything not run.
- [ ] Build the ZIP with `bash bin/build-zip.sh`; confirm it contains `landing-page-manager-pro/landing-page-manager-pro.php` and `includes/`, and no development files.
- [ ] Test installation and update on a clean WordPress site with the free dependency active; verify the missing-dependency path.
- [ ] After the release workflow runs, confirm the release has the asset `landing-page-manager-pro.zip` and notes starting with `## Requirements`.
- [ ] Review the changelog, tag, release notes, rollback path, and final Git diff.
- [ ] Publish a GitHub Release only after explicit owner approval; then verify installation/update from the published release.

## Ongoing Maintenance

- [ ] Revisit this plan after each release and remove completed or obsolete items.
