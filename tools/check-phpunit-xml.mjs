#!/usr/bin/env node
/**
 * PHPUnit configuration well-formedness check.
 *
 * WHY THIS EXISTS
 * ---------------
 * A PHPUnit XML file that is not well-formed fails in a way that looks like a
 * broken application: PHPUnit cannot load the configuration, so every test in
 * the run errors, and on a GitHub runner the per-test annotations are rejected
 * for their own reasons, leaving no readable cause. That is exactly how an
 * evening was lost — the actual defect was a `--------------------` separator
 * inside an XML comment, and XML forbids `--` in comments.
 *
 * XML's restriction is easy to violate and produces no useful error, so it is
 * checked mechanically here rather than remembered. Runs without Composer or
 * PHP, so it can gate the `static` job.
 *
 * Usage: node tools/check-phpunit-xml.mjs
 */

import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = 'core';
const failures = [];

// A deliberately small, dependency-free well-formedness check. It is not a
// general XML parser; it verifies what actually breaks in practice:
//   1. Comments must not contain `--` (XML spec, section 2.5).
//   2. Every element must be closed: self-closing, or matched by an end tag.
const files = readdirSync(ROOT).filter((f) => /^phpunit.*\.xml$/.test(f));

if (files.length === 0) {
  console.error('FAIL: no phpunit*.xml files found — the suite would not run.');
  process.exit(1);
}

for (const file of files) {
  const source = readFileSync(join(ROOT, file), 'utf8');
  const lines = source.split('\n');

  // 1. `--` inside comments.
  let inComment = false;
  let commentStart = 0;
  lines.forEach((line, i) => {
    let rest = line;
    for (;;) {
      if (!inComment) {
        const open = rest.indexOf('<!--');
        if (open === -1) break;
        inComment = true;
        commentStart = i + 1;
        rest = rest.slice(open + 4);
      } else {
        const close = rest.indexOf('-->');
        const body = close === -1 ? rest : rest.slice(0, close);
        if (body.includes('--')) {
          failures.push(
            `${ROOT}/${file}:${i + 1} comment opened at line ${commentStart} contains "--", ` +
            'which XML forbids. PHPUnit then cannot read the file and every test errors. ' +
            'Use a blank line or "==" instead of a dashed rule.'
          );
        }
        if (close === -1) break;
        inComment = false;
        rest = rest.slice(close + 3);
      }
    }
  });

  // 2. Element balance. Comments and the prolog are removed first.
  const stripped = source
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/<\?[\s\S]*?\?>/g, '')
    .replace(/<!DOCTYPE[^>]*>/g, '');

  const stack = [];
  const tagRe = /<(\/?)([A-Za-z_][\w.:-]*)([^>]*?)(\/?)>/g;
  let m;
  while ((m = tagRe.exec(stripped)) !== null) {
    const [, closing, name, , selfClosing] = m;
    if (closing) {
      const open = stack.pop();
      if (open !== name) {
        failures.push(`${ROOT}/${file}: "</${name}>" closes "<${open ?? 'nothing'}>"`);
        break;
      }
    } else if (!selfClosing) {
      stack.push(name);
    }
  }
  if (stack.length > 0) {
    failures.push(`${ROOT}/${file}: unclosed element(s): ${stack.join(', ')}`);
  }
}

if (failures.length > 0) {
  console.log(`PHPUnit config check FAILED (${failures.length}):`);
  for (const f of failures) console.log(`  - ${f}`);
  process.exit(1);
}

console.log(`PASSED: ${files.length} PHPUnit config file(s) well-formed — ${files.join(', ')}`);
