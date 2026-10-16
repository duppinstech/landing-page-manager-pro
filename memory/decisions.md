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
- **Superseded** by "Adopt the GitHub updater standard" below. The build now uses `git ls-files` with an exclusion list, and zipballs are never installed.

## 2026-10-14: Adopt the GitHub updater standard

- **Decision:** Replace `LPManagerPro\Update_Checker` with a verbatim copy of the Duppins Technology GitHub updater standard v1.0.0 (`LPManager_Pro_GitHub_Updater`, owner `duppinstech`, prefix `lpmanager_pro`), plus its release workflow and build script.
- **Reason:** One shared, tested updater across Duppins Technology plugins, with no drift. The class is global, not namespaced, because the reference uses unqualified `stdClass` and `WP_Error`, which would need edits inside a namespace.
- **Impact:** Only the `landing-page-manager-pro.zip` release asset installs. The manual "Check for updates" link and two filters are removed. The updater cannot read private repositories, and this repository is private, so release delivery is blocked until the owner decides (plan U2).

## 2026-10-14: Count views and refresh nonces from the browser

- **Decision:** The tracking script calls `lpmanager_pro_session` on load. It returns a fresh nonce and counts the view. Events wait for the fresh nonce, falling back to the printed one. Owner chose this over dropping nonce checks for anonymous requests.
- **Reason:** Full-page caches served expired nonces (breaking all tracking) and skipped server-side view counting.
- **Impact:** One extra uncached admin-ajax request per page view. Visitors without JavaScript are no longer counted. Anyone could already inflate views by requesting the page; they can now do it slightly more cheaply by calling the session endpoint. `'server'` mode remains available through a filter.

## 2026-10-14: Privacy defaults

- **Decision:** Honor Global Privacy Control / Do Not Track by default (owner decision). Do not require consent by default; offer an opt-in "Require consent" setting that integrates with the WP Consent API and a small JS API.
- **Reason:** Tracking sets no cookies and stores no personal data, so consent is not required by default, but sites with stricter policies can gate it.
- **Impact:** Visitors sending GPC or DNT are no longer counted. With consent required, server-mode view counting is skipped, because consent is only known in the browser.
