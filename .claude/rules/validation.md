# Validation

- First identify the directly affected behavior and choose the narrowest available check.
- CI runs `php -l` (PHP 7.4 and 8.3), a version-consistency check, and the PHPUnit tests (`composer test`) for the GitHub updater, Pro Settings, and `Tracking_Manager::clean_href`. The tracking handlers, REST, admin screens, and uninstall are covered by `bash tests/integration/run.sh` (Docker, run locally, not in CI); browser-side tracking.js behavior has no automated test. There is no PHPCS ruleset yet. Do not invent successful results for tools that are not configured.
- Run `php -l` on modified PHP files when PHP CLI is available. For behavior changes, exercise the affected path in WordPress with the free dependency active.
- Check dependency-missing behavior for activation or loader changes.
- Review changed files, generated artifacts, secrets, version metadata, and changelog entries before finishing.
- State exactly which checks ran and which remain unverified.