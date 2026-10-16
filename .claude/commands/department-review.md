---
description: Run every department review (security, QA, legal, privacy, finance, product, marketing, support, release) on the completed change, add the recommendations to plan.md, and record the meeting notes.
argument-hint: "[departments…] [base ref]"
---

Run the department review described in `workflows/department-review.md` on the current change. Treat it as a meeting: the departments report, recommendations are decided, the plan is updated, and notes are recorded.

1. **Identify the change.** Unless arguments name a base ref, use the uncommitted changes plus commits on this branch that are not on `main` (`git diff main...HEAD` and `git status`). Summarize the change in a few sentences and list the changed files. If there is no change, say so and stop.
2. **Choose departments.** If the arguments name departments (security, qa, legal, privacy, finance, product, marketing, support, release), run only those. Otherwise run all nine: `security-reviewer`, `test-writer`, `legal-reviewer`, `privacy-reviewer`, `finance-reviewer`, `product-reviewer`, `marketing-reviewer`, `support-reviewer`, `release-reviewer`.
3. **Run them in parallel** in a single message, one Agent call per department. Give each the change summary, the changed file list, and the relevant diff hunks, and tell it to use its documented output format.
4. **Verify and decide.** Check each finding against the code before accepting it; drop findings that are wrong. Merge duplicates across departments. Give every recommendation one decision using the rules in `meeting-notes/README.md`: auto-approved, owner decision, already planned, or declined (with reason).
5. **Update `plan.md`.** Add auto-approved items to the right section with new stable IDs (continue the existing B/F/U numbering; use R for review-process and documentation items, L for legal, P for privacy). Add owner-decision items under *Owner Decisions* with the recommendation. Reference the meeting file in a dated note.
6. **Write the meeting notes** to `meeting-notes/YYYY-MM-DD-HHMM-department-review.md` (current local time) using the template in `meeting-notes/README.md`. Summarize; do not paste full reports. Never put confidential pricing, revenue, or client-agreement details into repository files.
7. **Implement auto-approved items immediately**, without waiting for the owner. Validate them (`php -l`, `composer test`), update the changelog and plan, and check them off.
8. **Re-review (the loop).** Run the departments again on the new changes and record another meeting. Repeat until a meeting produces no new auto-approved work. Only owner-decision items wait for the owner.
9. **Report** a short summary to the owner: verdicts, what was implemented, and the questions forwarded for their decision.

Do not change product code while the departments are reviewing. Never tag, publish, or deploy as part of the loop.
