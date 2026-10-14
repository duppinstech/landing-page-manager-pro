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

## 2026-10-14: Count views and refresh nonces from the browser

- **Decision:** The tracking script calls `lpmanager_pro_session` on load. It returns a fresh nonce and counts the view. Events wait for the fresh nonce, falling back to the printed one. Owner chose this over dropping nonce checks for anonymous requests.
- **Reason:** Full-page caches served expired nonces (breaking all tracking) and skipped server-side view counting.
- **Impact:** One extra uncached admin-ajax request per page view. Visitors without JavaScript are no longer counted. Anyone could already inflate views by requesting the page; they can now do it slightly more cheaply by calling the session endpoint. `'server'` mode remains available through a filter.
