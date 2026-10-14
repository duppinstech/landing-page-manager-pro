#!/usr/bin/env bash
# Build landing-page-manager-pro.zip from tracked files only, excluding development files.
# Used by .github/workflows/release.yml; run locally with `bash bin/build-zip.sh` to inspect the package.
set -euo pipefail

cd "$(dirname "$0")/.."
rm -rf build landing-page-manager-pro.zip
mkdir -p build/landing-page-manager-pro

# Tracked files only (never local settings, vendor/, or untracked scratch files), minus dev-only paths.
git ls-files -z \
  | grep -zvE '^(\.github/|\.claude/|tests/|bin/|docs/|tools/|workflows/|memory/|\.gitignore$|\.gitattributes$|AGENTS\.md$|CLAUDE\.md$|CONTRIBUTING\.md$|intent\.md$|plan\.md$|composer\.(json|lock)$|phpcs\.xml\.dist$|phpunit\.xml\.dist$)' \
  | xargs -0 -I{} cp --parents {} build/landing-page-manager-pro/

( cd build && zip -qr9 ../landing-page-manager-pro.zip landing-page-manager-pro )
unzip -l landing-page-manager-pro.zip
