# Department Review — 2026-10-14 22:53 EDT

**Change reviewed:** work done after the owner's decisions on P4, S1, L2, L3, and the sales model: the one-time link-row cleanup (P4), signed session tokens and event limits (S1), Duppins Technology as copyright holder (L2), and the client-agnostic repository (L3).
**Departments:** Security, QA, Legal, Privacy, Release, Support, Product. Finance and Marketing had nothing new to review; Finance and Legal also gave the recommendations the owner asked for (below).

## Owner decisions since the last meeting

- P4: approved; clean up pre-1.0.3 link rows.
- Cache purge: the owner will purge after updating.
- Sales model: Pro is sold within managed services and standalone, installed on top of the free plugin (Elementor Pro model).
- L2: Duppins Technology holds the copyright.
- L3: the repository is client-agnostic.
- L4 and S1: follow Legal's and Security's recommendations.

## Recommendations requested by the owner

- **Security (S1):** signed, stateless session tokens required by every recording endpoint; tighter heatmap and time caps; once-per-session limits only with a persistent object cache; no IP hashing; document that counts are unauthenticated estimates. Implemented.
- **Legal (L4):** relicense the shipped code under GPL-2.0-or-later, as Elementor Pro, Gravity Forms, ACF Pro, and WP Rocket do, and protect revenue through the name, license keys, updates, and support. **Not applied:** it reverses the proprietary terms just added and cannot be undone for a released version, so it waits for the owner's explicit confirmation.
- **Finance (C1, C2):** Freemius as a paid-only add-on for license keys and update delivery (resolves U2 without a public repository); Elementor-style per-site tiers, with export, UTM attribution, and scheduled reports in the top tier. Spend, the new dependency, and prices are the owner's call. Finance's two reports disagree on the Freemius fee (7% vs 4.7%); confirm on freemius.com.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass with follow-ups | Token scheme correct. Cleanup could race a tracking request; session endpoint is still unlimited. |
| QA | Pass with follow-ups | Same cleanup race; asked for limit, boundary, and label-key tests. |
| Legal | Pass with follow-ups | Ownership wording inconsistencies; attorney question on whether a trade name can hold copyright. |
| Privacy | Pass with follow-ups | Disclose the per-page-view identifier; README contradicted itself on storage. |
| Release | Pass with follow-ups | Cleanup on a huge option, downgrade and re-upgrade, CDN and minify caches. |
| Support | Pass with follow-ups | Docs didn't say how custom code gets a token; conversion crediting and merge irreversibility missing. |
| Product | Pass with follow-ups | Tokens expire after an hour and drop conversions in long-open tabs; plan wording. |

## Recommendations and decisions

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |
| 1 | Security, QA, Release, Product | Skip the cleanup on admin-ajax; re-read and re-clean after saving | Auto-approved, implemented | B29 |
| 2 | Product | Renew the token when a visitor returns to a tab older than 50 minutes | Auto-approved, implemented | B30 |
| 3 | QA | Tests: per-session limits with an object cache, token time boundaries, label-cap keys | Auto-approved, implemented (61 tests pass) | R14 |
| 4 | Support | Docs: how custom code gets a token, the 5-second window, conversion crediting, irreversible merge, readme upgrade notice | Auto-approved, implemented | R14 |
| 5 | Privacy | Disclose the per-page-view identifier in the policy text and README; fix README storage wording | Auto-approved, implemented | R14 |
| 6 | Legal | LICENSE grants come from the copyright holder; OWNERSHIP wording | Auto-approved, implemented | R14 |
| 7 | Release | Purge CDN and minify caches too; check option size before updating; recovery and re-upgrade steps | Auto-approved, added to Deployment Readiness | — |
| 8 | Product | Plan wording (B19, C2 F5, status) | Auto-approved, implemented | — |
| 9 | Security, QA | Per-IP limit on the session endpoint | Declined: it would break the no-IP-storage claim; documented, with an optional firewall limit | S1 follow-up |
| 10 | Legal | Can a trade name hold copyright; author assignment | Owner decision, attorney review | L5 |
| 11 | Privacy | Session identifier under ePrivacy; old query strings in pre-1.0.3 backups | Owner decision, attorney review | P5 |

## Forwarded to owner

- **L4:** confirm (or decline) the GPL relicensing before it is applied.
- **C1 / C2:** approve Freemius (spend, new dependency, Privacy review of its opt-in screens) and set tier prices outside the repository.
- **L5, P5:** attorney questions above.
- Still open: U2, U3, P2, P3.

## Notes

- After the fixes: `php -l` clean, `node --check assets/js/tracking.js` passes, `composer test`: 61 tests, 165 assertions, all passing (PHP 8.2). PHP 7.4 results come from CI.
- Not run: live WordPress checks. QA's list for this round: token in the session response; tampered or expired token rejected; slow session (>5 s); conversion credited to its own page; pre-1.0.3 row cleaned and merged; with Redis or Memcached, the 11th click and the second heatmap return "Limit reached".
- The client template slug still exists in Git history (L3); removing it needs a history rewrite.
- The loop closes here: the round-4 follow-ups were small, targeted fixes with tests, and no department raised a blocking issue. The next meeting runs after the owner's L4 and C1 decisions or the next change.
