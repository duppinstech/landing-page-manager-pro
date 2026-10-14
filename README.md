# Landing Page Manager Pro

**Landing Page Manager Pro** is the premium add-on for [Landing Page Manager](https://github.com/oduppinsjr/landing-page-manager). It unlocks analytics, conversion tracking, engagement metrics, and click-through reporting for your landing pages.

## Requirements

- **WordPress** 5.9 or later
- **PHP** 7.4 or later
- **Landing Page Manager** (free) — must be installed and active. Pro will not load without it.
- **Carbon Fields** (or equivalent theme options) — used by the free plugin for tracking settings.

## Features

- **Page view tracking** — Count visits per landing page and daily totals. Views are counted by the tracking script, so pages served from a full-page cache are still counted (set the `lpmanager_pro_page_view_counting` filter to `server` to count on page render instead)
- **Conversion tracking** — Track conversions (e.g. phone link clicks) and conversion rate
- **Link click tracking** — Per-link click counts and CTR by page
- **Time on page** — Average time spent per page
- **Scroll depth** — 25%, 50%, 75%, 100% scroll milestones
- **Heatmap** — Click heatmap data (grid-based)
- **Rage clicks** — Count repeated clicks in the same area
- **Outbound clicks & CTA visibility** — Count clicks to other domains, and page views in which a call-to-action became visible
- **Form interactions** — Count form starts (first field focused) and submissions
- **Conversions submenu** — Visits, conversions, and conversion rate per page, plus an interactions table (outbound clicks, CTA views, form starts/submits)
- **REST API** — `GET /wp-json/lpmanager/v1/analytics-data` for dashboard and filtering by client/template
- **Dashboard integration** — Pro data (visits, conversions, summary) merged into the main LP Manager dashboard

## Installation

1. Ensure **Landing Page Manager** (free) is installed and active.
2. Upload the `landing-page-manager-pro` folder to `/wp-content/plugins/`, or install via your preferred method (e.g. private repo, ZIP).
3. Activate **Landing Page Manager Pro** in **Plugins** in the WordPress admin.
4. On first activation, all tracking options are enabled by default. Adjust them in the main Landing Page Manager settings if needed.

## Building a Release Package

Run `bash bin/build-zip.sh` from a clean checkout. It writes `landing-page-manager-pro.zip` (ignored by Git) from tracked files, with a single `landing-page-manager-pro/` folder and no development files. Pushing a `v*` tag runs `.github/workflows/release.yml`, which checks the tag against the plugin version, runs lint and tests, and attaches the ZIP to the GitHub release. See [workflows/deployment.md](workflows/deployment.md).

## Updates

Pro updates itself from the GitHub Releases of `duppinstech/landing-page-manager-pro` (the Duppins Technology GitHub updater standard). **Dashboard → Updates → Check again** forces a check. To turn updates off, add `define( 'LPMANAGER_PRO_DISABLE_GITHUB_UPDATER', true );` to `wp-config.php`. Sites can only download releases while the repository is public.

## Configuration

Tracking options (page views, link clicks, conversions, time on page, scroll depth, heatmap, rage clicks, etc.) are managed in the **Landing Page Manager** settings (provided by the free plugin). Pro reads these settings and enables the corresponding tracking and analytics.

**Landing Pages → Pro Settings** controls whose visits are tracked. By default, logged-in users who can edit content (editors, administrators) and known bots or automation tools are excluded. Developers can add rules, such as office IP ranges, with the `lpmanager_pro_exclude_request` filter.

## Privacy and Consent

Pro stores only aggregate counts per landing page. It sets no cookies and stores no IP addresses, user agents, form contents, or other personal data. Suggested privacy-policy text is added under **Settings → Privacy**.

- **Privacy signals** (on by default): visitors whose browser sends Global Privacy Control or Do Not Track are not tracked.
- **Require consent** (off by default): tracking waits for consent. It works with consent plugins that support the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) ("statistics" category). For a custom banner, call `window.lpmanagerPro.grantConsent()` or `window.lpmanagerPro.revokeConsent()`, or set `window.lpmanagerProConsent = true` before the tracking script loads. The `lpmanager_pro_require_consent` filter can also turn this on.

## Security

- All AJAX handlers use nonce verification (`lpmanager_nonce`).
- REST analytics endpoint is restricted to users with `manage_options`.
- Public recording endpoints accept only published landing pages, and per-page link storage and heatmap payloads are bounded.
- Inputs are sanitized (e.g. `absint`, `sanitize_text_field`, `esc_url_raw`) and post types/taxonomies are validated.

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

## License

Proprietary. See [LICENSE.md](LICENSE.md) for terms.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Author

**Odell Duppins** / **Duppins Technology** (https://duppinstech.com). See [OWNERSHIP.md](OWNERSHIP.md).

- GitHub: [@oduppinsjr](https://github.com/oduppinsjr)
- Repo: [landing-page-manager-pro](https://github.com/duppinstech/landing-page-manager-pro)
