#!/usr/bin/env bash
# Build the release ZIP from committed files (HEAD, or the ref given as $1).
# Output: landing-page-manager-pro-<version>.zip with a single landing-page-manager-pro/ folder.
# Files marked export-ignore in .gitattributes are left out.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"
ref="${1:-HEAD}"
slug="landing-page-manager-pro"
main="$(git show "$ref:$slug.php")"

version="$(printf '%s\n' "$main" | sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' | head -n 1)"
runtime="$(printf '%s\n' "$main" | sed -n "s/.*define( 'LP_MANAGER_PRO_VERSION', '\([^']*\)'.*/\1/p" | head -n 1)"

if [ -z "$version" ]; then
    echo "Could not read the Version header from $slug.php at $ref." >&2
    exit 1
fi
if [ "$version" != "$runtime" ]; then
    echo "Version header ($version) does not match LP_MANAGER_PRO_VERSION ($runtime)." >&2
    exit 1
fi
if [ "$ref" = "HEAD" ] && [ -n "$(git status --porcelain)" ]; then
    echo "Warning: uncommitted changes are not included in the package." >&2
fi

out="$slug-$version.zip"
git archive --format=zip --prefix="$slug/" -o "$out" "$ref"
echo "Built $out from $ref:"
unzip -l "$out"
