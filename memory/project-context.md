# Project Context

- Product: proprietary Landing Page Manager Pro WordPress plugin.
- Owner: Odell Duppins / Duppins Technology; see `OWNERSHIP.md`.
- Required free dependency: Landing Page Manager.
- Documented minimums: WordPress 5.9 and PHP 7.4.
- GitHub Releases are used for plugin updates.
- CI: `.github/workflows/php-lint.yml` (php -l on 7.4/8.3 plus a version check). No test suite or PHPCS yet (2026-10-14).
- Packaging: `bin/build-zip.sh` builds from committed files; `.gitattributes` export-ignore keeps dev files out.
- Free plugin contracts Pro relies on: `lpmanager_tracking_options()`, `lpmanager_is_premium()` (checks `lpmanager_pro_loaded()`), the `lpmanager_dashboard_data` filter, the `lp_dashboard` menu slug, Carbon Fields theme options `lpmanager_track_*` (registered only when Pro is loaded), and the free plugin's analytics page consuming `lpmanager/v1/analytics-data`.