# Department Review — 2026-10-17 20:10 EDT (re-review)

**Change reviewed:** the 19:25 fixes (R17) in `e74c138` on `feature/one-analytics-screen`.
**Departments:** all nine, in one combined pass.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass | All R17 fixes correct. The released Landing Page Manager 1.0.12 and earlier have the same quote-escaping issue in their own dashboard script, which Pro data feeds, so updating Pro alone doesn't fix it. |
| QA | Pass with follow-ups | Old Pro Analytics bookmarks lost `page_id` and filters on redirect; no unit test for the Chart.js integrity filter. |
| Product | Pass with follow-ups | Heatmap dialog: no focus move, no Escape, no focus return, no label. Previews were in the Tab order; no focus style on cards; identical link text on each card; overview empty state named a toggle that doesn't exist. |
| Support, Privacy | Pass with follow-ups | readme.txt didn't mention jsDelivr. |
| Release, Legal, Marketing | Pass | No new findings. |
| Finance | n/a | |

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | Security | Record the 1.0.12 issue: Security entries in both changelogs, and a Pro upgrade note to update Landing Page Manager to 1.0.13 | Auto-approved, implemented. Whether to release promptly or announce it: owner (U6) |
| 2 | Security | Wrap numeric values in `Number()` before HTML | Auto-approved, implemented |
| 3 | QA | Keep `page_id`, `client_id`, `template` when redirecting old Pro Analytics links | Auto-approved, implemented, integration check added |
| 4 | QA | Unit test for `chart_js_integrity` (both quote styles, other handles, existing integrity) | Auto-approved, added (70 tests) |
| 5 | Product | Dialog: `aria-labelledby`, focus to Close, Escape closes, focus returns to the card, Tab stays inside | Auto-approved, implemented, browser-checked |
| 6 | Product | Previews `inert` and `tabindex="-1"`; `:focus-visible` on cards; page title as screen-reader text on each grid link | Auto-approved, implemented |
| 7 | Product | Overview empty state: "Page views or Link clicks" | Auto-approved, implemented |
| 8 | Support, Privacy | jsDelivr sentence in readme.txt | Auto-approved, implemented |

## Notes

- After the fixes: 70 unit tests, PHPCS clean, integration suite passes against Landing Page Manager 1.0.13 (branch) and 1.0.12, and 16 browser checks pass (including Enter opens the dialog with focus on Close, Escape closes it and returns focus, previews inert).
- A short round 3 (security, QA, product) reviews these fixes.
