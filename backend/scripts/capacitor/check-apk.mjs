#!/usr/bin/env node
// APP-2: fail if an APK (or the synced android assets dir) contains server-only / sensitive files.
//
//   node scripts/capacitor/check-apk.mjs path/to/app-release.apk
//   node scripts/capacitor/check-apk.mjs                     # checks android/app/src/main/assets/public
import { existsSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { findForbiddenEntries, listDirEntries, listZipEntries } from './webAssets.mjs';

const backendDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const target = process.argv[2] ?? path.join(backendDir, 'android', 'app', 'src', 'main', 'assets', 'public');

if (!existsSync(target)) {
    console.error(`Not found: ${target}`);
    process.exit(2);
}

const entries = statSync(target).isDirectory() ? listDirEntries(target, 'assets/public') : listZipEntries(target);
const forbidden = findForbiddenEntries(entries);

if (forbidden.length > 0) {
    console.error(`FAIL ${target}: ${forbidden.length} forbidden entr${forbidden.length === 1 ? 'y' : 'ies'}`);
    for (const entry of forbidden) console.error(`  - ${entry}`);
    process.exit(1);
}
console.info(`OK ${target}: ${entries.length} entries, none forbidden`);
