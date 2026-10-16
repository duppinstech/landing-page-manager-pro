# Landing Page Manager Pro

**Landing Page Manager Pro** is the premium add-on for [Landing Page Manager](https://github.com/oduppinsjr/landing-page-manager). It adds page views, conversions, link clicks, engagement, heatmaps, rage clicks, outbound clicks, CTA views, and form tracking for Landing Page Manager, with bot and internal-traffic exclusion and privacy and consent controls.

## Requirements

- **WordPress** 5.9 or later
- **PHP** 7.4 or later
- **Landing Page Manager** (free) — must be installed and active. Pro will not load without it.
- **Carbon Fields** — used by Landing Page Manager itself; Pro does not need it.

## Features

- **Page view tracking** — Count visits per landing page and daily totals. Views are counted by the tracking script, so pages served from a full-page cache are still counted (set the `lpmanager_pro_page_view_counting` filter to `server` to count on page render instead)
- **Conversion tracking** — Conversions (phone and call-button clicks) and conversion rate
- **Link click tracking** — Per-link click counts and CTR by page
- **Time on page** — Average time spent per page
- **Scroll depth** — 25%, 50%, 75%, 100% scroll milestones
- **Heatmap** — Where visitors move the mouse, as a 10x10 grid of the visible window (not clicks)
- **Rage clicks** — Count repeated clicks in the same area
- **Outbound clicks & CTA visibility** — Count clicks to other domains, and page views in which a call-to-action became visible
- **Form interactions** — Count form starts (first field focused) and submissions
- **Pro Analytics screen** (administrators) — Views, conversions, link clicks, average time on page, scroll depth, rage clicks, outbound clicks, CTA views, and form starts and submissions for each landing page, filterable by client and template; each page shows its top links and heatmap grid
- **Conversions submenu** — Visits, conversions, and conversion rate per page, plus an interactions table (outbound clicks, CTA views, form starts/submits)
- **REST API** — `GET /wp-json/lpmanager/v1/analytics-data` for dashboard and filtering by client/template
- **Dashboard integration** — Pro data (visits, conversions, summary) merged into the main LP Manager dashboard. Heatmap, rage-click, engagement, and interaction data are shown under **Landing Pages → Pro Analytics** and available through the REST API.
- **Tracking exclusions** — Editors and administrators, bots, and visitors who send privacy signals are not tracked (configurable)
- **Consent controls** — Optionally wait for visitor consent through the WP Consent API or a JavaScript API

## Installation

1. Ensure **Landing Page Manager** (free) is installed and active.
2. Upload the `landing-page-manager-pro` folder to `/wp-content/plugins/`, or install via your preferred method (e.g. private repo, ZIP).
3. Activate **Landing Page Manager Pro** in **Plugins** in the WordPress admin.
4. On first activation, all tracking options are enabled by default. Adjust them under **Landing Pages → Pro Settings → What is tracked**.
5. Review **Landing Pages → Pro Settings**, which controls who is tracked (editors, bots, privacy signals, consent).

Upgrading from 1.0.2: counts may be lower after updating, because previews, logged-in users who can edit content (contributors, authors, editors, administrators), bots, and visitors who send privacy signals are no longer counted. Views on cached pages are now counted. Purge the page cache after updating or rolling back, so cached pages load the current tracking script.

## Building a Release Package

Run `bash bin/build-zip.sh` from a clean checkout. It writes `landing-page-manager-pro.zip` (ignored by Git) from tracked files, with a single `landing-page-manager-pro/` folder and no development files. Pushing a `v*` tag runs `.github/workflows/release.yml`, which checks that the tag is on `main` and matches the plugin version, requires a dated CHANGELOG section for the version, runs lint, PHPCS, and tests, and attaches the ZIP with build provenance to the GitHub release. See [workflows/deployment.md](workflows/deployment.md).

## Updates

Pro updates itself from the GitHub Releases of `duppinstech/landing-page-manager-pro` (the Duppins Technology GitHub updater standard). **Dashboard → Updates → Check again** forces a check. To turn updates off, add `define( 'LPMANAGER_PRO_DISABLE_GITHUB_UPDATER', true );` to `wp-config.php`. Sites can only download releases while the repository is public. Update checks contact `api.github.com`, which sees the site's IP address; GitHub's terms and privacy statement apply.

Deleting the plugin removes its update cache. Turn on **Delete data on uninstall** in Pro Settings to also remove Pro's own settings and options. Analytics counts are always kept, because Landing Page Manager uses them too, so a reinstall keeps your history. If you reinstall afterwards, Pro starts fresh: Pro Settings return to their defaults and all tracking options are turned on again.

## Configuration

Tracking options (page views, link clicks, time on page, scroll depth, heatmap, rage clicks, outbound clicks, CTA views, form interactions) are managed under **Landing Pages → Pro Settings → What is tracked**. Conversion tracking is always on; excluded visits are not counted. Before 1.0.4 these options were in the Landing Page Manager settings; Pro reads and writes the same stored values, so existing choices carry over.

## Functions

- `lpmanager_pro_tracking_options()` returns an array of booleans keyed `track_page_views`, `track_link_clicks`, `track_time_on_page`, `track_scroll_depth`, `track_heatmap`, `track_rage_clicks`, `track_outbound_clicks`, `track_cta_visibility`, and `track_form_interactions`, plus `enable_conversion_tracking` (always `true`). An option that was never saved reads as on. Returns an empty array when Landing Page Manager is not active.

**Landing Pages → Pro Settings** controls whose visits are tracked, and whether deleting the plugin removes its settings. By default, logged-in users who can edit content (contributors, authors, editors, administrators), known bots or automation tools, and visitors who send Global Privacy Control or Do Not Track are excluded. Excluded visits are not counted. When a consent or cookie-banner plugin is detected, the page shows whether Pro will wait for its consent. Product names are trademarks of their respective owners and are used only to identify those plugins; no affiliation or endorsement is implied.

## Filters

- `lpmanager_pro_page_view_counting` (string, default `'client'`): return `'server'` to count views when the page is rendered, as before 1.0.3. When **Require consent** is on, server mode counts no views.
- `lpmanager_pro_should_count_page_view` (bool `$count`, int `$post_id`; previews default to `false`): whether a request counts as a view. In client mode it runs during the tracking script's AJAX request.
- `lpmanager_pro_exclude_request` (bool, result of the Pro Settings rules): return `true` to skip tracking, for example for office IP ranges.
- `lpmanager_pro_require_consent` (bool, default is the **Require consent** setting).
- `lpmanager_pro_github_updater_config`, `lpmanager_pro_github_release_http_args`: change the update source or the HTTP request.

## Privacy and Consent

Pro is designed to store only aggregate counts per landing page. It sets no cookies and does not store IP addresses, user agents, or form contents. Link addresses are stored without query strings or fragments (addresses recorded before 1.0.3 are cleaned once, on the first admin page load after updating). Avoid putting personal data in link URLs. The user agent and privacy-signal headers are checked on each request to filter bots and honor opt-outs, and are not stored. Each page view gets a random identifier that stays in the browser's memory; it is not stored in cookies or local storage and is not linked to an IP address. On sites with a persistent object cache it may be kept there for up to one hour to limit repeat events. Whether your site needs visitor consent depends on the laws that apply to it. Suggested privacy-policy text is added under **Settings → Privacy**.

- **Privacy signals** (on by default): visitors whose browser sends Global Privacy Control or Do Not Track are not tracked.
- **Require consent** (off by default): tracking waits for consent. It works with consent plugins that support the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) ("statistics" category). For a custom banner, call `window.lpmanagerPro.grantConsent()` or `window.lpmanagerPro.revokeConsent()`, or set `window.lpmanagerProConsent = true` before the tracking script loads. The `lpmanager_pro_require_consent` filter can also turn this on.

## Security

- Recording handlers verify a nonce (`lpmanager_pro_tracking`). The `lpmanager_pro_session` request issues a fresh nonce and accepts only published landing pages.
- REST analytics endpoint is restricted to users with `manage_options`.
- Public recording endpoints accept only published landing pages, and per-page link storage and heatmap payloads are bounded.
- Each page view gets a signed session token from `lpmanager_pro_session` (valid for one hour, for that page only). Custom code gets one by POSTing `action=lpmanager_pro_session&post_id=<ID>&count_view=0` to `admin-ajax.php` and sending `data.session` and `data.nonce` with each event. Every recording endpoint requires it, so requests cannot target another page or reuse the shared nonce alone. The token itself is not stored. On sites with a persistent object cache, its random ID and per-event counters are held in the cache for up to one hour.
- On sites with a persistent object cache, each page view can record time on page, the heatmap, each scroll depth, and a CTA view once, and other events up to 10 times. Without an object cache these limits are skipped rather than stored in the database.
- Analytics counts are unauthenticated estimates: anyone who can load a landing page can send tracking requests. For stronger protection, rate-limit `admin-ajax.php?action=lpmanager_pro_session` at the web server or firewall.
- Inputs are sanitized (e.g. `absint`, `sanitize_text_field`, `esc_url_raw`) and post types/taxonomies are validated.

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

## License

GPL-2.0-or-later, from version 1.0.3 (earlier versions were proprietary). The names "Landing Page Manager Pro" and "Duppins Technology" are not licensed under the GPL. License keys, updates, and support are provided under a separate agreement with Duppins Technology. See [LICENSE.md](LICENSE.md).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Author

**Odell Duppins** / **Duppins Technology** (https://duppinstech.com). See [OWNERSHIP.md](OWNERSHIP.md).

- GitHub: [@oduppinsjr](https://github.com/oduppinsjr)
- Repo: [landing-page-manager-pro](https://github.com/duppinstech/landing-page-manager-pro)
