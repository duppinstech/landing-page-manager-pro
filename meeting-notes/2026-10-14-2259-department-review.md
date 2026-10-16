# Department Review — 2026-10-14 22:59 EDT

**Change reviewed:** GPL-2.0-or-later relicensing for 1.0.3 (L4), plus Finance's comparison of licensing and update-delivery options (C1).
**Departments:** Legal, Release (targeted), Finance (comparison requested by the owner).

## Owner decisions since the last meeting

- L4: switch to the GPL in 1.0.3.
- C1: compare licensing options with confirmed fees before choosing.

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Legal | Pass with follow-ups | Notice wording and consistency correct. `license.txt` must ship; the names clause should allow GPL-required notices. |
| Release | Pass with follow-ups | Header and readme changes don't affect CI, release parsing, or the updater; composer.lock unaffected. `license.txt` must be committed. |
| Finance | Needs owner decision | Comparison of five options from official pricing pages; recommends Freemius. |

## Recommendations and decisions

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |
| 1 | Legal, Release | Commit `license.txt` before building; confirm it is in the ZIP | Auto-approved; happens with the commit | 1.0.3 checklist |
| 2 | Legal | Names clause must not restrict GPL-required notices or accurate "based on" statements | Auto-approved, implemented | L4 |
| 3 | Legal | Client agreements under the old proprietary terms vs. GPLv2 section 6 | Owner decision, attorney review | L6 |
| 4 | Legal | The shared GitHub updater becomes available under the GPL by shipping here | Owner decision | L6 |
| 5 | Finance | Corrected Freemius pricing: 3.8% + 50¢, +2.3% for WordPress, plus card fees (both earlier figures were wrong) | Recorded | C1 |
| 6 | Finance | Choose Freemius (merchant of record, keys, private updates, no fixed cost); EDD + Software Licensing if volume exceeds roughly $18–19k a year or lock-in matters more | Owner decision | C1 |

## Finance comparison (summary)

| Option | Fees (official, 2026-10-14) | Merchant of record | Keys and updates | Effort |
| --- | --- | --- | --- | --- |
| Freemius | 3.8% + 50¢, +2.3% WordPress, + card fees | Yes | Yes; private updates | Low (SDK replaces the GitHub updater for this product) |
| EDD + Software Licensing | about $599/yr + Stripe | No | Yes; updater snippet, ZIP on duppinstech.com | Medium |
| WooCommerce + License Manager | $130–250/yr + Stripe | No | Keys yes; updates not confirmed | Medium–high |
| Lemon Squeezy / Paddle + own endpoint | 5% + 50¢ | Yes | Keys (Lemon Squeezy only); endpoint to build | Medium–high |
| Self-hosted + Stripe | 2.9% + 30¢ and add-ons | No | Build everything | Highest |

Sources: official pricing pages for Freemius, Easy Digital Downloads, License Manager for WooCommerce, Lemon Squeezy, Paddle, and Stripe, all checked 2026-10-14. Every option sends the site URL and license key on activation and update checks, so readme.txt must disclose it. Ask Freemius in writing for its card fee and whether opt-in telemetry can be turned off.

## Forwarded to owner

- **C1:** choose the licensing channel; decide whether managed client sites get licenses; estimate standalone volume and the non-US share.
- **L6:** attorney check of client agreements against the GPL; accept or not that the shared updater is GPL-licensed when shipped here.
- Still open: U2, U3, P2, P3, L5, P5.

## Notes

- `composer test`: 61 tests pass; `php -l` clean; `composer validate` passes. `license.txt` is the official GPLv2 text from gnu.org (338 lines).
- The loop closes here: the one auto-approved fix (names clause) is a single sentence and needs no further review.
