// P0-X6 regression: the native thermal-print fallback must open the
// authenticated SPA print route (/invoices/{id}/print), and that route must
// never render invoice data for a visitor without a token.
//
// Local server only (playwright.config.js webServer). Never point at production.
import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const bridgeSource = path.resolve(__dirname, '../../backend/resources/js/Composables/useNativeBridge.js');
const posViewSource = path.resolve(__dirname, '../../backend/resources/js/views/POS/PosView.vue');
const autoprintOpen = /window\.open\(\s*`\/invoices\/\$\{[^}]+\}\/print\?autoprint=true`/;
const authFile = path.resolve(__dirname, '../.auth/user.json');

function readAuthStorage() {
    if (!fs.existsSync(authFile)) return {};
    const state = JSON.parse(fs.readFileSync(authFile, 'utf8'));
    for (const origin of state.origins || []) {
        const entries = Object.fromEntries((origin.localStorage || []).map((e) => [e.name, e.value]));
        if (entries.auth_token) return entries;
    }
    return {};
}

async function firstInvoice(request) {
    const storage = readAuthStorage();
    const token = storage.auth_token;
    expect(token, 'auth-setup did not store an auth_token').toBeTruthy();
    const headers = {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
    };
    if (storage.tenant_id) headers['X-Tenant'] = storage.tenant_id;
    if (storage.current_store_id) headers['X-Store-Id'] = storage.current_store_id;
    const res = await request.get('/api/v1/invoices?per_page=1', { headers });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const rows = body?.data?.data ?? body?.data ?? [];
    test.skip(!Array.isArray(rows) || rows.length === 0, 'No invoice in the local DB; create one via POS first.');
    return rows[0];
}

test.describe('Invoice print route (P0-X6)', () => {
    test('native bridge fallback targets the SPA print route, not /print-thermal', async () => {
        const src = fs.readFileSync(bridgeSource, 'utf8');
        expect(src).not.toContain('/print-thermal');
        expect(src).toMatch(autoprintOpen);
    });

    test('POS web fallback opens the same auto-printing receipt URL', async () => {
        const src = fs.readFileSync(posViewSource, 'utf8');
        expect(src).not.toContain('/print-thermal');
        expect(src).toMatch(autoprintOpen);
    });

    test('authenticated user sees the receipt and window.print is invoked', async ({ page, request }) => {
        const invoice = await firstInvoice(request);
        const consoleErrors = [];
        page.on('pageerror', (err) => consoleErrors.push(err.message));

        await page.addInitScript(() => {
            window.__printCalls = 0;
            window.print = () => {
                window.__printCalls += 1;
            };
        });

        await page.goto(`/invoices/${invoice.id}/print?autoprint=true`);

        await expect(page).toHaveURL(new RegExp(`/invoices/${invoice.id}/print\\?autoprint=true$`));
        await expect(page.getByText(String(invoice.invoice_number)).first()).toBeVisible();
        await expect.poll(() => page.evaluate(() => window.__printCalls)).toBeGreaterThan(0);
        expect(consoleErrors).toEqual([]);
    });

    test('visitor without a token is redirected to login and sees no invoice data', async ({ browser, request }) => {
        const invoice = await firstInvoice(request);
        const context = await browser.newContext({
            storageState: { cookies: [], origins: [] },
        });
        const page = await context.newPage();

        await page.goto(`/invoices/${invoice.id}/print`);

        await expect(page).toHaveURL(/\/login/);
        await expect(page.getByText(String(invoice.invoice_number))).toHaveCount(0);
        if (invoice.customer?.phone) {
            await expect(page.getByText(String(invoice.customer.phone))).toHaveCount(0);
        }

        await context.close();
    });
});
