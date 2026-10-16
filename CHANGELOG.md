# Changelog

All notable changes to Landing Page Manager Pro are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added

- **Landing Pages → Pro Analytics**: views, conversions, link clicks, average time on page, scroll depth, rage clicks, outbound clicks, CTA views, and form starts and submissions for each landing page, filterable by client and template. Open a page to see its scroll depth, top links, and heatmap grid. Only enabled tracking features are shown. Administrators only. Analytics are part of Pro only.
- `lpmanager_pro_tracking_options()` returns which tracking features are on. Landing Page Manager uses it, when present, instead of its own settings.

### Changed

- **Tracking options moved to Landing Pages → Pro Settings** ("What is tracked"). They were under Landing Page Manager's settings; your existing choices carry over, because Pro uses the same stored values (an option that was never saved stays on, as before). Conversion tracking is always on; excluded visits are not counted. With Landing Page Manager versions that predate this change, its Conversion Tracking tab still shows; both screens change the same settings, and the last save wins.
- When **Delete data on uninstall** is on, the tracking options are removed too.
- The **Conversions** screen is folded into **Pro Analytics**, which shows the same numbers (views, conversions and conversion rate, outbound clicks, CTA views, form starts and submissions) and more. Old links and bookmarks to it open Pro Analytics.

## [1.0.3] - 2026-10-16

### Highlights

- More accurate numbers: views on cached pages are now counted, and previews, your own logged-in users who can edit content, bots, and visitors who send privacy signals are left out by default.
- Outbound clicks, call-to-action views, and form starts and submissions are now recorded and shown on the Conversions page.
- A new **Landing Pages → Pro Settings** page controls who is tracked, including optional consent through the WP Consent API.
- Updates now come through GitHub Releases, with release notes under "View details".

### Upgrade notes

- **Counts will change from the day you update.** Previews, logged-in users who can edit content (contributors, authors, editors, administrators), bots, and visitors who send Global Privacy Control or Do Not Track are no longer counted. Time on page and heatmap counts are no longer inflated by tab switching. Page views are counted by the tracking script, so pages served from a cache are now counted and visitors without JavaScript are not. Existing data is not changed. Review the exclusions under **Landing Pages → Pro Settings**.
- **Purge your page cache after updating or rolling back**, including any CDN and JavaScript minify or combine caches, so pages load the current tracking script. An old script cannot send the session token, and its events are rejected.
- Each landing-page view now makes one uncached `admin-ajax.php` request.
- "Check for updates" moved to **Dashboard → Updates → Check again**. Two updater filters changed (see Changed).
- The tracking script handle is now `lpmanager-pro-tracking`. Update optimization or minification exclusions that named `lpmanager-tracking`, and any custom script that lists `lpmanager-tracking` as a dependency or adds inline script to it.
- Custom code that calls Pro's recording actions must send the nonce from `lpmanagerTracking.nonce`. Requests signed with `lpmanager_nonce` are now rejected. Pages cached before the update send the old nonce until the cache is purged.
- Stored link-click rows are cleaned once, on the first admin page load after updating: query strings and fragments are removed, labels are capped at 100 characters, and rows for the same link and label are merged with their counts added together. The merge cannot be undone, and rolling back does not restore the old rows; take a database backup first if you need them. After a downgrade and re-upgrade, delete the `lpmanager_pro_link_clicks_cleaned` option so rows recorded by the older version are cleaned too.
- Tracking requests now need the session token issued when the page loads. Events are dropped if the visitor leaves before the session request answers, or if it takes longer than 5 seconds (events after a late answer are still sent). Very short visits may therefore record no time on page, which can raise the average slightly. A tab left open for more than 50 minutes gets a new token when the visitor returns to it.
- Custom code that posts to the recording actions must send `session` as well as `nonce`: POST `action=lpmanager_pro_session&post_id=<ID>&count_view=0` to `admin-ajax.php`, then send `data.session` and `data.nonce` from the response. Tokens last one hour and work only for that page; requests without a valid token return `Invalid session.`
- Conversions now count for the page where the button was clicked. `data-post-id` is ignored, so buttons that pointed at another page credit their own page.

### Fixed

- Visitors on Cubot Android phones are no longer treated as bots and excluded.
- CTA views are recorded for call-to-action buttons added after the page loads (for example by page builders or popups).
- Recording endpoints no longer trust the posted page ID: each request must carry a signed session token for that page, which limits how far counts can be inflated or sent to the wrong page. On sites with a persistent object cache, once-per-page-view events are also limited per session.
- Link-click rows recorded before 1.0.3, which kept query strings, are cleaned and merged on update.
- The analytics REST endpoint's `interactions` now leaves out interaction types whose tracking is off, matching the daily series.
- First-activation tracking defaults are now applied. Previously they ran before Carbon Fields registered its fields, so they failed (and raised a Carbon Fields error under `WP_DEBUG`).
- Deactivating and reactivating Pro no longer turns every tracking option back on.
- Conversions are only recorded for published landing pages; any other post ID is rejected.
- Click, engagement, heatmap, rage-click, and page-view recording now require a published landing page. A malformed (non-string) `href` no longer causes a PHP error.
- Link-click storage is capped at 200 distinct links per page. Stored link addresses are limited to 512 characters and saved without query strings or fragments, which can carry visitor tokens or email addresses; links that differed only by query string are now counted together. Clicks beyond the cap still count toward the page's total clicks.
- Heatmap requests are capped at 600 samples (one page view), and time-on-page values over one hour are dropped.
- Tracking data options (`lpmanager_link_clicks`, `lpmanager_engagement`, `lpmanager_heatmap`, `lpmanager_daily_visits`, `lpmanager_daily_conversions`) are saved with autoload off, so they no longer load on every request.
- Empty engagement pings (CTA visibility, form interactions) no longer rewrite the engagement option.
- Time on page is recorded once per page view; previously every tab switch sent another measurement, inflating averages.
- Heatmap data is sent once per page view; previously the grid was re-sent, uncleared, on every tab switch.
- Post previews no longer count as page views.
- Tracking works on pages served from a full-page cache. The script fetches a fresh nonce on load; previously a cached page could carry an expired nonce, and every tracking request from it failed.
- Dashboard charts no longer merge landing pages that share a title; duplicates are labeled with their post ID.
- The Conversions admin page and submenu now use the `landing-page-manager-pro` text domain and translatable strings.
- The analytics REST endpoint's `daily_visits` and `daily_conversions` now respect the tracking toggles, matching the dashboard.
- When the free plugin's template loader is unavailable, analytics group pages under the `default` template instead of a client-specific slug.

### Added

- `lpmanager_pro_should_count_page_view` filter to control whether a landing page request counts as a view.
- "View details" for Landing Page Manager Pro on the Plugins screen now shows the latest GitHub release notes.
- WordPress's auto-update toggle is now available for the plugin.
- `Requires at least: 5.9` and `Requires PHP: 7.4` plugin headers.
- Privacy options on Pro Settings. *Honor privacy signals* (on by default) stops tracking visitors whose browser sends Global Privacy Control or Do Not Track. *Require consent* (off by default) makes tracking wait for WP Consent API "statistics" consent or `window.lpmanagerPro.grantConsent()`. Pro registers with the WP Consent API and adds suggested privacy-policy text under Settings → Privacy. New filter: `lpmanager_pro_require_consent`.
- **Delete data on uninstall** option on Pro Settings (off by default). When on, deleting the plugin also removes Pro's own settings and options on that site (each site of a multisite network decides for itself). Analytics counts shared with Landing Page Manager are always kept. If you reinstall afterwards, Pro starts fresh: Pro Settings return to their defaults and all tracking options are turned on again.
- Pro Settings shows whether a consent or cookie-banner plugin (Complianz, CookieYes, Cookiebot, and others) is active and whether Pro will wait for its consent, and explains how the exclusions affect counts. It also confirms when settings are saved.
- The Conversions and Interactions tables show a message when there are no landing pages.
- **Landing Pages → Pro Settings** page with tracking exclusions, both on by default: logged-in users who can edit content, and known bots or automation tools (including requests with no user agent). Excluded visitors are not tracked at all. Add custom rules with the `lpmanager_pro_exclude_request` filter.
- Outbound-click, CTA-visibility, and form-interaction tracking now record data (new `lpmanager_record_interaction` AJAX action; post meta `_lpmanager_outbound_clicks`, `_lpmanager_cta_views`, `_lpmanager_form_starts`, `_lpmanager_form_submits`). Counts appear in a new Interactions table on the Conversions page and as `interactions` in the analytics REST response, and `tracking_enabled` reports the three toggles.

### Changed

- Plugin updates now use the Duppins Technology GitHub updater standard v1.1.0 (`LPManager_Pro_GitHub_Updater`) and come from the GitHub Releases of `duppinstech/landing-page-manager-pro`. The `Update URI` header stops WordPress.org from offering updates for this slug.
  - The updater loads with the plugin, so WP-Cron and background auto-updates see new releases, even while the free plugin is inactive.
  - Only the release asset `landing-page-manager-pro.zip` is installed (or a release's only `.zip` asset), never a source zipball.
  - Results are cached for 6 hours, then rechecked with `If-None-Match`. Failures wait 1 hour, and GitHub rate limits wait until their reset (at most 1 day).
  - The "Check for updates" plugin action link is removed. Use **Dashboard → Updates → Check again** instead.
  - The `lpmanager_pro_github_releases_api_url` and `lpmanager_pro_github_update_package_url` filters are removed. New filters are `lpmanager_pro_github_updater_config` and `lpmanager_pro_github_release_http_args`. Define `LPMANAGER_PRO_DISABLE_GITHUB_UPDATER` as `true` to turn updates off.
  - The cache moved from the `lpmanager_pro_github_release` site transient to the site option of the same name. A new `uninstall.php` deletes both and leaves tracking data in place; Pro's own settings are also removed when **Delete data on uninstall** is on (see Added).
- Page views are now counted by the tracking script, through a new `lpmanager_pro_session` request on page load, instead of on `template_redirect`. Cached pages are now counted, and visitors without JavaScript (mostly bots) no longer are, so view counts may shift after updating. With the new default exclusions (see Added), visits by editors, administrators, and bots also stop being counted. Use the `lpmanager_pro_page_view_counting` filter (`'server'`) to restore the previous behavior. `lpmanager_pro_should_count_page_view` still applies in both modes.
- Pro now ships its own front-end tracking script instead of loading the free plugin's `assets/js/tracking.js`. It has no jQuery dependency and uses `navigator.sendBeacon` for data sent as the visitor leaves, so those requests are not cancelled by navigation.
- Heatmap mouse samples are throttled to one per 100 ms, so new heatmap counts are lower in absolute terms than before; the relative distribution is unaffected.
- Public tracking requests use their own nonce action, `lpmanager_pro_tracking`, instead of sharing `lpmanager_nonce` with the free plugin's admin requests. Pro's script is enqueued as `lpmanager-pro-tracking`, and an older free-plugin `lpmanager-tracking` script is dequeued so only the consent-aware script runs.
- Suggested privacy-policy text and the Require consent description no longer make absolute claims; they describe what is and is not stored, and that consent requirements depend on applicable law.
- **Licensed under GPL-2.0-or-later** from this version (earlier versions were proprietary). The full text ships as `license.txt`. The product names are not licensed under the GPL, and license keys, updates, and support are provided under a separate agreement with Duppins Technology. LICENSE.md keeps a warranty disclaimer and limitation of liability.
- The plugin description now lists all tracking features.
- Copyright notices name Odell Duppins (d/b/a Duppins Technology), the copyright holder.
- "View details" now shows this version's changes after the requirements.
- `Tracking_Manager` moved to the `LPManagerPro` namespace. `LPManager\admin\Tracking_Manager` remains as an alias.

## [1.0.2] - 2026-03-09

### Changed

- Restored the `Requires Plugins: landing-page-manager` header so WordPress enforces the dependency in the plugin screens. Runtime dependency checks remain in place.

## [1.0.1] - 2026-03-09

### Added

- GitHub Releases update checker with a "Check for updates" plugin action link.
- Update source-folder normalization so updates install into `landing-page-manager-pro`.

### Changed

- Activation now requires the free Landing Page Manager plugin to be active (runtime check instead of header-only enforcement).

### Fixed

- Tracking requests use a same-origin AJAX URL to avoid cross-origin failures on subdomains.

## [1.0.0] - 2026-03-08

### Added

- Initial release.
- Page view tracking and daily visit totals.
- Conversion tracking with conversions submenu and conversion rate.
- Link click tracking (per link and per page) with CTR.
- Time-on-page and scroll-depth engagement tracking.
- Heatmap tracking (grid-based).
- Rage click tracking.
- Optional outbound click and CTA visibility tracking settings (data was not recorded until 1.0.3).
- Optional form interaction tracking setting (data was not recorded until 1.0.3).
- REST endpoint `GET /wp-json/lpmanager/v1/analytics-data` with client and template filters.
- Dashboard integration: Pro data (visits, conversions, summary) added to main LP Manager dashboard.
- Activation hook to enable all tracking options on first activation.
- Dependency on free Landing Page Manager plugin with admin notice when missing.
