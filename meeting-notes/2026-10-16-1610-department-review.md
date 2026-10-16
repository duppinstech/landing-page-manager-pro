# Department Review — 2026-10-16 16:10 EDT

**Change reviewed:** the F6 follow-ups from the 15:45 meeting (fail-safe setup, all-time heatmap, re-migration, chunked migration, duplicate-row merge).
**Departments:** Release and QA (targeted; they raised the original items).

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Release | Pass with follow-ups | Fixes correct. A failed CREATE or migration retried on every request; malformed date keys could trigger re-migration on every admin load. |
| QA | Pass with follow-ups | The counter UPDATE hit every duplicate row (possible double count); privacy text overstated "per day" and "until an administrator removes them". |

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | Release | Retry a failed CREATE on admin loads or at most hourly | Auto-approved, implemented |
| 2 | Release, QA | Keep the migration lock after a failure so retries wait ten minutes | Auto-approved, implemented |
| 3 | Release | Ignore malformed date keys in the re-migration check | Auto-approved, implemented |
| 4 | QA | Increment only the first counter row (`ORDER BY meta_id LIMIT 1`); test it | Auto-approved, implemented and tested |
| 5 | QA | Policy text: "most of them per day"; drop "until an administrator removes them" (no removal tool yet, F8) | Auto-approved, implemented |
| 6 | Release | Re-migration only follows the daily series and misses same-day 1.0.4 data | Accepted limitation; documented in the plan runbook (force with `wp option delete lpmanager_pro_storage_migrated`) |
| 7 | Release | Drop round-1 dev tables (old index) on test sites | Not applicable to customer sites (unreleased) |

## Notes

- After the fixes: 69 unit tests, PHPCS clean, integration harness passes (including "counter update touches only the first row", 40 simultaneous conversions, 20 simultaneous first hits).
- The loop closes here: each follow-up is small and covered by tests.
