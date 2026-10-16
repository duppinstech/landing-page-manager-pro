# Department Review — 2026-10-16 00:07 EDT

**Change reviewed:** manual verification of 1.0.3 in a disposable WordPress site, `Tested up to: 7.1` (U3), and the CTA-visibility fix for CTAs added after load (B31).
**Departments:** QA (targeted; no other department's area changed).

## Owner decisions since the last meeting

- Commit the work and continue on open items. Committed as four focused commits on `feature/github-updater-standard`; nothing pushed or tagged.

## Verification (summary)

Disposable WordPress 7.1.3 / PHP 8.2 in Docker with Landing Page Manager 1.0.6. Passed: missing-dependency block, activation defaults, no PHP notices with `WP_DEBUG`, new script handle only, all tracking endpoints including token rejection and exclusions, stored data and autoload, REST permissions and toggle filtering, the P4 cleanup (skipped on AJAX, merged on admin), admin pages, and a headless Chrome run including consent gating. Full list in [plan.md](../plan.md) under 1.0.3. The packaged file list includes `license.txt` and `LICENSE.md` and no development files.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| QA | Pass with follow-ups | B31 correct; the no-IntersectionObserver fallback missed CTAs already on screen when tracking starts after load. |

## Recommendations and decisions

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |
| 1 | QA (found during verification) | Observe CTAs inserted after load | Auto-approved, implemented, verified in headless Chrome | B31 |
| 2 | QA | Fallback path: check CTAs once at startup | Auto-approved, implemented | B31 |
| 3 | QA | List browser-unverified events (form start/submit, rage clicks) and the fallback path | Auto-approved, implemented | 1.0.3 verification |
| 4 | Release (from evidence) | `Tested up to: 7.1` after verifying on 7.1.3 | Auto-approved, implemented | U3 |
| 5 | — | Pin actions and changelog release notes (R3, R11) | Pending in the updater reference (`gravity-forms-tops`); outside this repository | R3, R11 |

## Forwarded to owner

- Unchanged: C1 (licensing channel), U2 (release channel while private), L5, L6, P2, P3, P5.

## Notes

- `composer test`: 61 tests pass; `node --check assets/js/tracking.js` passes.
- The loop closes here: the follow-up was a one-line fallback call, covered by the syntax check and the browser run of the main path.
