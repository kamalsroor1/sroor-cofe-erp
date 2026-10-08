// LOCAL smoke (real backend, throwaway DB): platform super-admin on the central host.
//  1. UI: password login -> tenants list shows the seeded tenant -> delete is refused with the
//     "deletion disabled" message and the tenant is still there.
//  2. API contract of the same journey (independent of the SPA), so a UI regression and a backend
//     regression are reported separately.
import { test, expect } from '@playwright/test';
import { TENANT_ID, credentials } from './smoke-env.mjs';
import { apiAs, collectConsoleErrors, loginAsSuperAdmin } from './smoke-helpers.js';

const TRASH_BUTTON = 'button:has([class*="lucide-trash"])';

test('super-admin UI: central login, tenants list, delete shows the disabled message', async ({ page }) => {
    const consoleErrors = collectConsoleErrors(page);

    const login = await loginAsSuperAdmin(page, {
        login: credentials.superAdminPhone,
        password: credentials.superAdminPassword,
    });
    expect(login.data.token).toBeTruthy();
    expect(await page.evaluate(() => localStorage.getItem('tenant_id')), 'central login carries no tenant').toBeNull();

    // ---- Tenants list -----------------------------------------------------------------------
    const listResponse = page.waitForResponse(
        (res) => new URL(res.url()).pathname === '/api/v1/super-admin/tenants' && res.request().method() === 'GET'
    );
    await page.goto('/super-admin/tenants');
    const list = await listResponse;
    expect(list.status()).toBe(200);
    const listBody = await list.json();
    const seeded = (listBody.tenants?.data ?? listBody.tenants ?? listBody.data?.data ?? listBody.data ?? []).find(
        (t) => t.id === TENANT_ID
    );
    expect(seeded, 'seeded tenant is listed').toBeTruthy();

    const row = page
        .locator('tr:visible, div:visible')
        .filter({ hasText: seeded.name })
        .filter({ has: page.locator(TRASH_BUTTON) });
    await expect(row.last()).toBeVisible();

    // ---- Delete -> confirm -> 403 + disabled message -----------------------------------------
    await row.last().locator(TRASH_BUTTON).first().click();
    const deleteResponse = page.waitForResponse(
        (res) => res.url().includes(`/api/v1/super-admin/tenants/${TENANT_ID}`) && res.request().method() === 'DELETE'
    );
    await page.locator('.swal2-confirm').click();
    const del = await deleteResponse;
    expect(del.status()).toBe(403);
    const delBody = await del.json();
    expect(delBody.success).toBe(false);

    // The dialog left on screen shows the server message, never a success state.
    await expect(page.locator('.swal2-popup .swal2-html-container')).toHaveText(delBody.message);
    await expect(page.locator('.swal2-popup .swal2-icon.swal2-success')).toHaveCount(0);

    const show = await apiAs(page, 'GET', `/api/v1/super-admin/tenants/${TENANT_ID}`);
    expect(show.status(), 'tenant still exists').toBe(200);

    // The browser logs the expected 403 of the DELETE as a resource error; anything else is a bug.
    expect(
        consoleErrors.filter((e) => !/status of 403/.test(e)),
        'super-admin console errors'
    ).toEqual([]);
});

test('super-admin API: tenants list and delete-disabled contract', async ({ request }) => {
    const loginRes = await request.post('/api/v1/auth/login', {
        headers: { Accept: 'application/json' },
        data: {
            login: credentials.superAdminPhone,
            password: credentials.superAdminPassword,
            device_name: 'e2e-smoke',
        },
    });
    expect(loginRes.status()).toBe(200);
    const token = (await loginRes.json()).data.token;
    const headers = {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
    };

    const list = await request.get('/api/v1/super-admin/tenants', { headers });
    expect(list.status()).toBe(200);
    expect(JSON.stringify(await list.json())).toContain(`"id":"${TENANT_ID}"`);

    for (const locale of ['ar', 'en']) {
        const del = await request.delete(`/api/v1/super-admin/tenants/${TENANT_ID}`, {
            headers: { ...headers, 'X-Locale': locale },
        });
        expect(del.status()).toBe(403);
        const body = await del.json();
        expect(body.success).toBe(false);
        expect(body.message, `translated message (${locale})`).toBeTruthy();
        expect(body.message, `no raw lang key (${locale})`).not.toMatch(/^super\./);
    }

    const show = await request.get(`/api/v1/super-admin/tenants/${TENANT_ID}`, {
        headers,
    });
    expect(show.status(), 'tenant still exists after the refused delete').toBe(200);

    // Unauthenticated callers never reach the endpoint.
    const guest = await request.delete(`/api/v1/super-admin/tenants/${TENANT_ID}`, {
        headers: { Accept: 'application/json' },
    });
    expect(guest.status()).toBe(401);
});
