// Helpers for the LOCAL smoke suite. Selectors: ids, data-testid, roles and icon classes only.
import { expect } from '@playwright/test';

/** Console errors that are not caused by the app (browser noise). */
const IGNORED_CONSOLE = [/favicon/i, /net::ERR_ABORTED/i, /Download the Vue Devtools/i];

export function collectConsoleErrors(page) {
    const errors = [];
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        const text = msg.text();
        if (!IGNORED_CONSOLE.some((re) => re.test(text))) errors.push(text);
    });
    page.on('pageerror', (err) => errors.push(err.message));
    return errors;
}

/** Turns window.print() into a counter for every page of the context (popups included). */
export async function stubPrint(context) {
    await context.addInitScript(() => {
        window.__smokePrintCalls = 0;
        window.print = () => {
            window.__smokePrintCalls += 1;
        };
    });
}

async function submitPasswordForm(page, login, password) {
    const loginInput = page.locator('#login');
    const passwordInput = page.locator('#password');
    await expect(passwordInput).toBeVisible();
    await loginInput.fill(login);
    await passwordInput.fill(password);

    const loginResponse = page.waitForResponse(
        (res) => res.url().includes('/api/v1/auth/login') && res.request().method() === 'POST'
    );
    await passwordInput.press('Enter');
    const res = await loginResponse;
    expect(res.status(), 'password login must succeed').toBe(200);
    const body = await res.json();
    expect(body.success).toBe(true);
    return body;
}

/** Tenant login on the central host: workspace code -> password form. */
export async function loginToTenant(page, { tenantCode, login, password }) {
    await page.goto(`/connect?code=${encodeURIComponent(tenantCode)}`);
    await page.waitForURL((url) => url.pathname === '/login');
    const body = await submitPasswordForm(page, login, password);
    await page.waitForURL((url) => !url.pathname.startsWith('/login') && !url.pathname.startsWith('/connect'));
    return body;
}

/** Platform super-admin login on the central host (no workspace). */
export async function loginAsSuperAdmin(page, { login, password }) {
    await page.goto('/login?central=1');
    const contextResponse = page.waitForResponse((res) => new URL(res.url()).pathname === '/api/v1/system/context');
    const body = await submitPasswordForm(page, login, password);
    const context = await contextResponse;
    expect(context.status(), 'GET /system/context right after the super-admin login').toBe(200);
    await page.waitForURL((url) => url.pathname.startsWith('/super-admin'));
    return body;
}

/** Calls the API with the token/tenant/store the SPA stored in localStorage. */
export async function apiAs(page, method, url) {
    const headers = await page.evaluate(() => {
        const h = { Accept: 'application/json' };
        const token = localStorage.getItem('auth_token');
        const tenant = localStorage.getItem('tenant_id');
        const store = localStorage.getItem('current_store_id');
        if (token) h.Authorization = `Bearer ${token}`;
        if (tenant) h['X-Tenant'] = tenant;
        if (store) h['X-Store-Id'] = store;
        return h;
    });
    return page.request.fetch(url, { method, headers });
}
