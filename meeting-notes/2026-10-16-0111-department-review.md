# Department Review — 2026-10-16 01:11 EDT

**Change reviewed:** pre-release review of everything since the 2026-10-16 00:07 meeting: copyright holder (L5), opt-in uninstall cleanup (P3/F10), the Docker integration harness, the PHPCS ruleset, and the re-ported release workflow (updater standard v1.1.0, R3 and R11). The repository is public.
**Departments:** all nine.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass with follow-ups | Uninstall, nonce exceptions, and workflow permissions sound. Configure the `release` environment; drop persisted credentials in CI. |
| QA | Pass with follow-ups | Harness could pass if the server checks never ran; no real `wp plugin uninstall`; release-notes script edge cases. |
| Legal | Pass with follow-ups | Notices consistent; `uninstall.php` lacked a notice; public plan carries legal and business detail. |
| Privacy | Pass with follow-ups | Add a retention statement to the policy text and uninstall text to readme.txt. |
| Finance | Pass with follow-ups | A client licensing term was in the public plan and notes. |
| Product | Pass with follow-ups | Plan status and wording inconsistencies. |
| Marketing | Pass with follow-ups | Changelog said standard v1.0.0; README release description out of date. |
| Support | Pass with follow-ups | Reinstall after an opt-in delete starts fresh; say so. |
| Release | Pass with follow-ups | Tag must be on `main`; date the changelog first; `release` environment must allow tags; leftover activation flag on uninstall. |

Nothing blocks the 1.0.3 release.

## Recommendations and decisions

| # | Department | Recommendation | Decision |
| --- | --- | --- | --- |
| 1 | QA | Harness fails if server checks do not finish; strict readiness wait; real `wp plugin uninstall` check | Auto-approved, implemented (43 checks pass) |
| 2 | QA, peer session | Release-notes script fails on empty or non-numeric requirements | Done in the reference (gravity-forms-tops PR #5, merged as 62aed18) and re-ported verbatim |
| 3 | Legal | Copyright notice in `uninstall.php`; mark L2 superseded by L5 | Auto-approved, implemented |
| 4 | Release, Support | Delete `lpmanager_pro_activation_pending` too; explain that reinstalling after an opt-in delete starts fresh | Auto-approved, implemented |
| 5 | Privacy | Retention sentence in the suggested policy text; uninstall section in readme.txt | Auto-approved, implemented |
| 6 | Marketing, Support | Changelog: standard v1.1.0, plain "View details" line, uninstall cross-reference; README release and Pro Settings text | Auto-approved, implemented |
| 7 | Security | `persist-credentials: false` in CI | Auto-approved, implemented |
| 8 | Security, Release | Configure the `release` environment to allow only `v*` tags | Auto-approved, done before tagging |
| 9 | Release | Date the changelog and tag `main` after merge | Auto-approved, done as part of the release |
| 10 | Product | Plan consistency (U2 duplicate, U5, unverified list, scope line, checklist wording) | Auto-approved, implemented |
| 11 | Finance | Remove the client licensing term from the public plan and notes | Auto-approved under the confidentiality rule; redacted |
| 12 | Legal | Move open legal questions (L4, L6, L7 substance) and Finance analysis out of the public repository | Owner decision |
| 13 | Legal | Confirm `gravity-forms-tops` is not a client identifier | Owner decision |
| 14 | Security | Required reviewer on the `release` environment and a `v*` tag ruleset | Owner decision (adds a manual approval step to every release) |
| 15 | Support, Marketing | Release notes show as plain text and include GitHub's PR list | Accepted for 1.0.3; any change belongs in the updater standard |

## Forwarded to owner

- Items 12–14 above.
- Still open: L6, P5 (attorney), L7 (GitHub Support purge), C1 (licensing).

## Notes

- After the fixes: `php -l` clean, `composer lint`, `composer phpcs`, and `composer test` (64 tests) pass; `node --check` passes; integration harness 43/43; `bin/release-notes.sh 1.0.3` produces the release body.
- Not verified: multisite uninstall, update from a published release (U5), PHP 7.4 at runtime.
- The loop closes here: the fixes were small, each checked by the harness, unit tests, or the release-notes script.
