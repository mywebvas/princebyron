#!/usr/bin/env node
/**
 * Composer lock-file sync check — Prince Byron platform.
 *
 * Why this exists
 * ---------------
 * `composer install` (never `update`) is what produces the shipped vendor/
 * tree. If composer.json changes without re-locking, install aborts with
 * "lock file is not up to date with the latest changes in composer.json".
 * On a host with no Composer that failure is invisible until the release
 * machine cannot build the archive at all — which is exactly what happened
 * during Sprint 0, caught by CI.
 *
 * This reproduces Composer\Package\Locker::getContentHash() exactly:
 *
 *   relevantKeys = name, version, require, require-dev, conflict, replace,
 *                  provide, minimum-stability, prefer-stable, repositories, extra
 *   if config.platform set -> include it
 *   if config.policy  set -> include it
 *   ksort(top level)  then  md5(json_encode($data, 0))
 *
 * `JsonFile::encode($data, 0)` uses PHP's *default* JSON flags, so:
 *   - "/" is escaped as "\/"  (no JSON_UNESCAPED_SLASHES)
 *   - non-ASCII becomes \uXXXX (no JSON_UNESCAPED_UNICODE)
 *   - no whitespace (no JSON_PRETTY_PRINT)
 * which is what .replace(/\//g, '\\/') + ensure_ascii + separators below do.
 *
 * Run: node tools/check-lock.mjs           (verify)
 *      node tools/check-lock.mjs --print   (print the hash only)
 */

import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const COMPOSER = path.join(ROOT, 'core/composer.json');
const LOCK = path.join(ROOT, 'core/composer.lock');

const RELEVANT = [
  'name',
  'version',
  'require',
  'require-dev',
  'conflict',
  'replace',
  'provide',
  'minimum-stability',
  'prefer-stable',
  'repositories',
  'extra',
];

export function composerContentHash(composerText) {
  const c = JSON.parse(composerText);

  // array_intersect_key($content, $relevantKeys) — insertion order preserved
  const rel = Object.fromEntries(Object.entries(c).filter(([k]) => RELEVANT.includes(k)));

  if (c.config && Object.prototype.hasOwnProperty.call(c.config, 'platform')) {
    rel.config = { platform: c.config.platform };
  }
  if (c.config && Object.prototype.hasOwnProperty.call(c.config, 'policy')) {
    rel.config = { ...(rel.config ?? {}), policy: c.config.policy };
  }

  // ksort() — PHP sorts keys with the standard string comparison
  const sorted = Object.keys(rel)
    .sort()
    .reduce((acc, k) => {
      acc[k] = rel[k];
      return acc;
    }, {});

  // JsonFile::encode($data, 0) → json_encode with PHP's default flags
  const encoded = JSON.stringify(sorted).replace(/\//g, '\\/');

  return createHash('md5').update(encoded, 'utf8').digest('hex');
}

const isMain = process.argv[1] && path.resolve(process.argv[1]) === path.resolve(import.meta.filename);

if (isMain) {
  let composerText;
  let lock;

  try {
    composerText = readFileSync(COMPOSER, 'utf8');
  } catch {
    console.error('FAIL: core/composer.json not found.');
    process.exit(1);
  }
  try {
    lock = JSON.parse(readFileSync(LOCK, 'utf8'));
  } catch {
    console.error('FAIL: core/composer.lock not found or invalid JSON.');
    process.exit(1);
  }

  const computed = composerContentHash(composerText);
  const recorded = lock['content-hash'];

  if (process.argv.includes('--print')) {
    console.log(computed);
    process.exit(0);
  }

  console.log('Composer lock sync — Prince Byron\n');
  console.log(`  core/composer.lock content-hash : ${recorded}`);
  console.log(`  computed from core/composer.json: ${computed}`);

  if (computed !== recorded) {
    console.log('\nFAILED: composer.json and composer.lock are out of sync.');
    console.log('  `composer install` would abort, so the release machine cannot');
    console.log('  produce vendor/ and the archive would ship without dependencies.');
    console.log('\n  Fix: on a machine with PHP 8.5 and Composer, run');
    console.log('         composer update --lock      (no dependency changes)');
    console.log('       — never hand-edit content-hash, and never add config.platform');
    console.log('       unless the lock is re-generated in the same commit.');
    process.exit(1);
  }

  // Advisory: the PHP floor declared for consumers vs. what the lock was
  // resolved against. A mismatch means the shipped vendor/ tree was not
  // resolved for the runtime the installer enforces.
  const declared = JSON.parse(composerText).require?.php ?? '(none)';
  const lockedAgainst = lock.platform?.php ?? '(none)';
  console.log(`  require.php (consumers)         : ${declared}`);
  console.log(`  lock platform.php (resolution)  : ${lockedAgainst}`);
  if (declared !== lockedAgainst) {
    console.log('  WARN: these differ — confirm vendor/ is resolved for the');
    console.log('        version the installer enforces (install/index.php).');
  }

  console.log('\nPASSED: composer.json and composer.lock are in sync.');
}
