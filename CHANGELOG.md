# Changelog

All notable changes to Landing Page Manager Pro are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Fixed

- First-activation tracking defaults are now applied. Previously they ran before Carbon Fields registered its fields, so they failed (and raised a Carbon Fields error under `WP_DEBUG`).
- Deactivating and reactivating Pro no longer turns every tracking option back on.
- Conversions are only recorded for published landing pages; any other post ID is rejected.
- Click, engagement, heatmap, rage-click, and page-view recording now require a published landing page. A malformed (non-string) `href` no longer causes a PHP error.
- Link-click storage is capped at 200 distinct links per page, and stored URLs are length-limited. Clicks beyond the cap still count toward the page's total clicks.
- Heatmap requests are capped at 5,000 cells.
- Tracking data options (`lpmanager_link_clicks`, `lpmanager_engagement`, `lpmanager_heatmap`, `lpmanager_daily_visits`, `lpmanager_daily_conversions`) are saved with autoload off, so they no longer load on every request.
- Empty engagement pings (CTA visibility, form interactions) no longer rewrite the engagement option.
- Time on page is recorded once per page view; previously every tab switch sent another measurement, inflating averages.
- Heatmap data is sent once per page view; previously the grid was re-sent, uncleared, on every tab switch.
- Post previews no longer count as page views.
- Tracking works on pages served from a full-page cache. The script fetches a fresh nonce on load; previously a cached page could carry an expired nonce, and every tracking request from it failed.
- Dashboard charts no longer merge landing pages that share a title; duplicates are labeled with their post ID.
- The GitHub release lookup is cached (6 hours, 1 hour after a failure) instead of calling the API on every update-transient write. "Check for updates" clears the cache.
- The Conversions admin page and submenu now use the `landing-page-manager-pro` text domain and translatable strings.
- The analytics REST endpoint's `daily_visits` and `daily_conversions` now respect the tracking toggles, matching the dashboard.
- When the free plugin's template loader is unavailable, analytics group pages under the `default` template instead of a client-specific slug.

### Added

- `lpmanager_pro_should_count_page_view` filter to control whether a landing page request counts as a view.
- "View details" for Landing Page Manager Pro on the Plugins screen now shows the latest GitHub release notes.
- WordPress's auto-update toggle is now available for the plugin.
- `Requires at least: 5.9` and `Requires PHP: 7.4` plugin headers.
- Outbound-click, CTA-visibility, and form-interaction tracking now record data (new `lpmanager_record_interaction` AJAX action; post meta `_lpmanager_outbound_clicks`, `_lpmanager_cta_views`, `_lpmanager_form_starts`, `_lpmanager_form_submits`). Counts appear in a new Interactions table on the Conversions page and as `interactions` in the analytics REST response, and `tracking_enabled` reports the three toggles.

### Changed

- Page views are now counted by the tracking script, through a new `lpmanager_pro_session` request on page load, instead of on `template_redirect`. Cached pages are now counted, and visitors without JavaScript (mostly bots) no longer are, so view counts may shift after updating. Use the `lpmanager_pro_page_view_counting` filter (`'server'`) to restore the previous behavior. `lpmanager_pro_should_count_page_view` still applies in both modes.
- Pro now ships its own front-end tracking script instead of loading the free plugin's `assets/js/tracking.js`. It has no jQuery dependency and uses `navigator.sendBeacon` for data sent as the visitor leaves, so those requests are not cancelled by navigation.
- Heatmap mouse samples are throttled to one per 100 ms, so new heatmap counts are lower in absolute terms than before; the relative distribution is unaffected.
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
