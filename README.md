# Landing Page Manager Pro

**Landing Page Manager Pro** is the premium add-on for [Landing Page Manager](https://github.com/oduppinsjr/landing-page-manager). It unlocks analytics, conversion tracking, engagement metrics, and click-through reporting for your landing pages.

## Requirements

- **WordPress** 5.9 or later
- **PHP** 7.4 or later
- **Landing Page Manager** (free) — must be installed and active. Pro will not load without it.
- **Carbon Fields** (or equivalent theme options) — used by the free plugin for tracking settings.

## Features

- **Page view tracking** — Count visits per landing page and daily totals
- **Conversion tracking** — Track conversions (e.g. phone link clicks) and conversion rate
- **Link click tracking** — Per-link click counts and CTR by page
- **Time on page** — Average time spent per page
- **Scroll depth** — 25%, 50%, 75%, 100% scroll milestones
- **Heatmap** — Click heatmap data (grid-based)
- **Rage clicks** — Count repeated clicks in the same area
- **Outbound & CTA visibility** — Optional tracking for outbound links and CTA visibility
- **Form interactions** — Optional form interaction tracking
- **Conversions submenu** — Quick table of visits, conversions, and conversion rate per page
- **REST API** — `GET /wp-json/lpmanager/v1/analytics-data` for dashboard and filtering by client/template
- **Dashboard integration** — Pro data (visits, conversions, summary) merged into the main LP Manager dashboard

## Installation

1. Ensure **Landing Page Manager** (free) is installed and active.
2. Upload the `landing-page-manager-pro` folder to `/wp-content/plugins/`, or install via your preferred method (e.g. private repo, ZIP).
3. Activate **Landing Page Manager Pro** in **Plugins** in the WordPress admin.
4. On first activation, all tracking options are enabled by default. Adjust them in the main Landing Page Manager settings if needed.

## Configuration

Tracking options (page views, link clicks, conversions, time on page, scroll depth, heatmap, rage clicks, etc.) are managed in the **Landing Page Manager** settings (provided by the free plugin). Pro reads these settings and enables the corresponding tracking and analytics.

## Security

- All AJAX handlers use nonce verification (`lpmanager_nonce`).
- REST analytics endpoint is restricted to users with `manage_options`.
- Inputs are sanitized (e.g. `absint`, `sanitize_text_field`, `esc_url_raw`) and post types/taxonomies are validated.

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

## License

Proprietary. See [LICENSE.md](LICENSE.md) for terms.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Author

**Odell Duppins**

- GitHub: [@oduppinsjr](https://github.com/oduppinsjr)
- Repo: [landing-page-manager-pro](https://github.com/oduppinsjr/landing-page-manager-pro)
