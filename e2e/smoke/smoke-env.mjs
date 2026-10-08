// Shared settings for the LOCAL smoke harness (e2e/smoke).
//
// Everything here points at a throwaway environment:
//  - the central DB is a fresh sqlite file in the OS temp dir;
//  - the tenant DB is backend/database/e2e_smoke_<tenant>.sqlite (gitignored, deleted on teardown);
//  - the server listens on 127.0.0.1 only.
// Credentials are random per run and travel through process.env, so nothing secret is stored in the repo.
import crypto from 'node:crypto';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

export const repoRoot = path.resolve(here, '..', '..');
export const backendDir = path.join(repoRoot, 'backend');
export const realPublicDir = path.join(backendDir, 'public');
export const workDir = path.join(os.tmpdir(), 'sroor-e2e-smoke');
export const publicMirrorDir = path.join(workDir, 'public');
export const centralDbPath = path.join(workDir, 'central.sqlite');

export const HOST = '127.0.0.1';
export const PORT = Number(process.env.E2E_SMOKE_PORT || 8077);
export const BASE_URL = `http://${HOST}:${PORT}`;

export const TENANT_ID = 'e2esmoke';
export const TENANT_DB_PREFIX = 'e2e_smoke_';
export const tenantDbPath = path.join(backendDir, 'database', `${TENANT_DB_PREFIX}${TENANT_ID}.sqlite`);
// FilesystemTenancyBootstrapper suffix: storage_path() . '/tenant' . <id>
export const tenantStorageDir = path.join(backendDir, 'storage', `tenant${TENANT_ID}`);

export const ITEM = {
    code: 'E2E-SMOKE-01',
    name: 'بن تجريبي سموك',
    price: '120.500',
    stock: '25.000',
};

/** Hosts the harness is allowed to talk to. Anything else aborts the run. */
export function assertLocalUrl(url) {
    const { hostname } = new URL(url);
    if (!['127.0.0.1', 'localhost', '::1', '[::1]'].includes(hostname)) {
        throw new Error(`[e2e-smoke] refusing to run against non-local URL: ${url}`);
    }
}

function ensureSecret(name, factory) {
    if (!process.env[name]) process.env[name] = factory();
    return process.env[name];
}

// Generated once in the Playwright main process; workers and the web server inherit them.
export const credentials = {
    tenantAdminPhone: ensureSecret('E2E_SMOKE_TENANT_PHONE', () => '0100' + String(crypto.randomInt(1000000, 9999999))),
    tenantAdminPassword: ensureSecret('E2E_SMOKE_TENANT_PASSWORD', () => crypto.randomBytes(12).toString('base64url')),
    superAdminPhone: ensureSecret(
        'E2E_SMOKE_SUPERADMIN_PHONE',
        () => '0101' + String(crypto.randomInt(1000000, 9999999))
    ),
    superAdminPassword: ensureSecret('E2E_SMOKE_SUPERADMIN_PASSWORD', () =>
        crypto.randomBytes(12).toString('base64url')
    ),
};

/** Environment for every PHP process of the harness (prepare script + web server). */
export function serverEnv() {
    return {
        ...process.env,
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_URL: BASE_URL,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: centralDbPath,
        TENANT_DB_PREFIX,
        CENTRAL_DOMAIN: 'localhost',
        CACHE_STORE: 'array',
        SESSION_DRIVER: 'array',
        QUEUE_CONNECTION: 'database',
        MAIL_MAILER: 'array',
        BROADCAST_CONNECTION: 'null',
        LOG_CHANNEL: 'stderr',
        LOG_LEVEL: 'warning',
        TELESCOPE_ENABLED: 'false',
        PULSE_ENABLED: 'false',
        TELEGRAM_NOTIFICATIONS_ENABLED: 'false',
        TELEGRAM_SCHEDULED_JOBS_ENABLED: 'false',
        TELEGRAM_BOT_TOKEN: '',
        TELEGRAM_CHAT_ID: '',
        QUICK_LOGIN_ENABLED: 'false',
        E2E_SMOKE_CENTRAL_DB: centralDbPath,
        E2E_SMOKE_REAL_PUBLIC: realPublicDir,
        E2E_SMOKE_PUBLIC_MIRROR: publicMirrorDir,
        E2E_SMOKE_TENANT_ID: TENANT_ID,
        E2E_SMOKE_ITEM_CODE: ITEM.code,
        E2E_SMOKE_ITEM_NAME: ITEM.name,
        E2E_SMOKE_ITEM_PRICE: ITEM.price,
        E2E_SMOKE_ITEM_STOCK: ITEM.stock,
    };
}
