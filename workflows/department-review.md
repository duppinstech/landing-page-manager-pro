# Department Review Workflow

After development and validation are finished, every change goes through a department review before it is reported as done or released. Each department is a read-only agent in `.claude/agents/`; run them all with `/department-review`. Each run is a meeting, recorded in [meeting-notes/](../meeting-notes/).

| Department | Agent | Decides |
| --- | --- | --- |
| Security | `security-reviewer` | Authorization, nonces, input/output handling, data exposure |
| Quality assurance | `test-writer` | Regression tests and manual WordPress checks |
| Legal | `legal-reviewer` | Licensing, ownership, third-party licenses, disclaimers, claims |
| Privacy and compliance | `privacy-reviewer` | Data collected, consent, retention, privacy-policy text |
| Finance | `finance-reviewer` | Pricing, tiers, entitlement, operating cost, revenue risk |
| Product | `product-reviewer` | Fit with intent and plan, scope, upgrade impact, UX, accessibility, i18n |
| Marketing | `marketing-reviewer` | Accuracy of feature claims, release announcement |
| Support and documentation | `support-reviewer` | Changelog, upgrade notes, docs, customer FAQ |
| Operations and release | `release-reviewer` | Versions, packaging, CI, updater, migrations, rollback |

## Steps

1. Finish implementation and the validation in [feature-build.md](feature-build.md) or [bug-fix.md](bug-fix.md).
2. Run `/department-review` (optionally naming departments, for example `/department-review legal finance`). All departments run in parallel on the same change; a department the change does not affect answers in one line.
3. Verify each finding against the code, merge duplicates, and decide each recommendation using the rules in [meeting-notes/README.md](../meeting-notes/README.md): **auto-approved**, **owner decision**, **already planned**, or **declined**.
4. Add auto-approved items to [plan.md](../plan.md) with stable IDs, and forwarded items under *Owner Decisions*.
5. Record the meeting in `meeting-notes/YYYY-MM-DD-HHMM-department-review.md`: what each department found, what was recommended, and what was decided.
6. Implement auto-approved items immediately; the owner does not need to be asked. Fix **Blocking** findings before the change is reported as done.
7. Re-review: run the departments again on the new changes and record another meeting. The loop ends when a meeting produces no new auto-approved work; owner decisions wait for the owner.

## Rules

- Reviewers do not edit files. Approved items are implemented between meetings.
- Legal and privacy output is not legal advice; items marked **Needs attorney review** go to counsel.
- Confidential business information (prices, revenue, client agreements, legal strategy) stays out of the repository, which may become public (plan item U2).
