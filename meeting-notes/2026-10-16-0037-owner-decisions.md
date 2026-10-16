# Owner Decisions — 2026-10-16 00:37 EDT

**Format:** question-and-answer session with the owner to clear every item waiting on a decision. No department agents ran; recommendations came from earlier meetings.

## Decisions

| Item | Question | Decision |
| --- | --- | --- |
| C1 | Licensing channel and client licenses | Defer licensing; details recorded outside the repository (redacted 2026-10-16 under the confidentiality rule) |
| U2 | Release channel while private | Make this repository public — **done** |
| L3 | Client name in Git history | Rewrite history first — **done**: all branches and tags rewritten and force-pushed; v1.0.1 and v1.0.2 release ZIPs replaced with copies differing only in the fallback slug |
| L7 | Cached pre-rewrite commits on GitHub | Go public now and ask GitHub Support to purge (owner sends the request) |
| L5 | Copyright holder | Duppins Technology is a trade name; notices now name Odell Duppins (d/b/a Duppins Technology) |
| L6 | Shared updater under the GPL | Accepted; client-agreement check stays with counsel |
| P3 | Uninstall behavior | Opt-in "Delete data on uninstall", Pro-only data |
| P2 | Privacy defaults | Keep current defaults; revisit with an attorney before EU-focused sales |
| — | PHPCS dev dependency | Approved |
| — | Integration-test harness | Approved: commit the Docker setup as `tests/integration/` |
| F5–F11 | Roadmap after 1.0.3 | F5, F6, F7, F8, F9, F11 approved, each with a department review |
| R3, R11 | Updater reference changes | Approved on a branch in `gravity-forms-tops`, then re-port |
| R10 | Free-plugin display of Pro data | Approved on a branch in `landing-page-manager` |
| Release | 1.0.3 | After P3, a passing review, and green CI: merge PR #1 and push tag `v1.0.3` |

## Verification

- Before going public: no secrets, keys, or email addresses in files or history; no client name in any ref, commit message, release asset, PR, or issue.
- After going public: the anonymous GitHub releases API returns v1.0.2 and its asset; the old `oduppinsjr` URL used by 1.0.2's checker redirects (HTTP 200).

## Still open (attorney)

- L6 client agreements vs. GPLv2 section 6; P5 session identifier under ePrivacy.
