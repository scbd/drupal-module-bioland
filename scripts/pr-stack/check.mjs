#!/usr/bin/env node
// Enforces .github/pr-stack.yml: a declared head branch must target its declared
// base, and that base must be merged into the head. Exits non-zero on violation
// with the exact command to fix it. A branch not listed in the manifest passes.

import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const MANIFEST = 'scripts/pr-stack/stack.yml';
const headRef = process.env.HEAD_REF;
const baseRef = process.env.BASE_REF;

if (!headRef || !baseRef) {
  console.error('HEAD_REF and BASE_REF must be set.');
  process.exit(1);
}

// Minimal parse of the `chains:` list -- avoids a yaml dependency for four keys.
const chains = [];
let current = null;
for (const raw of readFileSync(MANIFEST, 'utf8').split('\n')) {
  const line = raw.split('#')[0].trimEnd();
  const head = line.match(/^\s*-\s*head:\s*(\S+)/);
  const base = line.match(/^\s*base:\s*(\S+)/);
  if (head) {
    current = { head: head[1] };
    chains.push(current);
  } else if (base && current) {
    current.base = base[1];
  }
}

const rule = chains.find((c) => c.head === headRef);
if (!rule) {
  console.log(`Branch "${headRef}" is not part of a declared PR stack. Nothing to check.`);
  process.exit(0);
}

const failures = [];

if (baseRef !== rule.base) {
  failures.push(
    `This PR targets "${baseRef}" but ${MANIFEST} declares its base as "${rule.base}".\n` +
    `  Fix (base pointer only, no history rewrite):\n` +
    `    gh pr edit <number> --base ${rule.base}`
  );
}

const merged = (() => {
  try {
    execFileSync('git', ['merge-base', '--is-ancestor', `origin/${rule.base}`, `origin/${headRef}`]);
    return true;
  } catch {
    return false;
  }
})();

if (!merged) {
  failures.push(
    `"${rule.base}" is not merged into "${headRef}", so the chain is a pointer only -- ` +
    `the base's commits are not in this PR's history.\n` +
    `  Fix (non-destructive):\n` +
    `    git checkout ${headRef} && git merge origin/${rule.base} && git push origin HEAD`
  );
}

if (failures.length) {
  console.error(`PR stack violation for "${headRef}":\n\n${failures.join('\n\n')}\n`);
  process.exit(1);
}

console.log(`OK: "${headRef}" targets "${rule.base}" and has it merged in.`);
