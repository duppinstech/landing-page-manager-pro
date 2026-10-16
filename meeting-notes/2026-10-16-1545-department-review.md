# Department Review — 2026-10-16 15:45 EDT

**Change reviewed:** F6 scalable storage on `feature/f6-scalable-storage` (would ship as 1.0.5): atomic analytics tables, atomic post-meta counters (fixes B11), and a one-time migration of the old options.
**Departments:** all nine.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass | SQL fully prepared; public writes bounded to known metrics and published pages. |
| QA | Pass with follow-ups | Setup marked done even if CREATE fails; sparse heatmap keys renumbered; first-hit duplicate meta rows. |
| Legal | Pass | Notice correct; no new third-party code. |
| Privacy | Pass with follow-ups | The old link option kept uncleaned URLs; "aggregate per page" and "kept because Landing Page Manager uses them" no longer accurate; unbounded retention. |
| Finance | Pass with follow-ups | Per-day heatmap rows would grow the table about 10 KB per page per day; index order. |
| Product | Pass with follow-ups | New options missing from opt-in uninstall; plan runbook out of date; F5 limits. |
| Marketing | Pass with follow-ups | Changelog and README wording; 1.0.5 upgrade notice. |
| Support | Pass with follow-ups | Silent failure if tables cannot be created; downgrade and re-run guidance. |
| Release | Pass with follow-ups | Same CREATE failure (High); downgrade/re-upgrade loses 1.0.4 data; migration memory; heatmap growth; multisite table drop. |

Nothing blocks releasing 1.0.5 after the follow-ups.

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | Release, QA, Support | Mark setup and migration done only on success; admin notice when tables cannot be created | Auto-approved, implemented |
| 2 | Finance, Release | Heatmap as one all-time bucket; index `(metric, day)` | Auto-approved, implemented (about 15 rows per page per day now) |
| 3 | Privacy | Clean the old link option in place during migration | Auto-approved, implemented and tested |
| 4 | Release | Re-run the migration after a downgrade and re-upgrade | Auto-approved, implemented (admin page loads) |
| 5 | Release | Write migration rows in chunks to bound memory | Auto-approved, implemented |
| 6 | QA | Keep sparse heatmap cell positions; merge duplicate first-hit meta rows; treat empty values as 0 | Auto-approved, implemented and tested (20 simultaneous first hits on one row) |
| 7 | QA | Lock tests (fresh and stale) | Auto-approved, implemented |
| 8 | Product, Privacy, Support | Add the new options to the opt-in uninstall list | Auto-approved, implemented |
| 9 | Release | Drop a deleted multisite site's tables (`wpmu_drop_tables`) | Auto-approved, implemented |
| 10 | Privacy, Marketing, Support | Policy text "per page and per day (UTC)" and retention statement; uninstall text no longer says "because Landing Page Manager uses them"; README cleanup wording; changelog downgrade and failure notes | Auto-approved, implemented |
| 11 | QA | Comment on `VALUES()` (deprecated in MySQL 8.0.20+, required by MariaDB) | Auto-approved, implemented |
| 12 | Product, Finance | Remove the pre-1.0.5 options in a later release (F16) | Owner decision on timing |
| 13 | Privacy, Product | Should "Delete data on uninstall" drop the Pro-only tables; retention limit for daily rows (P6) | Owner decision; retention needs attorney review |
| 14 | Marketing, Support | 1.0.5 Upgrade Notice recommending a backup | At release |
| 15 | QA | Run the harness on MySQL 8.4 as well as MariaDB | Follow-up |

## Verification after the fixes

- 69 unit tests; PHPCS clean; integration harness passes (tables, migration run twice and merged with live data, sparse heatmap, link option cleaned, lock fresh/stale, 40 simultaneous conversions, 20 simultaneous first hits on one row).
- Real upgrade test: released 1.0.4 with live traffic, then this build: every analytics number identical before and after; later traffic adds correctly.

## Forwarded to owner

- Release 1.0.5?
- P6: drop Pro-only tables on opt-in uninstall? Retention limit?
- F16: when to delete the pre-1.0.5 options.
