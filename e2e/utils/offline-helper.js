// OFFL-2 helpers: API fixtures for the offline-banner flow.
// Uses the token / tenant / store the auth-setup project stored for the SPA, so the
// fixture item lives in the same tenant and branch the browser test runs against.
// Local server only (playwright.config.js webServer). Never point at production.
import { expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const authFile = path.resolve(__dirname, '../.auth/user.json');

/** localStorage entries of the first origin in the stored auth state that holds a token. */
export function readAuthStorage() {
    if (!fs.existsSync(authFile)) return {};
    const state = JSON.parse(fs.readFileSync(authFile, 'utf8'));
    for (const origin of state.origins || []) {
        const entries = Object.fromEntries((origin.localStorage || []).map((e) => [e.name, e.value]));
        if (entries.auth_token) return entries;
    }
    return {};
}

/**
 * An APIRequestContext that talks to /api/v1 exactly like the SPA (Bearer + X-Tenant + X-Store-Id).
 * @param {import('@playwright/test').PlaywrightWorkerArgs['playwright']} playwright
 * @param {string} baseURL
 */
export async function createTenantApi(playwright, baseURL) {
    const storage = readAuthStorage();
    expect(
        storage.auth_token,
        'auth-setup stored no auth_token: the local tenant login failed (see docs/07-operations/local-testing.md §10)'
    ).toBeTruthy();

    const storeId = Number(storage.current_store_id);
    expect(Number.isInteger(storeId) && storeId > 0, 'auth-setup stored no concrete current_store_id').toBe(true);

    const extraHTTPHeaders = {
        Accept: 'application/json',
        Authorization: `Bearer ${storage.auth_token}`,
    };
    if (storage.tenant_id) extraHTTPHeaders['X-Tenant'] = storage.tenant_id;
    extraHTTPHeaders['X-Store-Id'] = String(storeId);

    const api = await playwright.request.newContext({ baseURL, extraHTTPHeaders });
    return { api, storeId };
}

async function expectOk(res, what) {
    if (!res.ok()) {
        throw new Error(`${what} failed: HTTP ${res.status()} ${await res.text()}`);
    }
    return res.json();
}

/** First unit of the tenant's own list (SETG-10 rejects any other unit). */
async function firstTenantUnit(api) {
    const body = await expectOk(await api.get('/api/v1/system/context'), 'GET /api/v1/system/context');
    const units = body?.data?.system?.inventory_units;
    expect(Array.isArray(units) && units.length > 0, 'tenant has no inventory_units').toBe(true);
    return units[0];
}

/**
 * Creates `E2E-OFFL-<ts>` and puts `quantity` of it into the active branch.
 * @returns {Promise<{ id: number, name: string, code: string, quantity: string }>}
 */
export async function createStockedItem(api, storeId, { prefix = 'E2E-OFFL', quantity = '5.000' } = {}) {
    const name = `${prefix}-${Date.now()}`;
    const unit = await firstTenantUnit(api);

    const created = await expectOk(
        await api.post('/api/v1/items', {
            data: {
                name,
                code: name,
                unit,
                cost_price: '10.000',
                selling_price: '25.000',
                min_stock_level: '0',
            },
        }),
        'POST /api/v1/items'
    );
    const id = created?.data?.id;
    expect(id, 'POST /api/v1/items returned no id').toBeTruthy();

    await expectOk(
        await api.post(`/api/v1/items/${id}/adjust-stock`, {
            data: { store_id: storeId, movement_type: 'stock_adjustment_in', quantity, notes: 'E2E OFFL-2 fixture' },
        }),
        `POST /api/v1/items/${id}/adjust-stock (in)`
    );

    return { id, name, code: name, quantity };
}

/**
 * Best-effort cleanup. The adjustment movement blocks a hard DELETE (Item::getDeletionBlockers),
 * so: take the stock back out, try DELETE, and if the ledger still blocks it, deactivate the item
 * so it no longer shows in POS. Returns what actually happened so the caller can log it.
 */
export async function removeStockedItem(api, storeId, item) {
    if (!item?.id) return 'nothing-to-remove';

    await api.post(`/api/v1/items/${item.id}/adjust-stock`, {
        data: {
            store_id: storeId,
            movement_type: 'stock_adjustment_out',
            quantity: item.quantity,
            notes: 'E2E OFFL-2 cleanup',
        },
    });

    const del = await api.delete(`/api/v1/items/${item.id}`);
    if (del.ok()) return 'deleted';

    const show = await api.get(`/api/v1/items/${item.id}`);
    const isActive = show.ok() ? (await show.json())?.data?.is_active : undefined;
    if (isActive !== false) {
        const toggled = await api.patch(`/api/v1/items/${item.id}/toggle-active`);
        if (toggled.ok()) return `deactivated (DELETE ${del.status()})`;
        return `left active (DELETE ${del.status()}, toggle ${toggled.status()})`;
    }
    return `already inactive (DELETE ${del.status()})`;
}

/**
 * Records every POST the page sends to the given API prefixes (attempted requests count,
 * including ones the offline network fails), so "no checkout was attempted" is provable.
 */
export function recordApiPosts(page, prefixes = ['/api/v1/pos/']) {
    const posts = [];
    page.on('request', (req) => {
        if (req.method() !== 'POST') return;
        const { pathname } = new URL(req.url());
        if (prefixes.some((p) => pathname.startsWith(p))) posts.push(pathname);
    });
    return posts;
}
