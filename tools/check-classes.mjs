#!/usr/bin/env node
/**
 * Blade class coverage check — Prince Byron platform.
 *
 * WHY THIS EXISTS (review P2-1)
 * -----------------------------
 * 26 classes were referenced by Blade templates and completely absent from the
 * stylesheet: the bag drawer badge, the toast, the product gallery, the bio band,
 * the booking stepper. None of that produced an error. The page rendered, the
 * markup was valid, and the components were simply invisible or unstyled — the
 * single largest visual defect in the review, and one that survived several
 * rounds of human inspection because a missing CSS rule looks like a design
 * decision.
 *
 * A human diff of "222 referenced classes vs compiled CSS" found it once. This
 * script finds it on every push, which is the difference between a fix and a
 * guarantee.
 *
 * SCOPE AND HONESTY
 * -----------------
 * This checks **literal class tokens** — the names a developer typed into a
 * `class="..."` attribute or into a Blade ternary string. It does not (and
 * cannot reasonably) resolve:
 *
 *   - Alpine object syntax (`:class="{ 'active': cond }"`) whose values are
 *     conditional state, not design tokens;
 *   - Blade expressions that build a class name at runtime.
 *
 * Conditional state classes (`active`, `visible`, `selected`) are checked when
 * they appear literally, and are listed below when they are intentionally
 * state-only.
 *
 * The stylesheet is read from `core/resources/css/` — source, not build output.
 * Reading the build would make the check silently pass whenever the committed
 * build is stale, which is exactly the failure mode that hid this defect.
 *
 * Run: node tools/check-classes.mjs
 * Exit: 0 clean, 1 missing classes found (each printed with the file it is in).
 */

import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const VIEWS = path.join(ROOT, 'core/resources/views');
const CSS_DIR = path.join(ROOT, 'core/resources/css');

/**
 * Classes that are intentionally not styled by this project's CSS.
 * Each entry needs a reason — an empty reason is how an allowlist becomes a bin.
 */
const ALLOWED = new Map([
  // Tailwind variant markers. They have no rule of their own by design.
  ['group', 'Tailwind variant marker'],
  ['peer', 'Tailwind variant marker'],
  // Alpine boot marker; its behaviour comes from the Alpine runtime.
  ['x-cloak', 'Alpine boot marker'],
]);

/** Walk a directory for files matching an extension. */
function walk(dir, extensions, out = []) {
  if (!existsSync(dir)) return out;
  for (const entry of readdirSync(dir)) {
    const full = path.join(dir, entry);
    if (statSync(full).isDirectory()) walk(full, extensions, out);
    else if (extensions.some((ext) => entry.endsWith(ext))) out.push(full);
  }
  return out;
}

const VALID = /^[a-z][a-z0-9-]*$/;

function isClassToken(token) {
  // Lowercase, hyphen-or-alphanumeric only. This rejects Blade/PHP identifiers
  // (priceCents, storeApp), size words from prose (the, for, an) and file
  // extensions (jpg, webp) without needing a blocklist.
  return VALID.test(token) && token.length > 1;
}

/**
 * Extract literal class tokens from a Blade template.
 *
 * Precision matters more than recall here, but only in one direction: a false
 * "missing" wastes a minute, while a false "present" hides the defect this check
 * exists to catch. Both extraction rules below are therefore restricted to
 * *class position* rather than "any quoted string in the file", which is what a
 * naive version does — it reports `about` from `request()->is('about')` and `all`
 * from `activeFilter === 'all'`, then drowns the real findings in noise until
 * somebody deletes the check.
 */
function classesInSource(source) {
  const found = new Set();

  // 1. Literal `class="..."` values: everything outside {{ }} is literal.
  // (?<!:) so `:class="..."` (Alpine) is not read as a plain class attribute.
  for (const match of source.matchAll(/(?<!:)class\s*=\s*"([^"]*)"/g)) {
    const raw = match[1];

    for (const literal of raw.split(/\{\{[\s\S]*?\}\}/)) {
      for (const token of literal.split(/\s+/)) {
        if (isClassToken(token)) found.add(token);
      }
    }

    // 2. Inside {{ }} in a class attribute, only strings in ternary class
    //    position are class names: `cond ? 'active' : ''`.
    for (const echo of raw.matchAll(/\{\{([\s\S]*?)\}\}/g)) {
      for (const str of echo[1].matchAll(/[?:]\s*'([^']*)'/g)) {
        for (const token of str[1].split(/\s+/)) {
          if (isClassToken(token)) found.add(token);
        }
      }
    }
  }

  // 3. Alpine :class object bindings — only the *keys* are class names:
  //    :class="{ 'selected': activeFilter === 'all' }"  → selected
  for (const match of source.matchAll(/:class\s*=\s*"([^"]*)"/g)) {
    for (const key of match[1].matchAll(/'([a-z][a-z0-9-]*)'\s*:/g)) {
      if (isClassToken(key[1])) found.add(key[1]);
    }
  }

  return found;
}

function stylesheetSource() {
  return walk(CSS_DIR, ['.css'])
    .map((file) => readFileSync(file, 'utf8'))
    .join('\n')
    .replace(/\/\*[\s\S]*?\*\//g, ''); // a class named in a comment is not styled
}

function main() {
  const css = stylesheetSource();
  const referenced = new Map(); // class -> first file seen

  for (const file of walk(VIEWS, ['.blade.php'])) {
    const relative = path.relative(ROOT, file);
    for (const className of classesInSource(readFileSync(file, 'utf8'))) {
      if (!referenced.has(className)) referenced.set(className, relative);
    }
  }

  // Tailwind utilities are generated, not written in source. Detect them from
  // the committed build so a utility class is not reported as missing.
  const buildDir = path.join(ROOT, 'core/public/build/assets');
  const builtCss = existsSync(buildDir)
    ? walk(buildDir, ['.css']).map((f) => readFileSync(f, 'utf8')).join('\n')
    : '';

  const missing = [];

  for (const [className, file] of [...referenced].sort((a, b) => a[0].localeCompare(b[0]))) {
    if (ALLOWED.has(className)) continue;

    // A selector, not a substring: `.btn` must not be satisfied by `.btn-primary`.
    // That near-miss is precisely how a visual bug survives review.
    const escaped = className.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
    const pattern = new RegExp(`\\.${escaped}(?![\\w-])`);

    if (pattern.test(css)) continue;
    if (builtCss && pattern.test(builtCss)) continue; // Tailwind utility

    missing.push({ className, file });
  }

  console.log('Blade class coverage — Prince Byron\n');
  console.log(`  views scanned      : ${walk(VIEWS, ['.blade.php']).length}`);
  console.log(`  classes referenced : ${referenced.size}`);
  console.log(`  allowed exceptions : ${ALLOWED.size}`);
  console.log(`  tailwind build read: ${builtCss ? 'yes' : 'no (utilities will be reported)'}\n`);

  if (missing.length === 0) {
    console.log('PASSED: every literal class used in a Blade template resolves to a rule');
    console.log('        in resources/css or in the compiled Tailwind build.');
    process.exit(0);
  }

  console.log(`MISSING: ${missing.length} class(es) have no rule anywhere.\n`);
  for (const { className, file } of missing) {
    console.log(`  .${className}`);
    console.log(`      first used in ${file}`);
  }
  console.log('\nEach of these renders as an unstyled element. Add the rule, or add the');
  console.log('class to ALLOWED in tools/check-classes.mjs with a reason.\n');
  process.exit(1);
}

main();
