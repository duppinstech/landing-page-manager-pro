# Meeting Notes

Every department review (`/department-review`) is recorded here as a meeting, so there is a history of what each department found, what it recommended, and what was decided.

- One file per meeting, named `YYYY-MM-DD-HHMM-department-review.md` (local time, 24-hour clock).
- Notes are summaries, not transcripts: a few lines per department.
- Never edit a past meeting's notes. Later decisions go in a later meeting, or in a dated note in [plan.md](../plan.md).
- Keep confidential business details (prices, revenue, client agreements, legal strategy) out of these notes; the repository may become public (plan item U2). Write "discussed with owner" instead.
- This folder is development-only and is excluded from the release ZIP and source zipballs.

## Decision rules

Each recommendation gets exactly one decision:

- **Auto-approved**: within the existing intent, plan, and rules, and changes nothing listed below. Added to [plan.md](../plan.md) and implemented immediately, then re-reviewed at the next meeting.
- **Owner decision**: forwarded to the owner and added to plan.md under *Owner Decisions*. Required for pricing, tiers, or licensing terms; wording in LICENSE.md or other legal text; collecting, retaining, or sending new data; repository visibility or the release channel; new dependencies or requirement changes; breaking public hooks, options, REST, or stored data; anything that costs money or is communicated to customers or clients; publishing a release.
- **Already planned**: duplicates an existing plan item; that item's ID is noted.
- **Declined**: with a one-line reason (out of scope, incorrect, or not worth the cost).

## Template

```markdown
# Department Review — YYYY-MM-DD HH:MM TZ

**Change reviewed:** branch, commit range, or plan items
**Departments:** Security, QA, Legal, Privacy, Finance, Product, Marketing, Support, Release

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |

## Recommendations and decisions

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |

## Forwarded to owner

- Question, with the departments' recommendation.

## Notes

- Anything not checked, and follow-up for the next meeting.
```
