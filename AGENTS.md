# Repository Guide

## Project

Landing Page Manager Pro is a proprietary WordPress plugin owned by Odell Duppins / Duppins Technology. It extends the separately maintained Landing Page Manager plugin. See [intent.md](intent.md) and [LICENSE.md](LICENSE.md).

## Working Rules

- Preserve compatibility with WordPress 5.9+ and PHP 7.4+ unless the owner approves a requirement change.
- Keep changes focused on this Pro plugin. Do not modify or assume ownership of the free plugin repository.
- Follow WordPress PHP conventions and the local patterns in `includes/`. Keep public hooks, option names, REST routes, and stored data backward compatible unless a change is intentional and documented.
- Validate capabilities, nonces, input, and output at the appropriate boundary. Escape output and sanitize input using WordPress APIs.
- Update [CHANGELOG.md](CHANGELOG.md) for user-visible changes. Keep the plugin header and runtime version consistent when preparing a release.
- Do not add dependencies, credentials, production data, or machine-specific configuration without an explicit need.
- Treat `.claude/` and `workflows/` as contributor guidance, not as evidence that automation or tests exist.

## Validation

- CI (`.github/workflows/php-lint.yml`) runs `php -l` on PHP 7.4 and 8.3, checks that the header and runtime versions match, and runs the PHPUnit tests (`composer test`). The tests cover only the GitHub updater; no PHPCS configuration exists yet.
- The GitHub updater (`includes/class-lpmanager-pro-github-updater.php`) is a verbatim copy of the Duppins Technology updater standard. Do not edit it here; change the reference implementation and re-port it.
- For PHP changes, run `php -l` on each modified PHP file when PHP CLI is available.
- For behavioral changes, verify the affected behavior in a WordPress install with the free Landing Page Manager dependency active; include a missing-dependency check when loader or activation behavior changes.
- Review the final diff for unintended files, secrets, generated ZIPs, and version/changelog inconsistencies.
- Record checks that could not be run. Do not claim tests passed when no test suite is configured.

## Git and Releases

- Keep generated `.zip` packages out of Git; the existing ignore rule is intentional. `bin/build-zip.sh` builds `landing-page-manager-pro.zip` from tracked files minus its development-only exclusion list; `.gitattributes` `export-ignore` rules keep the same files out of GitHub source zipballs. Keep both lists in sync.
- Use focused commits that describe the change. Do not commit secrets, local settings, or unreviewed release artifacts.
- Releases are distributed through GitHub Releases and are consumed by the plugin updater. Follow [workflows/deployment.md](workflows/deployment.md); publishing a release or pushing a tag requires explicit authorization.

## Guidance Index

- [Coding style](.claude/rules/code-style.md)
- [WordPress security](.claude/rules/wordpress-security.md)
- [Validation](.claude/rules/validation.md)
- [Release process](.claude/rules/release-process.md)
- [Current plan](plan.md)