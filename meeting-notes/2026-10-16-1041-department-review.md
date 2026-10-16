# Department Review — 2026-10-16 10:41 EDT

**Change reviewed:** 1.0.4 work on `feature/1.0.4-tracking-toggles`: F14 (tracking toggles owned by Pro, stored in the existing `_lpmanager_track_*` rows) and F15 (Landing Pages → Pro Analytics).
**Departments:** all nine.

## Owner decisions since the last meeting

- Analytics are Pro-only; Pro's extra data goes on Pro's own screens (R10 dropped for the free plugin).
- The tracking toggles move into Pro in 1.0.4, before F6. The free-plugin side was built to the same contract by the landing-page-manager session (its branch `fix/owner-decisions-p0`, unreleased).

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass | The settings form can only write the ten registered options; capabilities, GET filters, and escaping sound. |
| QA | Pass with follow-ups | Missing toggle rows read as off (see Release); tests for carry-over, capability, and missing rows. |
| Legal | Pass with follow-ups | "Conversions are always recorded" overstates; exclusions still apply. |
| Privacy | Pass | No new data; consent and exclusions unaffected. |
| Finance | Pass with follow-ups | Keep price; Pro-only analytics make licensing (C1) matter more before standalone sales. |
| Product | Pass with follow-ups | Heatmap contrast and headers; summary ignores toggles; overlapping menus. |
| Marketing | Pass with follow-ups | Conversion wording; Carbon Fields requirement and REST-only claims out of date. |
| Support | Pass with follow-ups | Upgrade notice for the moved toggles; document `lpmanager_pro_tracking_options()`. |
| Release | **Blocking** | A never-saved toggle row read as off, while the free plugin defaulted it to on: upgrading would stop tracking on sites where nobody saved those settings. |

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | Release, QA | Treat a missing toggle row as on | Auto-approved, implemented and tested (B33) |
| 2 | QA | Autoload the toggle rows (read on every landing-page request) | Auto-approved, implemented |
| 3 | QA | Tests: missing row, capability on Pro Analytics | Auto-approved, implemented (unit and integration) |
| 4 | QA | Template filter: same handling as the REST endpoint | Auto-approved, implemented |
| 5 | Product | Heatmap contrast (about 6:1 now) and row/column headers | Auto-approved, implemented (R16) |
| 6 | Product | Summary shows only enabled features; title fallback; exclusion text under "Who is tracked" | Auto-approved, implemented |
| 7 | Legal, Marketing | "Conversion tracking is always on; the Who is tracked rules still apply" | Auto-approved, implemented on screen, README, CHANGELOG |
| 8 | Marketing, Support | README: Carbon Fields line, Pro Analytics feature text, REST line, Functions section | Auto-approved, implemented |
| 9 | Support, Product, Marketing | CHANGELOG notes the old tab still shows with older free-plugin versions; function under Added; internal bullet dropped | Auto-approved, implemented |
| 10 | Support | readme.txt uninstall text mentions tracking choices; stale comments in Settings.php and tracking.js | Auto-approved, implemented |
| 11 | Support, Release, Finance | 1.0.4 Upgrade Notice; version bump and changelog date | At release |
| 12 | Product | One analytics menu: fold Conversions into Pro Analytics (R15) | Owner decision |
| 13 | Release, Product, Finance | Release order with the free plugin | Owner decision; recommended: free plugin first or both together |
| 14 | Privacy | Show only scheme and host for `mailto:` links on screen | Later, not needed for 1.0.4 |
| 15 | Marketing | Announcement | Not needed (C3) |

## Forwarded to owner

- R15: retire Pro's Conversions screen in favor of Pro Analytics? (The free plugin's own Analytics entry is that repository's decision.)
- Release order and timing for 1.0.4 together with the free plugin's release.

## Notes

- After the fixes: 69 unit tests, PHPCS clean, `node --check`, integration harness passes (including "a never-saved toggle reads as on" and "Pro Analytics hidden from editors").
- The loop closes here: the blocking item is fixed with tests; the rest were small text and markup changes covered by the harness.
