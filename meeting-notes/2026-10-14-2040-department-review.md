# Department Review — 2026-10-14 20:40 EDT

**Change reviewed:** re-review of the items implemented after the [15:36 meeting](2026-10-14-1536-department-review.md): B21–B25, R1, R2, R4–R9, F13 (owner-requested consent-plugin notice), L1 (license wording), and P1 (privacy wording). Uncommitted work on `feature/github-updater-standard`.
**Departments:** Security, QA, Legal, Privacy, Finance, Product, Marketing, Support, Release (all nine).

## Owner decisions since the last meeting

- Auto-approved items are implemented immediately, then re-reviewed (the loop). No need to wait for the owner.
- U4: the repository already lives at `duppinstech/landing-page-manager-pro` (transferred from `oduppinsjr`; the old URL redirects). The 404 was because it is private. Resolved.
- U2: the owner is handling ZIP delivery.
- L1: add the proposed license wording. Done.
- P1: make the privacy wording adjustment. Done.
- C3: no customer announcement; the owner manages all client sites. Instead, document the count changes and flag consent plugins where the settings are (F13).

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass with follow-ups | B22–B24 confirmed. Old link rows keep query strings; escaping could exceed the 512 cap. |
| QA | Pass with follow-ups | Nonce wiring correct; PHP 7.4-safe. Asked for `clean_href` tests; manual checks listed below. |
| Legal | Pass with follow-ups | L1 and P1 match the proposed wording. The consent success notice made an unverified claim about third-party plugins; add a trademark line. |
| Privacy | Pass with follow-ups | The WP Consent API alone reports consent for every visitor, so the green notice could be wrong; notice ignored the consent filter. |
| Finance | Pass with follow-ups | Keep the price. Owner-managed deployment makes license keys non-urgent until Pro is sold directly or ZIPs are public. |
| Product | Pass with follow-ups | Notice wording, error styling, list formatting, and accessibility; plan wording fixes. |
| Marketing | Pass with follow-ups | Descriptions agree. "Editors and administrators" understates the exclusion (it covers anyone who can edit content). |
| Support | Pass with follow-ups | Upgrade notes needed for split link rows, the handle rename, and the nonce change. |
| Release | Pass with follow-ups | Exclusion lists and CI check confirmed. Customers never see the changelog: release notes don't include it. |

## Recommendations and decisions

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |
| 1 | Legal, Privacy, Product, Support | Consent notice: don't claim a detected plugin sends consent; use the effective consent value; warn when the API runs without a consent plugin; warning not error for `grantConsent()`; `wp_sprintf_l`; `aria-describedby` | Auto-approved, implemented | B26 |
| 2 | Security, QA | Cut hrefs to 512 after escaping | Auto-approved, implemented | B27 |
| 3 | QA | Unit tests for `clean_href` and the consent notice | Auto-approved, implemented (51 tests pass) | R13 |
| 4 | Marketing | Exclusion wording names contributors and authors; "internal traffic" not "staff"; list privacy signals; "not counted" not "never counted" | Auto-approved, implemented | R12 |
| 5 | Legal | Trademark line; GitHub terms clause in README; `tracking.js` comment | Auto-approved, implemented | R12 |
| 6 | Support | Upgrade notes: split link rows, handle rename for dependencies, nonce change | Auto-approved, implemented | R12 |
| 7 | Privacy, Support | Limit the "no query strings" claim to links recorded from 1.0.3 | Auto-approved, implemented | R12 |
| 8 | Product, Release | Plan and AGENTS.md wording (status line, B21, R1, test coverage, move F13, shorten R10) | Auto-approved, implemented | — |
| 9 | Release | Put the changelog section into the release notes; pass the upgrade notice through the updater | Auto-approved, pending in the updater reference standard | R11 |
| 10 | Security, Privacy, Support, Release | One-time migration to strip and merge pre-1.0.3 link rows | Owner decision (permanently changes stored data); recommended | P4 |
| 11 | Release | Pages cached before 1.0.3 lose events until purged (nonce change) | Kept B23; purge step added to deployment readiness, since the owner manages every site | Deployment Readiness |
| 12 | Privacy, Legal | Whether the "consent detected" notice could be read as a compliance assurance | Owner decision, attorney review | P2 |
| 13 | Finance | Sales model, ZIP visibility, hosting cost questions | Owner decision | C1, C2 |
| 14 | Security, QA | Dequeue misses a legacy script enqueued after `wp_enqueue_scripts` | Declined for now: the current free plugin does not enqueue it, and Pro now rejects the old nonce anyway | — |
| 15 | Release | `Tested up to` still blank | Already planned | U3 |

## Forwarded to owner

- **P4:** approve a one-time cleanup of link rows recorded before 1.0.3? Recommended for privacy and to free slots under the 200-link cap.
- **U2:** release channel; the repository location is settled.
- **C1 / C2:** Finance asks whether Pro is sold only within managed services or also standalone, whether ZIP delivery will be public, and who pays hosting for client sites. Answer outside the repository.
- **P2:** consent defaults, plus whether the consent notice could be read as an assurance (attorney).
- **U3:** choose a `Tested up to` version.

## Notes

- Ran: `php -l` on every changed PHP file (PHP 8.2) and `composer test`: 51 tests, 125 assertions, all passing. PHP 7.4 results come from CI.
- Not run: live WordPress checks. QA's manual list for this round covers a cached page from before the update, the legacy script removal, REST `interactions` with toggles off, each consent-notice state, and empty tables.
- R3 and R11 need changes in the Duppins Technology updater reference standard, then a re-port.
- Next meeting: after the owner decides P4, or when the next change is complete. This round produced auto-approved work (all implemented), so a targeted round 3 (Legal, Privacy, Product, QA) re-checks B26, B27, R12, and R13.
