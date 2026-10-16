# Department Review — 2026-10-14 22:50 EDT

**Change reviewed:** targeted re-review of the items implemented after the [20:40 meeting](2026-10-14-2040-department-review.md): B26 (consent notice accuracy), B27 (href length after escaping), R12 (wording), R13 (unit tests).
**Departments:** Legal, Privacy, Product, QA, the departments whose findings drove those items. The other five had no open items on these changes.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Legal | Pass with follow-ups | Round-2 items resolved. One remaining flat claim in the Require consent help text. |
| Privacy | Pass with follow-ups | Round-2 items resolved. The "no consent plugin" warning should say "known", since detection uses a fixed list. |
| Product | Pass with follow-ups | Round-2 items resolved. The notice could contradict the checkbox when a filter overrides it. |
| QA | Pass | Tests are correct, PHP 7.4-compatible, and the doubles do not conflict. The length test fails under the old order, so it guards B27. |

## Recommendations and decisions

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |
| 1 | Legal | "stores only aggregate counts" → "is designed to store only aggregate counts" in the Require consent help | Auto-approved, implemented | R12 |
| 2 | Privacy | Say "no known consent plugin" and qualify the consequence | Auto-approved, implemented | B26 |
| 3 | Product | Explain in the notice when the `lpmanager_pro_require_consent` filter overrides the checkbox | Auto-approved, implemented | B28 |

## Forwarded to owner

- Nothing new. Still open: P4 (clean up pre-1.0.3 link rows), U2 (release channel), U3 (`Tested up to`), C1/C2 (sales model questions), P2 (consent defaults, attorney), L2–L4, S1, P3.

## Notes

- After the fixes: `php -l` clean and `composer test`: 51 tests, 125 assertions, all passing (PHP 8.2). PHP 7.4 results come from CI.
- The loop closes here. This round's three changes were one-line wording and a notice sentence, checked by lint and tests; none changes behavior a department reviewed. The next meeting runs after the next change, or after the owner decides P4.
- Not run: live WordPress checks (see the 20:40 meeting and the plan's manual verification item).
