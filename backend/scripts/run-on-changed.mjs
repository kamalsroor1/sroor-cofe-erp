#!/usr/bin/env node
// Run ESLint or Prettier only on files changed vs HEAD (staged + unstaged + untracked),
// limited to the current package directory. Cross-platform (no shell globbing / xargs).
//
//   node <path>/run-on-changed.mjs eslint [eslint args...]
//   node <path>/run-on-changed.mjs prettier --check | --write [prettier args...]
//
// Resolves the tool from ./node_modules of the current working directory, so the same
// script serves backend/ and desktop/.

import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

const TOOLS = {
    eslint: { bin: 'node_modules/eslint/bin/eslint.js', extraArgs: ['--no-warn-ignored'] },
    prettier: { bin: 'node_modules/prettier/bin/prettier.cjs', extraArgs: ['--ignore-unknown'] },
};
const EXTENSIONS = new Set(['.js', '.cjs', '.mjs', '.vue']);
const BATCH_SIZE = 100;
// Build output / vendored code that may sit untracked in the tree; the tools ignore it anyway.
const SKIP = /(^|\/)(node_modules|vendor|public\/build|dist|android)\//;

const [toolName, ...toolArgs] = process.argv.slice(2);
const tool = TOOLS[toolName];
if (!tool) {
    console.error(`Usage: run-on-changed.mjs <${Object.keys(TOOLS).join('|')}> [args...]`);
    process.exit(2);
}

const binPath = path.resolve(tool.bin);
if (!existsSync(binPath)) {
    console.error(`${toolName} not found at ${binPath}. Run npm install first.`);
    process.exit(2);
}

const git = (args) =>
    execFileSync('git', args, { encoding: 'utf8' })
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);

const changed = [
    ...git(['diff', '--name-only', '--relative', '--diff-filter=ACMR', 'HEAD']),
    ...git(['ls-files', '--others', '--exclude-standard']),
];

const files = [...new Set(changed)].filter(
    (file) => EXTENSIONS.has(path.extname(file)) && !SKIP.test(file) && existsSync(file)
);

if (files.length === 0) {
    console.log(`No changed ${[...EXTENSIONS].join('/')} files — nothing for ${toolName} to do.`);
    process.exit(0);
}

console.log(`${toolName}: ${files.length} changed file(s)`);

let exitCode = 0;
for (let i = 0; i < files.length; i += BATCH_SIZE) {
    const batch = files.slice(i, i + BATCH_SIZE);
    const result = spawnSync(process.execPath, [binPath, ...tool.extraArgs, ...toolArgs, ...batch], {
        stdio: 'inherit',
    });
    exitCode = Math.max(exitCode, result.status ?? 1);
}

process.exit(exitCode);
