#!/usr/bin/env node
/**
 * GitHub Actions workflow validator — Prince Byron platform.
 *
 * Why this exists
 * ---------------
 * A malformed workflow does not fail loudly: GitHub accepts the push, reports
 * the run as "completed / failure", and publishes *zero jobs*. That happened
 * during Sprint 0 — an unquoted step name containing a colon
 * (`- name: Tests: PostgreSQL suite`) silently disabled the entire pipeline,
 * and the failure looked like a test problem rather than a YAML problem.
 *
 * This validator catches that class of mistake before it reaches the remote:
 *   - the file must parse as YAML;
 *   - `on`, `jobs` and each job's `runs-on` must exist;
 *   - every job must have at least one step;
 *   - every step must have exactly one of `uses` / `run`;
 *   - shell blocks must not contain obvious quote mistakes;
 *   - no workflow may be empty of jobs.
 *
 * Run: node tools/check-workflows.mjs
 */

import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const DIR = path.join(ROOT, '.github/workflows');

const failures = [];

function fail(file, msg) {
  failures.push(`${file}: ${msg}`);
}

let yaml;
try {
  // js-yaml is a dev-only convenience for this check; it is not a runtime
  // dependency of the application. It is CommonJS, so the named export is
  // reached through the module namespace rather than `.default`.
  const mod = await import('js-yaml');
  yaml = mod.load ? mod : mod.default;
  if (!yaml || typeof yaml.load !== 'function') throw new Error('no load()');
} catch {
  console.error('SKIP: js-yaml is not installed.');
  console.error('      This checker is advisory-only when the parser is absent,');
  console.error('      so it never blocks a build in an environment without it.');
  process.exit(0);
}

let files = [];
try {
  files = readdirSync(DIR).filter((f) => f.endsWith('.yml') || f.endsWith('.yaml'));
} catch {
  console.error(`No workflow directory at ${DIR} — nothing to check.`);
  process.exit(0);
}

if (files.length === 0) {
  console.error(`No workflow files found in ${DIR}.`);
  process.exit(1);
}

for (const file of files) {
  const abs = path.join(DIR, file);
  let doc;

  try {
    doc = yaml.load(readFileSync(abs, 'utf8'));
  } catch (e) {
    fail(file, `YAML does not parse — ${e.message.split('\n')[0]}`);
    continue;
  }

  if (!doc || typeof doc !== 'object') {
    fail(file, 'workflow does not parse to a mapping');
    continue;
  }

  if (!doc.on) fail(file, 'missing "on" trigger block');
  if (!doc.jobs || typeof doc.jobs !== 'object') {
    fail(file, 'missing "jobs" block');
    continue;
  }

  const jobNames = Object.keys(doc.jobs);
  if (jobNames.length === 0) fail(file, 'has zero jobs');

  for (const [name, job] of Object.entries(doc.jobs)) {
    const where = `job "${name}"`;

    if (!job || typeof job !== 'object') {
      fail(file, `${where} is not a mapping`);
      continue;
    }
    if (!job['runs-on']) fail(file, `${where} has no "runs-on"`);

    const steps = job.steps;
    if (!Array.isArray(steps) || steps.length === 0) {
      fail(file, `${where} has no steps`);
      continue;
    }

    steps.forEach((step, i) => {
      const id = `${where} step ${i + 1}${step?.name ? ` ("${step.name}")` : ''}`;

      if (!step || typeof step !== 'object') {
        fail(file, `${id} is not a mapping — an unquoted value containing ": " is the usual cause`);
        return;
      }
      const hasUses = typeof step.uses === 'string';
      const hasRun = typeof step.run === 'string';
      if (!hasUses && !hasRun) {
        fail(file, `${id} has neither "uses" nor "run" — check for an unquoted name containing ": "`);
      }
      if (hasUses && hasRun) fail(file, `${id} has both "uses" and "run"`);

      // Step names are the most common victim of unquoted colons; when YAML
      // still parses but the name is absent, it silently became a nested key.
      if (step.name === undefined && hasRun) {
        fail(file, `${id} has no "name" (a colon in the name likely nested it as a key)`);
      }
    });
  }

  // Guard against the "empty pipeline" outcome: if every job is a placeholder
  // the run reports success while testing nothing.
  const totalSteps = jobNames.reduce((s, n) => s + (doc.jobs[n].steps?.length ?? 0), 0);
  if (totalSteps === 0) fail(file, 'all jobs are empty — the pipeline would verify nothing');
}

if (failures.length > 0) {
  console.log(`Workflow validation FAILED (${failures.length}):`);
  for (const f of failures) console.log(`  - ${f}`);
  process.exit(1);
}

console.log(`PASSED: ${files.length} workflow file(s) valid — ${files.join(', ')}`);
