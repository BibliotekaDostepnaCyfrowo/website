#!/usr/bin/env bash
# Builds a theme release: the zip WordPress installs and the theme-update.json that
# sites read to find it (theme/biblioteka-dostepna-cyfrowo/inc/updates.php).
# Used by .github/workflows/theme-release.yml; runs locally too. See README.md,
# "Theme releases".
#
# Usage:  wordpress/tools/theme-release.sh <version> <out-dir>
#   version  must equal `Version` in style.css (the tag is theme-v<version>)
#
# Env:    REPO      owner/name the release is published in (default: $GITHUB_REPOSITORY,
#                   else BibliotekaDostepnaCyfrowo/website)
#         BASE_URL  where the zip will be downloaded from, instead of the GitHub
#                   release (for testing a release on a local site)
set -euo pipefail

if (( $# != 2 )); then
  echo "usage: $0 <version> <out-dir>" >&2
  exit 2
fi
VERSION=$1
OUT=$2
SLUG=biblioteka-dostepna-cyfrowo
HERE=$(cd "$(dirname "$0")" && pwd)
THEMES=$HERE/../theme
STYLE=$THEMES/$SLUG/style.css
REPO=${REPO:-${GITHUB_REPOSITORY:-BibliotekaDostepnaCyfrowo/website}}
TAG=theme-v$VERSION

header() { sed -n "s/^$1: *//p" "$STYLE" | head -n 1 | tr -d '\r'; }

if [[ ! "$VERSION" =~ ^[0-9]+(\.[0-9]+){1,3}$ ]]; then
  echo "version must be numbers and dots (e.g. 2.2.0), got '$VERSION'" >&2
  exit 2
fi
if [[ "$(header Version)" != "$VERSION" ]]; then
  echo "style.css says Version: $(header Version), not $VERSION: bump it in the release commit" >&2
  exit 1
fi
if [[ "$(header 'Update URI')" != "https://github.com/$REPO" ]]; then
  echo "style.css needs 'Update URI: https://github.com/$REPO' for sites to find this release" >&2
  exit 1
fi

mkdir -p "$OUT"
OUT=$(cd "$OUT" && pwd)
ZIP=$SLUG-$VERSION.zip
rm -f "$OUT/$ZIP"
# The folder inside the zip is the folder WordPress installs into: it must be the slug.
(cd "$THEMES" && zip -qrX "$OUT/$ZIP" "$SLUG" -x '*/.DS_Store')
SHA=$(shasum -a 256 "$OUT/$ZIP" | cut -d' ' -f1)

jq -n \
  --arg theme "$SLUG" --arg version "$VERSION" \
  --arg package "${BASE_URL:-https://github.com/$REPO/releases/download/$TAG}/$ZIP" \
  --arg sha256 "$SHA" \
  --arg url "https://github.com/$REPO/releases/tag/$TAG" \
  --arg requires "$(header 'Requires at least')" --arg requires_php "$(header 'Requires PHP')" \
  --arg released "$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  '{theme: $theme, version: $version, package: $package, sha256: $sha256, url: $url,
    requires: $requires, requires_php: $requires_php, released: $released}' \
  > "$OUT/theme-update.json"

echo "$OUT/$ZIP"
echo "$OUT/theme-update.json"
