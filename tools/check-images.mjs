#!/usr/bin/env node
/**
 * Responsive-image discipline check (P2-6).
 *
 * WHY THIS EXISTS
 * ---------------
 * Derivative generation is worthless if a template keeps emitting the master
 * file. The store page was converted to <x-img> and the home and product pages
 * were not, so a 1.03 MB hoodie JPEG was still being sent to a 390 px phone on
 * the two pages with the most traffic. Nothing failed — the images rendered
 * perfectly, just at eight times the bytes.
 *
 * So: page templates may not emit a raw <img>. Either use <x-img> (which emits
 * <picture> with srcset, intrinsic dimensions and the right loading hint) or
 * declare an exemption below with a reason.
 *
 * Usage: node tools/check-images.mjs
 */

import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const VIEWS = 'core/resources/views';

// Components and layouts may legitimately contain a bare <img>: <x-img> itself
// is one, and a layout has no knowledge of page data.
const EXEMPT_FILES = [
  'components/img.blade.php',
];

// Emails must use plain <img>: mail clients do not support <picture> or srcset.
const EXEMPT_DIRS = [
  'mail',
];

const offences = [];

function walk(dir, rel = '') {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    const relPath = rel ? `${rel}/${entry}` : entry;

    if (statSync(full).isDirectory()) {
      if (!EXEMPT_DIRS.some((d) => relPath.startsWith(d))) walk(full, relPath);
      continue;
    }
    if (!entry.endsWith('.blade.php')) continue;
    if (EXEMPT_FILES.includes(relPath)) continue;

    const lines = readFileSync(full, 'utf8').split('\n');
    lines.forEach((line, i) => {
      // `<x-img` is fine; `<img` is not. Also catches `<img\n` multi-line tags.
      if (/<img[\s>]/.test(line)) {
        offences.push(
          `${VIEWS}/${relPath}:${i + 1} emits a raw <img> — use <x-img> so the ` +
          'browser gets srcset, intrinsic dimensions and a WebP source'
        );
      }
    });
  }
}

walk(VIEWS);

if (offences.length > 0) {
  console.log(`Responsive image check FAILED (${offences.length}):`);
  for (const o of offences) console.log(`  - ${o}`);
  process.exit(1);
}

console.log('PASSED: every page template uses <x-img> — no master JPEGs are served directly');
