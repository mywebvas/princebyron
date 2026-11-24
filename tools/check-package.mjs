#!/usr/bin/env node
/**
 * Release-archive manifest checker — Prince Byron platform.
 *
 * Purpose
 * -------
 * The deployment promise is "upload the archive to cPanel and it works, with no
 * Composer or Node on the host". That promise is only true if the archive
 * contains the prebuilt dependencies and compiled assets, and contains none of
 * the things that must never leave the build machine (secrets, logs, local
 * databases, review fixtures, machine-specific config).
 *
 * ARCHITECTURE.md describes those rules in prose. This script makes them
 * executable so a packaging mistake fails CI instead of failing on the owner's
 * shared host, where there is no terminal to debug it.
 *
 * What is checked
 * ---------------
 *  1. INCLUDE  — paths that MUST exist and be tracked by git.
 *  2. EXCLUDE  — paths that MUST NOT be tracked (secrets, runtime state, dev
 *                artifacts, local databases, review snapshots).
 *  3. FORBIDDEN-CONTENT — patterns that must not appear in tracked source
 *                (committed .env values, live API keys, hardcoded passwords).
 *  4. LAYOUT   — the public web root must not expose application internals.
 *
 * Exit code 0 = pass, 1 = one or more rule violations.
 *
 * Run: node tools/check-package.mjs
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');

/* ------------------------------------------------------------------ helpers */

function tracked() {
  const out = execFileSync('git', ['ls-files', '-z'], { cwd: ROOT, encoding: 'utf8' });
  return new Set(out.split('\0').filter(Boolean));
}

function isFile(rel) {
  try {
    return statSync(path.join(ROOT, rel)).isFile();
  } catch {
    return false;
  }
}

const failures = [];
const warnings = [];

function fail(rule, detail) {
  failures.push({ rule, detail });
}

function warn(rule, detail) {
  warnings.push({ rule, detail });
}

function readText(rel) {
  return readFileSync(path.join(ROOT, rel), 'utf8');
}

const files = tracked();

/* -------------------------------------------------------- 1. MUST be tracked
 *
 * Without these the archive cannot run on a host that has no Composer/Node.
 * `vendor/` itself is intentionally NOT listed here: it is a build product
 * produced on the release machine and is gitignored to keep the repository
 * reviewable. Its presence is a documented release step (review P0-5, P3-1).
 */

const mustExist = [
  'index.php',                                          // root loader
  '.htaccess',                                          // root rewrite + headers
  'core/.htaccess',                                     // app-root denial (defense in depth)
  'core/storage/.htaccess',                             // runtime/storage denial
  'install/index.php',                                  // one-time browser installer
  'install/.htaccess',                                  // installer directory denial
  'core/artisan',
  'core/composer.json',
  'core/composer.lock',                                 // reproducible vendor tree
  'core/.env.example',                                  // template only, never .env
  'core/public/index.php',                              // front controller
  'core/public/build/manifest.json',                    // compiled asset map
  'core/bootstrap/app.php',
  'core/bootstrap/providers.php',
  'core/routes/web.php',
  'core/database/migrations',
  'core/resources/views/layouts/app.blade.php',
];

for (const rel of mustExist) {
  const abs = path.join(ROOT, rel);
  if (!existsSync(abs)) {
    fail('INCLUDE', `missing required path: ${rel}`);
  } else if (isFile(rel) && !files.has(rel)) {
    fail('INCLUDE', `exists on disk but is not tracked by git: ${rel}`);
  }
}

// Every asset named by the Vite manifest must be present, or the site deploys
// with a 404-ing stylesheet.
const manifestRel = 'core/public/build/manifest.json';
if (existsSync(path.join(ROOT, manifestRel))) {
  const manifest = JSON.parse(readText(manifestRel));
  for (const [entry, meta] of Object.entries(manifest)) {
    const built = `core/public/build/${meta.file}`;
    if (!existsSync(path.join(ROOT, built))) {
      fail('INCLUDE', `Vite manifest references a missing build output: ${built} (from ${entry})`);
    }
    if (!files.has(built)) {
      fail('INCLUDE', `compiled asset is not tracked by git: ${built}`);
    }
  }
}

/* ---------------------------------------------------- 2. MUST NOT be tracked
 *
 * IMPORTANT: this list is currently informational-as-failure for secrets, but
 * several entries are known-debt from the production readiness review.
 * They are reported with the review finding ID so each violation maps to a
 * tracked task rather than being silently tolerated.
 */

const forbiddenTracked = [
  { pattern: /^core\/\.env$/, why: 'real environment file (secrets)' },
  { pattern: /^core\/\.env\.(?!example$).+/, why: 'environment backup containing secrets' },
  { pattern: /^\.env(?!\.example$)/, why: 'environment file at repository root' },
  { pattern: /^core\/storage\/logs\//, why: 'runtime logs (customer data, redaction risk)' },
  { pattern: /^core\/storage\/framework\/(cache|sessions|views)\//, why: 'runtime cache/session state' },
  { pattern: /^core\/storage\/installed$/, why: 'installer lock marker (must be created on the host)' },
  { pattern: /^core\/storage\/install_execution\.lock$/, why: 'installer mutex file' },
  { pattern: /^core\/public\/snapshots\//, why: 'review screenshots inside the web root (P1-2)' },
  { pattern: /^core\/public\/hot$/, why: 'Vite dev-server marker' },
  { pattern: /^core\/public\/storage\//, why: 'storage symlink (host-specific)' },
  { pattern: /^core\/vendor\//, why: 'vendor is a build product, not source (produced on the release machine)' },
  { pattern: /^core\/node_modules\//, why: 'node_modules is a build product' },
  { pattern: /\.(sqlite|sqlite3)$/, why: 'local SQLite database (P1-13)' },
  { pattern: /^core\/princebyron$/, why: 'committed local SQLite database (P1-13)' },
  { pattern: /^core\/scratch_/, why: 'throwaway dev script (P1-13)' },
  { pattern: /^core\/redis_test\.php$/, why: 'throwaway dev script, syntactically invalid (P1-13)' },
  { pattern: /^core\/public\/find_overflow\.js$/, why: 'dev debugging helper in the web root' },
  { pattern: /^core\/storage\/.+\.(png|html)$/, why: 'dev capture output in storage' },
];

for (const { pattern, why } of forbiddenTracked) {
  for (const file of files) {
    if (!pattern.test(file)) continue;

    // Laravel keeps a `.gitignore` placeholder in each runtime directory so the
    // folder survives a clone. Those guards are intentional source and are the
    // only permitted tracked content inside an otherwise-excluded directory.
    if (path.basename(file) === '.gitignore') continue;

    fail('EXCLUDE', `tracked file must not ship — ${file} (${why})`);
  }
}

/* --------------------------------------------- 3. FORBIDDEN committed content
 *
 * Scans tracked text sources for credential shapes. Deliberately conservative:
 * these patterns target real key formats and assignments, not documentation
 * placeholders such as "STRIPE_SECRET=" with an empty value in .env.example.
 */

const scannable = [
  /^core\/(app|config|routes|database|resources)\/.+\.(php|js|css)$/,
  /^install\/.+\.php$/,
  /^index\.php$/,
];

const contentRules = [
  {
    name: 'stripe-live-key',
    pattern: /\b(?:sk|rk)_live_[A-Za-z0-9]{16,}\b/,
    why: 'live Stripe secret key committed',
  },
  {
    name: 'stripe-test-key',
    pattern: /\bsk_test_[A-Za-z0-9]{16,}\b/,
    why: 'Stripe test secret key committed',
  },
  {
    name: 'paypal-client-secret',
    pattern: /\bE[A-Za-z0-9_-]{60,}\b/,
    why: 'possible PayPal client secret committed',
  },
  {
    name: 'aws-access-key',
    pattern: /\bAKIA[0-9A-Z]{16}\b/,
    why: 'AWS access key id committed',
  },
  {
    name: 'hardcoded-secret-assignment',
    // A literal secret is a quote-free run of 8+ secret-ish characters. Values
    // that interpolate a PHP variable ({$appKey}) or compose from it are
    // generated at install time by the installer's own template and are the
    // opposite of a committed secret, so they are excluded rather than flagged.
    pattern: /^\s*(?:APP_KEY|DB_PASSWORD|STRIPE_SECRET|STRIPE_WEBHOOK_SECRET|PAYPAL_SECRET)\s*=\s*(?!.*\{)[^\s"'{}$]{8,}\s*$/m,
    why: 'literal secret-looking value hardcoded in a shipped source file',
  },
];

for (const file of files) {
  if (!scannable.some((re) => re.test(file))) continue;
  let text;
  try {
    text = readText(file);
  } catch {
    continue;
  }
  for (const rule of contentRules) {
    const m = text.match(rule.pattern);
    if (m) {
      fail('FORBIDDEN-CONTENT', `${file}: ${rule.why} ("${String(m[0]).slice(0, 24)}…")`);
    }
  }
}

/* ------------------------------------------------------------- 4. Web layout
 *
 * Defense in depth: the public document root must never contain application
 * internals. `core/` is reachable only through the root loader, which denies
 * these prefixes (see index.php). This asserts the *layout* rule so a future
 * refactor cannot quietly move app code into the docroot.
 */

const mustNotBeInWebRoot = [
  'core/app',
  'core/config',
  'core/database',
  'core/routes',
  'core/storage',
  'core/vendor',
  'core/bootstrap',
  'core/resources',
];

// The root loader must exist and must be what the document root executes.
if (existsSync(path.join(ROOT, 'index.php'))) {
  const loader = readText('index.php');
  if (!loader.includes("core/public/index.php")) {
    fail('LAYOUT', 'index.php no longer delegates to core/public/index.php');
  }
  for (const prefix of ['/core', '/vendor', '/storage', '/.env', '/.git']) {
    if (!loader.includes(prefix)) {
      fail('LAYOUT', `root loader no longer denies "${prefix}"`);
    }
  }
}

// The root .htaccess must deny the private directories at the web-server layer.
if (existsSync(path.join(ROOT, '.htaccess'))) {
  const htaccess = readText('.htaccess');
  for (const rule of [/RewriteRule \^core\(\/\|\$\) - \[F,L\]/, /RewriteRule \^vendor\(\/\|\$\) - \[F,L\]/, /RewriteRule \^storage\(\/\|\$\) - \[F,L\]/, /RewriteRule \^\\\.env\.\*\$ - \[F,L\]/]) {
    if (!rule.test(htaccess)) {
      fail('LAYOUT', `root .htaccess is missing the denial rule matching ${rule}`);
    }
  }
}

for (const rel of mustNotBeInWebRoot) {
  // Informational: these are expected to exist in the repo but must be denied
  // at the HOST document root. The loader/htaccess assertions above verify the
  // denial; this loop only guards against them being moved into core/public.
  if (existsSync(path.join(ROOT, 'core/public', path.basename(rel)))) {
    fail('LAYOUT', `application internals found inside the web root: core/public/${path.basename(rel)}`);
  }
}

/* -------------------------------------------------------------------- report */

const label = (s) => `\u001b[${s === 'FAIL' ? 31 : 33}m${s}\u001b[0m`;

console.log('Release packaging rules — Prince Byron');
console.log(`  tracked files: ${files.size}`);
console.log(`  rules checked: include=${mustExist.length} exclude=${forbiddenTracked.length} content=${contentRules.length} layout=loader+htaccess`);

for (const w of warnings) console.log(`  ${label('WARN')} [${w.rule}] ${w.detail}`);

if (failures.length > 0) {
  console.log(`\n${failures.length} violation(s):\n`);
  const grouped = failures.reduce((acc, f) => {
    (acc[f.rule] ??= []).push(f.detail);
    return acc;
  }, {});
  for (const [rule, details] of Object.entries(grouped)) {
    console.log(` ${rule}`);
    for (const d of details) console.log(`   - ${d}`);
  }
  console.log('\nFAILED: the release archive would be incorrect or unsafe.');
  process.exit(1);
}

console.log('\nPASSED: workspace satisfies the release packaging rules.');
console.log('Scope: this validates the repository. It does not assert that');
console.log('vendor/ or Filament assets exist — those are build products, and');
console.log('their presence is a documented release step (review P0-5, P3-1).');
