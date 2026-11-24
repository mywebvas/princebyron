#!/usr/bin/env node
/**
 * Release-completeness check — what the archive must contain to work on a host
 * with no Composer and no Node.
 *
 * WHY THIS EXISTS
 * ---------------
 * review P0-5: `vendor/` and Filament's compiled assets are gitignored build
 * products. That is correct for a repository, but it means the thing the owner
 * uploads is NOT the repository — it is the repository plus two build steps.
 * Nothing verified that the build steps actually happened before zipping, so a
 * package could look complete and deploy to a completely unstyled admin panel.
 *
 * `tools/check-package.mjs` deliberately does not assert these (it runs on a
 * clean checkout where they legitimately do not exist). This script is the
 * separate, explicit gate that runs against a *release candidate tree*.
 *
 * Usage:
 *   node tools/check-release.mjs            # check the working tree
 *   node tools/check-release.mjs --strict   # also require vendor/ + filament
 *
 * Exit codes:
 *   0  ready
 *   1  blocking problem
 *   2  advisory only (nothing blocking, but the package is not yet complete)
 */

import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const APP = path.join(ROOT, 'core');
const strict = process.argv.includes('--strict');

const blocking = [];
const advisory = [];
const ok = [];

const exists = (p) => existsSync(p);

function dirSize(dir) {
  if (!exists(dir)) return 0;
  let total = 0;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    total += entry.isDirectory() ? dirSize(p) : statSync(p).size;
  }
  return total;
}

const mb = (b) => `${(b / 1024 / 1024).toFixed(1)} MB`;

// ---------------------------------------------------------------------------
// 1. Application dependencies
// ---------------------------------------------------------------------------
const vendor = path.join(APP, 'vendor');
const autoload = path.join(vendor, 'autoload.php');

if (exists(autoload)) {
  ok.push(`vendor/ present (${mb(dirSize(vendor))})`);
} else {
  const msg =
    'core/vendor/ is missing. The host has no Composer, so the package cannot ' +
    'run at all. Build it with `cd core && composer install --no-dev ' +
    '--optimize-autoloader` on the release machine.';
  (strict ? blocking : advisory).push(msg);
}

// ---------------------------------------------------------------------------
// 2. Frontend build (Vite)
// ---------------------------------------------------------------------------
const manifestPath = path.join(APP, 'public/build/manifest.json');
if (exists(manifestPath)) {
  try {
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
    const entries = ['resources/css/app.css', 'resources/js/app.js'];
    const missing = entries.filter((e) => !manifest[e]);

    if (missing.length) {
      blocking.push(`Vite manifest is missing entries: ${missing.join(', ')}`);
    } else {
      const filesMissing = entries.filter((e) => !exists(path.join(APP, 'public/build', manifest[e].file)));
      if (filesMissing.length) {
        blocking.push(
          `Vite manifest references files that do not exist on disk: ${filesMissing
            .map((e) => manifest[e].file)
            .join(', ')}`
        );
      } else {
        ok.push('frontend build present and self-consistent');
      }
    }
  } catch (e) {
    blocking.push(`Vite manifest is not valid JSON: ${e.message}`);
  }
} else {
  blocking.push(
    'core/public/build/manifest.json is missing — the site would load with no ' +
    'CSS or JavaScript. Run `npm ci && npm run build`.'
  );
}

// ---------------------------------------------------------------------------
// 3. Filament admin assets (review P0-5)
// ---------------------------------------------------------------------------
const filamentDirs = ['css/filament', 'js/filament'].map((d) => path.join(APP, 'public', d));
const filamentPresent = filamentDirs.filter((d) => exists(d) && readdirSync(d).length > 0);

if (filamentPresent.length === filamentDirs.length) {
  ok.push('Filament admin assets present');
} else {
  const msg =
    'Filament admin assets are missing (core/public/{css,js}/filament). /admin ' +
    'would load unstyled. Build with `php artisan filament:assets`.';
  (strict ? blocking : advisory).push(msg);
}

// ---------------------------------------------------------------------------
// 4. Installer + loader presence (the upload-and-works contract)
// ---------------------------------------------------------------------------
const required = [
  ['index.php', ROOT],
  ['.htaccess', ROOT],
  ['install/index.php', ROOT],
  ['core/.htaccess', ROOT],
  ['core/storage/.htaccess', ROOT],
  ['core/public/index.php', ROOT],
];

for (const [rel, base] of required) {
  if (!exists(path.join(base, rel))) {
    blocking.push(`${rel} is missing — the package cannot be installed.`);
  }
}
if (!blocking.length) ok.push('loader, installer and deny rules all present');

// ---------------------------------------------------------------------------
// 5. No runtime state or secrets in the archive
// ---------------------------------------------------------------------------
const mustNotExist = [
  'core/.env',
  'core/storage/installed',
  'core/storage/install_execution.lock',
];

for (const rel of mustNotExist) {
  if (exists(path.join(ROOT, rel))) {
    blocking.push(
      `${rel} exists. A release archive must not contain a configured environment ` +
      'or an install lock, or the next install will be skipped or leak secrets.'
    );
  }
}

// ---------------------------------------------------------------------------
// 6. Favicon must be a real image, not a 0-byte placeholder
// ---------------------------------------------------------------------------
const favicon = path.join(APP, 'public/favicon.ico');
if (exists(favicon)) {
  const size = statSync(favicon).size;
  if (size === 0) {
    blocking.push('core/public/favicon.ico is 0 bytes — browsers request it directly.');
  } else {
    ok.push(`favicon present (${size} bytes)`);
  }
} else {
  advisory.push('core/public/favicon.ico is missing.');
}

// ---------------------------------------------------------------------------
// Report
// ---------------------------------------------------------------------------
console.log('Release completeness — Prince Byron\n');
for (const line of ok) console.log(`  ok    ${line}`);
for (const line of advisory) console.log(`  TODO  ${line}`);
for (const line of blocking) console.log(`  FAIL  ${line}`);
console.log('');

if (blocking.length) {
  console.log(`BLOCKED: ${blocking.length} problem(s) must be fixed before packaging.`);
  process.exit(1);
}

if (advisory.length) {
  console.log(`INCOMPLETE: nothing broken, but ${advisory.length} build step(s) remain.`);
  console.log('Run the build on a machine with PHP + Composer + Node, then re-run with --strict.');
  process.exit(2);
}

console.log('READY: this tree is a deployable upload-and-works package.');
