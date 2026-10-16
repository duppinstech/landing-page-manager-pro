# Department Review — 2026-10-14 15:36 EDT

**Change reviewed:** 1.0.3 release candidate, branch `feature/github-updater-standard` vs `main` (8 commits: B1–B19, F1–F4, U1 updater port, CI and release tooling, docs). First department review.
**Departments:** Security, QA, Legal, Privacy, Finance, Product, Marketing, Support, Release (all nine).

## Verdicts

| Department | Verdict | Summary |
| --- | --- | --- |
| Security | Pass with follow-ups | No blocking issues. The shared link-click option can be grown by anyone; the public tracking nonce shares an action with admin AJAX; counts can be faked. |
| QA | Pass with follow-ups | Manual WordPress verification is still open; no automated coverage of tracking or settings. Found a bot-pattern false positive. |
| Legal | Needs owner decision | LICENSE.md is too thin for a sold product; copyright holder named inconsistently; absolute privacy claims; client name would become public with U2. |
| Privacy | Needs owner decision | No blocking defect; consent and exclusions apply on every endpoint. Wording overstates "no personal data"; stored link URLs can carry personal data. |
| Finance | Needs owner decision | Value-add release; keep the price. No entitlement check exists, so the U2 choice sets revenue risk. |
| Product | Pass with follow-ups | Fits intent. Needs an upgrade notice, settings-saved message, table empty states, and plan wording fixes. |
| Marketing | Pass with follow-ups | Heatmap described wrongly as clicks; header, readme.txt, and README disagree on features. Announcement recommended. |
| Support | Pass with follow-ups | Customers are not told in one place that counts will drop; filters are undocumented. |
| Release | Needs owner decision | U2 and U3 block delivery. Changelog still `[Unreleased]`; CI does not check the Stable tag. |

## Recommendations and decisions

Verified against the code before deciding. Plan IDs refer to [plan.md](../plan.md).

| # | Department | Recommendation | Decision | Plan item |
| --- | --- | --- | --- | --- |
| 1 | QA | Bot pattern matches `bot` anywhere (Cubot phones excluded) | Auto-approved | B21 |
| 2 | Security, Privacy | Cap stored hrefs at 512, strip query/fragment; move link clicks per page | Auto-approved; per-page part already planned | B22, F6 |
| 3 | Security | Separate nonce action for public tracking | Auto-approved | B23 |
| 4 | Privacy | Unique script handle so the consent-aware Pro script always runs | Auto-approved (free plugin's current code does not enqueue the old handle; older versions might) | B24 |
| 5 | QA | REST `interactions` should respect toggles like B15 | Auto-approved | B25 |
| 6 | QA | Settings unit tests | Auto-approved | R4 |
| 7 | Product | `settings_errors()`, empty states, `scope="col"` | Auto-approved | R5 |
| 8 | Support, Marketing, Release, Product | Customer-language changelog with upgrade notes; rename `[Unreleased]` | Auto-approved | R6 |
| 9 | Support, Product, Legal | readme.txt Upgrade Notice; GitHub API disclosure | Auto-approved | R7 |
| 10 | Support, Marketing, Legal | README: filters, Pro Settings, uninstall, heatmap and nonce corrections | Auto-approved | R8 |
| 11 | Marketing | One feature description across header, readme.txt, README | Auto-approved | R9 |
| 12 | Product, Marketing | Free plugin should display interactions, heatmap, and rage-click data | Auto-approved as a free-plugin follow-up | R10 |
| 13 | Release | Sync `.gitattributes` with `bin/build-zip.sh` | Auto-approved (`meeting-notes/` added to both today) | R1 |
| 14 | Release | CI should check `readme.txt` Stable tag | Auto-approved | R2 |
| 15 | Security | Pin workflow actions to SHAs | Auto-approved, in the updater standard upstream | R3 |
| 16 | Product, QA | Expand manual verification checklist | Auto-approved | 1.0.3 manual verification item |
| 17 | Release | Staged 1.0.2 → 1.0.3 upgrade test | Auto-approved | U5 |
| 18 | Product | Fix F3 and F10 wording in the plan | Auto-approved, applied | F3, F10 |
| 19 | Release, Finance, Legal | Release channel; plus the meeting found the updater points at `duppinstech/…` (404) while the code is at `oduppinsjr/…` | Owner decision | U2 + U4 |
| 20 | Finance | Add entitlement (license keys or licensed channel) | Owner decision | C1 |
| 21 | Finance | Keep price for 1.0.3; revisit tiers after C1 | Owner decision | C2 |
| 22 | Finance, Marketing, Support | Warn clients about count changes; publish announcement | Owner decision | C3 |
| 23 | Legal | Expand LICENSE.md | Owner decision, attorney review | L1 |
| 24 | Legal | Consistent copyright holder; headers in shipped files | Owner decision, attorney review | L2 |
| 25 | Legal, Release | Client names before going public; OWNERSHIP.md in ZIP | Owner decision | L3 |
| 26 | Legal | GPL derivative-work question | Owner decision, counsel | L4 |
| 27 | Legal, Privacy, Marketing | Soften absolute privacy and consent claims | Owner decision, attorney review | P1 |
| 28 | Privacy | Consent default, consent category, GPC override, trackers on at activation | Owner decision, attorney review | P2 |
| 29 | Privacy, Product | Retention and uninstall scope | Owner decision | P3 |
| 30 | Security | Document or rate-limit fake traffic | Owner decision | S1 |
| 31 | Security, Privacy | Retention/reset and concurrency gaps | Already planned | F8, F10, B11 |
| 32 | QA, Marketing | Conversion tracking missing from activation defaults | Declined: the free plugin forces `enable_conversion_tracking` on whenever Pro is active | — |
| 33 | Product | Interactions hidden when conversion tracking is off | Declined: same reason; the Conversions page is always registered with Pro | — |
| 34 | Release | Add `meeting-notes/` to exclusion lists | Done during the meeting | — |

## Forwarded to owner

- **U2 + U4 (release channel):** recommendation is to transfer the repository to `duppinstech` (GitHub redirects keep 1.0.2 sites working) and publish ZIPs from a public release-only repository until licensing exists. Do not make the source public before L1.
- **C1 / C2 (entitlement and pricing):** keep the 1.0.3 price. Finance asks for the billing model, paying-site count, target market, and whether agreements include updates (answer outside the repository).
- **C3 (announcement):** draft for approval:
  > Landing Page Manager Pro 1.0.3 makes your landing page numbers more accurate. Views are now counted even on cached pages, and visits from bots and logged-in editors and administrators are left out by default. A new Pro Settings page lets you change these exclusions. Pro now records outbound clicks, call-to-action views, and form starts and submissions, shown in a new Interactions table on the Conversions page. Visitors whose browsers send Global Privacy Control or Do Not Track are not tracked by default, and you can require consent through plugins that support the WP Consent API. Because of these changes, your view counts may shift after you update.
- **L1 (license):** proposed additions are grant scope, "viewing or downloading the Software, including from a public repository, does not grant a license", an "as is" warranty disclaimer, a limitation of liability, customer responsibility for privacy notices and consent, termination on breach, and separately licensed dependencies (WordPress, Landing Page Manager).
- **P1 (privacy wording):** proposed replacements:
  - Policy text: "It is designed to store only aggregate counts per page. It does not set cookies or store IP addresses or user agents. The user agent and privacy-signal headers are checked on each request to filter bots and honor opt-outs, and are not stored."
  - Consent option help: "Do not track until the visitor consents to statistics, through a WP Consent API plugin or a banner that calls `window.lpmanagerPro.grantConsent()`. Whether consent is required depends on the laws that apply to your site."
- **L2, L3, L4, P2, P3, S1:** as listed in the plan's Owner Decisions.

## Notes

- Not checked: live WordPress behavior, the built ZIP, CI results, and the updater compared byte-for-byte with the reference standard. No code was run during the meeting.
- Finance used public pricing pages, checked 2026-10-14: Independent Analytics Pro ($49–$199/yr) and MonsterInsights (third-party listing, $199–$799/yr). Freemius fee is 7% plus card fees.
- Next meeting: after B21–B25 and R-items are implemented, or when owner decisions change scope.
