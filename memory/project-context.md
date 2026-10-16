# Project Context

- Product: Landing Page Manager Pro WordPress plugin (GPL-2.0-or-later from 1.0.3; earlier versions proprietary).
- Owner, author, and copyright holder: Odell Duppins (d/b/a Duppins Technology, a trade name). See `OWNERSHIP.md`.
- Required free dependency: Landing Page Manager.
- Documented minimums: WordPress 5.9 and PHP 7.4.
- GitHub Releases are used for plugin updates.
- CI: `.github/workflows/php-lint.yml` (php -l on 7.4/8.3, a version check, and PHPUnit updater tests via dev-only Composer). No PHPCS yet (2026-10-14).
- Updates: GitHub updater standard v1.0.0 (`includes/class-lpmanager-pro-github-updater.php`, a verbatim copy; do not edit locally). Repository `duppinstech/landing-page-manager-pro` is private, so release delivery is blocked (plan U2).
- Packaging: `bin/build-zip.sh` builds `landing-page-manager-pro.zip` from tracked files minus dev-only paths; `.github/workflows/release.yml` attaches it on `v*` tags.
- Free plugin contracts Pro relies on: `lpmanager_tracking_options()`, `lpmanager_is_premium()` (checks `lpmanager_pro_loaded()`), the `lpmanager_dashboard_data` filter, the `lp_dashboard` menu slug, Carbon Fields theme options `lpmanager_track_*` (registered only when Pro is loaded), and the free plugin's analytics page consuming `lpmanager/v1/analytics-data`.