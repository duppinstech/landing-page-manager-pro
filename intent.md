# Project Intent

## Purpose

Provide the premium analytics and tracking extension for Landing Page Manager while keeping the free plugin as a separate required dependency. The Pro plugin adds page and engagement metrics, conversion reporting, and related dashboard/API capabilities.

## Product Boundaries

- This repository contains only the proprietary Pro plugin.
- The free Landing Page Manager plugin remains independently maintained and must be installed and active for Pro functionality.
- Preserve existing user data, settings, integrations, and documented API behavior unless an intentional migration is part of the work.
- Keep tracking behavior secure, privacy-conscious, and consistent with WordPress capability, nonce, sanitization, and escaping conventions.
- Maintain the currently documented minimums: WordPress 5.9 and PHP 7.4.

## Definition of Done

- The change solves the stated user or maintenance need without unrelated refactoring.
- Relevant PHP syntax and behavior have been checked, and any unavailable validation is reported.
- User-visible changes are recorded in `CHANGELOG.md`.
- Release metadata and package contents are checked when preparing a release.
- No secrets, local environment files, or unintended generated packages are committed.

## Ownership

Source ownership remains with Odell Duppins / Duppins Technology. See [LICENSE.md](LICENSE.md) for the terms governing use and distribution.