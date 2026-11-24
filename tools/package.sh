#!/usr/bin/env bash
#
# Build a versioned, host-ready release archive (review P3-1).
#
# WHAT THIS PRODUCES
#   dist/princebyron-<version>.zip          the archive you upload
#   dist/princebyron-<version>.zip.sha256   its checksum, for verifying the upload
#   dist/VERSION                            the version, so the archive is traceable
#
# WHY IT REFUSES TO RUN SOMETIMES
#   The promise is "upload it to cPanel and it works, with no Composer and no
#   Node on the host". That is only true if the archive contains vendor/ and the
#   Filament assets. Both are build products that cannot be produced here (no PHP
#   runtime), so this script checks for them and STOPS with an exact instruction
#   rather than shipping an archive that fails on the owner's host.
#
#   A package that silently omits vendor/ is worse than no package: the failure
#   happens on the live site, in a browser, with no terminal to debug it.
#
# USAGE
#   tools/package.sh                 build the archive (fails if prerequisites missing)
#   tools/package.sh --verify-only   run only the prerequisite and content checks
#
# Build the prerequisites on a machine with PHP 8.5 + Composer + Node:
#   cd core && composer install --no-dev --optimize-autoloader
#   php artisan filament:assets
#   npm ci && npm run build
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

DIST="$ROOT/dist"
VERIFY_ONLY=0
[[ "${1:-}" == "--verify-only" ]] && VERIFY_ONLY=1

red()  { printf '\033[31m%s\033[0m\n' "$*"; }
grn()  { printf '\033[32m%s\033[0m\n' "$*"; }
ylw()  { printf '\033[33m%s\033[0m\n' "$*"; }
hdr()  { printf '\n\033[1m%s\033[0m\n' "$*"; }

fail() { red "BLOCKED: $*"; exit 1; }

hdr "1. Prerequisites that only exist on a build machine"

PREREQ_FAILED=0

if [[ ! -d core/vendor ]]; then
  red "  missing core/vendor/"
  red "    -> cd core && composer install --no-dev --optimize-autoloader"
  PREREQ_FAILED=1
else
  grn "  core/vendor/ present"
  # vendor must be production-only. Shipping dev dependencies on a shared host
  # wastes quota and puts PHPUnit, Faker and Pint inside the web-accessible tree.
  if [[ -d core/vendor/phpunit ]]; then
    red "  core/vendor/ contains dev dependencies (phpunit found)"
    red "    -> re-run: composer install --no-dev --optimize-autoloader"
    PREREQ_FAILED=1
  fi
fi

if [[ ! -d core/public/css/filament && ! -d core/public/js/filament ]]; then
  red "  missing Filament admin assets (core/public/css|js/filament)"
  red "    -> cd core && php artisan filament:assets"
  PREREQ_FAILED=1
else
  grn "  Filament admin assets present"
fi

if [[ ! -f core/public/build/manifest.json ]]; then
  red "  missing core/public/build/manifest.json"
  red "    -> cd core && npm ci && npm run build"
  PREREQ_FAILED=1
else
  grn "  Vite build output present"
fi

if [[ $PREREQ_FAILED -eq 1 ]]; then
  hdr "Cannot build a runnable archive yet"
  ylw "The archive would install, but it would fail on the host."
  ylw "Nothing has been written to dist/. Run the commands above, then re-run this script."
  exit 1
fi

hdr "2. Content rules (reuses the CI checks)"

node tools/check-package.mjs || fail "packaging rules are violated — fix the findings above first"
node tools/check-assets.mjs  || fail "build output is missing or over budget"
node tools/check-release.mjs --strict || fail "release checks failed"

if [[ $VERIFY_ONLY -eq 1 ]]; then
  hdr "Verify-only run complete"
  grn "All prerequisites and content rules pass. Nothing was written."
  exit 0
fi

hdr "3. Version"

# Prefer an explicit override, then the git tag, then the short SHA. The version
# goes into the archive name and a VERSION file so a deployed site can be traced
# back to a commit — otherwise "which release is live?" is unanswerable.
VERSION="${VERSION:-}"
if [[ -z "$VERSION" ]]; then
  VERSION="$(git describe --tags --always --dirty 2>/dev/null || date -u +%Y%m%d%H%M)"
fi
grn "  version: $VERSION"

ZIP="princebyron-${VERSION}.zip"
mkdir -p "$DIST"
rm -f "$DIST/$ZIP" "$DIST/$ZIP.sha256"

hdr "4. Staging"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
APP="$STAGE/princebyron-${VERSION}"
mkdir -p "$APP"

# Copied explicitly, never `cp -r core/*`. An allowlist cannot accidentally pick
# up .env, a local SQLite database, node_modules or a stray scratch file.
# core/vendor is the whole point of the archive: the host has no Composer.
# It was missing from the first draft and the extraction test below caught it —
# which is exactly why the test exists.
COPY=(index.php .htaccess core/app core/artisan core/bootstrap core/config
      core/database core/public core/resources core/routes core/storage core/vendor
      core/.htaccess core/.env.example core/composer.json core/composer.lock
      install)

# cp -a rather than rsync: rsync is not installed by default on macOS, on many
# minimal Linux images, or in the CI container this was first run in — and a
# packaging script that needs a package manager to work is not portable. The
# paths below are explicit, so there is nothing for an exclude list to do.
for item in "${COPY[@]}"; do
  [[ -e "$item" ]] || fail "expected path is missing: $item"
  mkdir -p "$APP/$(dirname "$item")"
  cp -a "$item" "$APP/$(dirname "$item")/"
done

# Runtime directories must exist and be empty: the archive is read-only content.
mkdir -p "$APP/core/storage/framework/cache/data" \
         "$APP/core/storage/framework/sessions" \
         "$APP/core/storage/framework/views" \
         "$APP/core/storage/logs" \
         "$APP/core/bootstrap/cache"

# Things that must never ship, even if someone created them locally.
rm -rf "$APP/core/node_modules" "$APP/core/.env" "$APP/core/.phpunit.cache"
find "$APP" -name '*.sqlite' -o -name '*.sqlite3' -o -name '.DS_Store' -delete 2>/dev/null || true
find "$APP/core/storage/logs" -type f ! -name '.gitignore' -delete 2>/dev/null || true

printf '%s\n' "$VERSION" > "$APP/VERSION"

hdr "5. Archive"

( cd "$STAGE" && zip -qr "$DIST/$ZIP" "princebyron-${VERSION}" )

cd "$DIST"
if command -v sha256sum >/dev/null 2>&1; then
  sha256sum "$ZIP" > "$ZIP.sha256"
elif command -v shasum >/dev/null 2>&1; then
  shasum -a 256 "$ZIP" > "$ZIP.sha256"
fi
printf '%s\n' "$VERSION" > VERSION
cd "$ROOT"

SIZE=$(du -h "$DIST/$ZIP" | cut -f1)
grn "  $DIST/$ZIP  ($SIZE)"
[[ -f "$DIST/$ZIP.sha256" ]] && grn "  $DIST/$ZIP.sha256"

hdr "6. Extraction test"
# An archive that cannot be extracted into a clean directory is not an archive.
# This proves the zip is intact and the structure is what the installer expects.
TESTDIR="$(mktemp -d)"
trap 'rm -rf "$STAGE" "$TESTDIR"' EXIT
unzip -q "$DIST/$ZIP" -d "$TESTDIR"

for expect in index.php .htaccess install/index.php core/artisan core/vendor/autoload.php \
              core/public/build/manifest.json VERSION; do
  [[ -e "$TESTDIR/princebyron-${VERSION}/${expect}" ]] \
    || fail "the archive is missing ${expect} — extraction test failed"
done
grn "  extracted cleanly and contains the expected structure"

if [[ -f "$DIST/$ZIP.sha256" ]]; then
  grn "  checksum written — verify after uploading with: sha256sum -c $ZIP.sha256"
fi

hdr "Done"
grn "Upload ${ZIP} to the host, extract it, then open /install/ in a browser."
ylw "After the installer completes, run: php artisan pb:readiness --strict"
ylw "and delete the install/ directory."
