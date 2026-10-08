// Web server launcher for the LOCAL smoke harness (Playwright `webServer` command).
// 1. Removes leftovers of a previous run (temp central DB, e2e tenant DB, tenant storage dir).
// 2. Builds a fresh throwaway DB with prepare-local-smoke.php.
// 3. Serves backend/public on 127.0.0.1 with router.php (built assets, no Vite dev server).
// Playwright stops this process tree when the run ends.
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    BASE_URL,
    HOST,
    PORT,
    assertLocalUrl,
    centralDbPath,
    publicMirrorDir,
    realPublicDir,
    serverEnv,
    tenantDbPath,
    tenantStorageDir,
    workDir,
} from './smoke-env.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));

assertLocalUrl(BASE_URL);

export function removeSmokeArtifacts() {
    // Unlink the junction to backend/public/build first so a recursive delete can never reach the real build.
    const buildLink = path.join(publicMirrorDir, 'build');
    if (fs.lstatSync(buildLink, { throwIfNoEntry: false })?.isSymbolicLink()) fs.unlinkSync(buildLink);
    fs.rmSync(workDir, { recursive: true, force: true });
    fs.rmSync(tenantDbPath, { force: true });
    fs.rmSync(`${tenantDbPath}-journal`, { force: true });
    fs.rmSync(tenantStorageDir, { recursive: true, force: true });
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    removeSmokeArtifacts();
    fs.mkdirSync(publicMirrorDir, { recursive: true });
    fs.symlinkSync(path.join(realPublicDir, 'build'), path.join(publicMirrorDir, 'build'), 'junction');
    fs.writeFileSync(centralDbPath, '');

    const env = serverEnv();
    const prepared = spawnSync('php', [path.join(here, 'prepare-local-smoke.php')], {
        env,
        stdio: 'inherit',
        cwd: path.dirname(realPublicDir),
    });
    if (prepared.status !== 0) {
        console.error('[e2e-smoke] database preparation failed');
        process.exit(1);
    }

    const server = spawn('php', ['-S', `${HOST}:${PORT}`, '-t', realPublicDir, path.join(here, 'router.php')], {
        env,
        stdio: 'inherit',
        cwd: realPublicDir,
    });
    server.on('exit', (code) => process.exit(code ?? 0));
    for (const signal of ['SIGINT', 'SIGTERM']) {
        process.on(signal, () => server.kill(signal));
    }
}
