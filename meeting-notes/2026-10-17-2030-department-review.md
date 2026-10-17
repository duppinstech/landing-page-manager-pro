# Department Review — 2026-10-17 20:30 EDT (round 3)

**Change reviewed:** the 20:10 fixes (R18) in `10d90d8`.
**Departments:** Security, QA, Product (the departments that raised the round-2 items).

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass | Bookmark redirect is not an open redirect, doesn't double-encode, and ignores odd input; number handling fine. |
| QA | Pass | A plan entry was joined to the previous line. Info: a redirect could point at itself if Landing Page Manager named no Analytics screen. |
| Product | Pass | After a mouse click inside the heatmap dialog, focus fell to the page, so Escape stopped working. |

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | QA | Fix the plan formatting | Auto-approved, done. The new owner items were also renumbered U7–U9 (U4–U6 were already used) |
| 2 | QA | Never redirect a screen to itself | Auto-approved, guard added |
| 3 | Product | Keep focus inside the dialog after a click (`tabindex="-1"` on the dialog box) | Auto-approved, implemented; browser check added (click inside, then Escape closes and focus returns) |

## Notes

- After the fixes: 70 unit tests, integration suite passes against Landing Page Manager 1.0.13 (branch) and 1.0.12, 17 browser checks pass.
- No new auto-approved work beyond these one-line fixes; the review loop ends here. Open owner items: U7 (release the security fix promptly), U8 (Chart.js CDN or bundled), U9 (all-pages table).
