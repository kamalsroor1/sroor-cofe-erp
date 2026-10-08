#!/usr/bin/env node
// APP-2: assemble the Capacitor webDir (capacitor-www/) from the allow-listed shell files in public/.
// Runs before `npx cap sync|copy` (see package.json `cap:*` scripts).
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { WEB_DIR, buildCapacitorWeb } from './webAssets.mjs';

const backendDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const outDir = path.join(backendDir, WEB_DIR);

const copied = buildCapacitorWeb({ sourceDir: path.join(backendDir, 'public'), outDir });
console.info(`Capacitor webDir ${WEB_DIR}/: ${copied.join(', ')}`);
