# Department Review — 2026-10-17 19:25 EDT

**Change reviewed:** F19, one Analytics screen and Pro Settings renamed Tracking (`feature/one-analytics-screen`), checked together with Landing Page Manager's `feature/analytics-tracking-slots`.
**Departments:** all nine, in one combined pass.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Fix before merge | Stored XSS: the dashboard's `escapeHtml()` left quotes unescaped inside attributes, so an Author could put script into an administrator's Analytics screen through a page title. Everything else was escaped and checked for `manage_options`. |
| QA | Pass with follow-ups | No end-to-end test with Landing Page Manager 1.0.12; no editor check on the detail view; filter handlers hard-coded `lp_analytics`; heatmap previews loaded every page on opening. |
| Release | Pass | All four version pairings work; no data changes; rollback safe. Release Pro 1.0.6 before or with Landing Page Manager 1.0.13. |
| Product | Pass with follow-ups | Dashboard text was English-only; "in Settings" wording; the detail view's back link went to Overview; the dashboard heatmap was colour only. |
| Support | Pass with follow-ups | README sentence repeated "for each landing page"; `tracking.js` comments said Pro Settings. |
| Legal | Pass with follow-ups | Copyright lines missing on the moved `analytics.js` and `analytics.css`. Chart.js is MIT and loaded, not bundled. |
| Privacy | Pass with follow-ups | No change to visitor data. Document that the Analytics screen contacts jsDelivr. |
| Marketing | Pass | Docs match what ships; release copy drafted for the release notes. |
| Finance | n/a | No pricing, licensing or cost change. |

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | Security | Escape `"` and `'` in `escapeHtml()` | Auto-approved, implemented; checked in a browser with a hostile page title |
| 2 | Security | Subresource Integrity for Chart.js | Auto-approved, implemented. The URL now names `chart.umd.js` (the published, minified file; jsDelivr generates `.min.js` on the fly and says not to pin a hash on it), checked byte for byte against the npm package |
| 3 | QA | Test Pro 1.0.6 with Landing Page Manager 1.0.12 | Auto-approved: the integration suite now checks whichever pairing it runs against; run against both |
| 4 | QA | Editor gets nothing from the detail view | Auto-approved, check added |
| 5 | QA | Filter changes use the screen's slug | Auto-approved, implemented |
| 6 | QA | Load heatmap previews only when the Heatmap tab opens | Auto-approved, implemented |
| 7 | Product | Translatable dashboard text | Auto-approved: strings passed from PHP through `wp_localize_script` |
| 8 | Product | "in Settings" → "under Landing Pages → Tracking", with the Tracking screen's labels | Auto-approved, implemented |
| 9 | Product | Back link returns to By Page | Auto-approved: the active tab is kept in the address (`#pages`) |
| 10 | Product | Accessible alternative to the colour-only heatmap | Auto-approved: each heatmap card links to the page's counted grid |
| 11 | Support | README sentence, `tracking.js` comments | Auto-approved, implemented |
| 12 | Legal, Privacy | Copyright lines; README note on jsDelivr | Auto-approved, implemented |
| 13 | Release | Version bump and readme.txt entry | At release (needs authorization) |
| 14 | Product | The all-pages metrics table is gone with Landing Page Manager 1.0.13 | Owner decision (U9) |
| 15 | Privacy, Legal | Bundle Chart.js instead of the CDN | Owner decision (U8) |

## Notes

- After the fixes: 69 unit tests, PHPCS clean, integration suite passes against Landing Page Manager 1.0.13 (branch) and 1.0.12. Browser checks (13) pass: no script from a title containing quotes, previews load on the Heatmap tab, tab kept in the address, back link opens By Page, filters stay on the screen, Chart.js loads with its integrity hash.
- The re-review follows in the next meeting.
