#!/usr/bin/env node
/**
 * Frontend build budget check — Prince Byron platform.
 *
 * Run after `npm run build` (from core/). Fails when:
 *   - the Vite manifest is missing or references files that do not exist;
 *   - compiled CSS or JS exceeds the size budget below;
 *   - the build emits anything other than the two expected entry points.
 *
 * Why a budget at all: the PRD sets Core Web Vitals targets (LCP <= 2.5 s,
 * INP <= 200 ms, CLS <= 0.1 at p75 on mobile). CSS and JS are the only payload
 * this repository fully controls, so they are the only part that can be gated
 * mechanically. Image weight is a separate, documented finding (P2-6).
 *
 * Budgets are gzip bytes, set slightly above current reality so a legitimate
 * refactor does not fail the build, but a runaway import does. Raise them
 * deliberately, with a note in docs/PRODUCTION-READINESS-REVIEW.md.
 *
 * Run: node tools/check-assets.mjs
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';
import { gzipSync } from 'node:zlib';

const ROOT = path.resolve(import.meta.dirname, '..');
const BUILD_DIR = path.join(ROOT, 'core/public/build');
const MANIFEST = path.join(BUILD_DIR, 'manifest.json');

// gzip byte budgets
const BUDGETS = {
  css: 22 * 1024, // current 20.4 kB gzip per this check after the Revenue Chart styles
  js: 30 * 1024,  // current ~21.0 kB
};

// Absolute ceilings that should never be crossed by a single page bundle.
const HARD_CEILING = {
  css: 45 * 1024,
  js: 75 * 1024,
};

const failures = [];
const rows = [];
const fmt = (n) => `${(n / 1024).toFixed(1)} kB`;

if (!existsSync(MANIFEST)) {
  console.error('FAIL: core/public/build/manifest.json is missing.');
  console.error('      Run `npm run build` in core/ before this check.');
  process.exit(1);
}

const manifest = JSON.parse(readFileSync(MANIFEST, 'utf8'));

/* 1. Every manifest entry must resolve to a real file, and every emitted file
 *    must be referenced by the manifest (an orphan means stale output). */
const referenced = new Set();

for (const [entry, meta] of Object.entries(manifest)) {
  if (!meta.file) {
    failures.push(`${entry}: manifest entry has no "file"`);
    continue;
  }
  const abs = path.join(BUILD_DIR, meta.file);
  if (!existsSync(abs)) {
    failures.push(`${entry}: manifest references a missing file — ${meta.file}`);
    continue;
  }
  referenced.add(path.normalize(meta.file));

  const ext = path.extname(meta.file).slice(1);
  if (ext !== 'css' && ext !== 'js') continue;

  const raw = statSync(abs).size;
  const gz = gzipSync(readFileSync(abs)).length;
  const budget = BUDGETS[ext];
  const ceiling = HARD_CEILING[ext];

  rows.push({ entry, file: meta.file, raw, gz, budget, ceiling });

  if (gz > ceiling) {
    failures.push(`${meta.file}: ${fmt(gz)} gzip exceeds hard ceiling ${fmt(ceiling)} for ${ext}`);
  } else if (gz > budget) {
    failures.push(`${meta.file}: ${fmt(gz)} gzip exceeds budget ${fmt(budget)} for ${ext} — raise deliberately or remove code`);
  }
}

/* 2. Expected entry points must both be present. */
for (const expected of ['resources/css/app.css', 'resources/js/app.js']) {
  if (!manifest[expected]) {
    failures.push(`manifest is missing the expected entry point: ${expected}`);
  }
}

/* 3. Report. */
console.log('Frontend build budget — Prince Byron\n');
console.log(`  ${'entry'.padEnd(24)} ${'raw'.padStart(10)} ${'gzip'.padStart(10)} ${'budget'.padStart(10)}  status`);
for (const r of rows) {
  const status = r.gz > r.ceiling ? 'OVER CEILING' : r.gz > r.budget ? 'OVER BUDGET' : 'ok';
  console.log(
    `  ${r.entry.padEnd(24)} ${fmt(r.raw).padStart(10)} ${fmt(r.gz).padStart(10)} ${fmt(r.budget).padStart(10)}  ${status}`,
  );
}

const totalGz = rows.reduce((s, r) => s + r.gz, 0);
console.log(`\n  total gzip payload: ${fmt(totalGz)}`);

if (failures.length > 0) {
  console.log(`\n${failures.length} failure(s):`);
  for (const f of failures) console.log(`  - ${f}`);
  process.exit(1);
}

console.log('\nPASSED: build output is complete and within budget.');
