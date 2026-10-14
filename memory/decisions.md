# Project Decisions

Record durable project decisions here using this format:

## YYYY-MM-DD: Short decision title

- **Decision:** What was decided.
- **Reason:** Why it was chosen.
- **Impact:** Compatibility, maintenance, or release implications.

## 2026-10-14: Bound public tracking storage

- **Decision:** Cap distinct links stored per page (200), heatmap cells per request (5,000), and stored href length (2,048). Save tracking options with autoload off.
- **Reason:** The recording endpoints are unauthenticated and the nonce is public on every landing page, so unbounded storage let anyone grow autoloaded options.
- **Impact:** Existing data is preserved. Links beyond the cap still count toward page totals. The long-term fix is a custom table (plan F6).

## 2026-10-14: Package from committed files

- **Decision:** Release ZIPs are built with `git archive` (`bin/build-zip.sh`) and exclude development files through `.gitattributes`.
- **Reason:** Repeatable packages, and GitHub zipballs (the updater fallback) get the same exclusions.
- **Impact:** Uncommitted changes are never packaged; commit before building.
