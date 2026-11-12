import { test, expect } from '@playwright/test';

/**
 * SELL-01 (platform console): reset the shop owner's password and approve a signup with an
 * automatic password. Mocked API on the admin-host shell (like central-login.spec.js); the
 * "temporary passwords" below are dummy values, never real credentials.
 *
 *  1. reset owner: confirm -> step-up 2FA (existing prompt) -> retried call -> one-time modal
 *     with owner details; the wa.me link is built only on click and carries the Arabic text;
 *  2. matched_by=fallback shows the warning;
 *  3. approve with the default "generate automatically": no owner_password is sent, the returned
 *     owner_credentials are shown once; the typed-password path still sends what was typed.
 */

test.use({ storageState: { cookies: [], origins: [] } });

const DUMMY_TEMP = 'Dummy-Temp-0000';
const OPERATOR = {
  id: 1,
  name: 'Platform Operator',
  email: 'operator@example.test',
  is_active: true,
  two_factor_enabled: true,
  roles: ['super_admin'],
  permissions: ['super_admin.tenants.view', 'super_admin.tenants.manage'],
};
const TENANT = { id: 'acme', name: 'Acme Coffee', domain: 'acme.example.test', status: 'active', plan: { name: 'Pro' } };
const SIGNUP = {
  id: 55,
  business_name: 'Beans Corner',
  contact_name: 'Owner One',
  phone: '01012345678',
  email: 'owner@beans.example.test',
  city: 'Cairo',
  business_type: 'coffee',
  plan_code: null,
  preferred_subdomain: 'beans',
  notes: null,
  status: 'pending',
  created_at: '2026-11-12T08:00:00+00:00',
};

const json = (route, body, status = 200) =>
  route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });

// Two ways to get the console shell:
//  - default: inject <meta name="app-context" content="central"> into the served HTML (like
//    central-login.spec.js), for a local stack that serves /super-admin on the base URL;
//  - E2E_CENTRAL_HOST: run against the real admin host (APP_URL=http://admin.sroor.test:8052, the host
//    mapped to loopback in the browser); the server renders the shell itself, nothing is injected.
const REAL_ADMIN_HOST = !!process.env.E2E_CENTRAL_HOST;

async function serveCentralShell(page) {
  if (REAL_ADMIN_HOST) return;
  await page.route(
    (url) => url.pathname.startsWith('/super-admin'),
    async (route) => {
      if (route.request().resourceType() !== 'document') return route.fallback();
      const response = await route.fetch();
      let html = await response.text();
      if (!html.includes('name="app-context"')) {
        html = html.replace('<head>', '<head>\n    <meta name="app-context" content="central">');
      }
      return route.fulfill({ response, body: html, headers: { ...response.headers(), 'content-type': 'text/html' } });
    }
  );
}

async function seedOperator(page) {
  await page.addInitScript((operator) => {
    localStorage.setItem('central_auth_token', 'central-test-token');
    localStorage.setItem('central_auth_user', JSON.stringify(operator));
    localStorage.setItem('central_auth_started_at', String(Date.now()));
    localStorage.setItem('central_auth_expires_at', new Date(Date.now() + 4 * 3600 * 1000).toISOString());
    localStorage.setItem('theme_preference', 'dark');
  }, OPERATOR);
}

async function mockConsoleApi(page, state) {
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname.replace(/^\/api\/v1/, '');
    const method = request.method();

    if (path === '/super-admin/auth/me') return json(route, { success: true, data: OPERATOR });
    if (path === '/super-admin/auth/step-up') {
      state.stepUpDone = true;
      return json(route, { success: true });
    }
    if (path === '/super-admin/tenants/acme' && method === 'GET') {
      return json(route, {
        success: true,
        data: { tenant: { ...TENANT, enabled_features: [] }, stats: {}, features: [], global_units: [], allowed_units: [] },
      });
    }
    if (path === '/super-admin/tenants/acme/reset-owner-password') {
      state.resetCalls += 1;
      if (!state.stepUpDone) {
        return json(
          route,
          { success: false, message: 'Recent 2FA required.', error_code: 'central_auth.step_up_required' },
          403
        );
      }
      return json(route, {
        success: true,
        message: 'ok',
        data: {
          tenant_id: 'acme',
          owner: { id: 3, name: 'Owner One', email: 'owner@acme.example.test', phone: '01012345678' },
          must_change_password: true,
          temporary_password: DUMMY_TEMP,
          matched_by: state.matchedBy,
        },
      });
    }
    if (path === '/super-admin/signup-requests' && method === 'GET') {
      return json(route, { success: true, data: [SIGNUP], meta: { current_page: 1, last_page: 1, total: 1 } });
    }
    if (path === '/super-admin/plans') return json(route, { success: true, data: { plans: [] } });
    if (path === '/super-admin/signup-requests/55/approve') {
      state.approvals.push(request.postDataJSON());
      return json(route, {
        success: true,
        message: 'ok',
        data: { ...SIGNUP, status: 'approved' },
        owner_credentials: state.approvals.at(-1).owner_password
          ? null
          : { login: 'owner@beans.example.test', temporary_password: DUMMY_TEMP, must_change_password: true },
      });
    }
    return json(route, { success: true, data: [] });
  });
}

const newState = (matchedBy = 'email') => ({ stepUpDone: false, resetCalls: 0, matchedBy, approvals: [] });

test.describe('Console temporary passwords (SELL-01)', () => {
  test('owner reset runs the step-up prompt, then shows the one-time modal and a click-built WhatsApp link', async ({
    page,
  }) => {
    const state = newState();
    await serveCentralShell(page);
    await seedOperator(page);
    await mockConsoleApi(page, state);
    await page.addInitScript(() => {
      window.__opened = [];
      window.open = (...args) => {
        window.__opened.push(args);
        return null;
      };
    });

    await page.goto('/super-admin/tenants/acme');
    await page.getByTestId('reset-owner-password').click();
    await page.locator('.swal2-confirm').click();

    // existing step-up prompt (the first call answered 403 step_up_required)
    await page.locator('input[name="code"]').fill('123456');
    await page.locator('input[name="code"]').press('Enter');

    await expect(page.getByTestId('temporary-password')).toHaveText(DUMMY_TEMP);
    await expect(page.getByTestId('owner-details')).toContainText('owner@acme.example.test');
    await expect(page.getByTestId('owner-fallback-warning')).toHaveCount(0);
    expect(state.resetCalls).toBe(2);

    // the wa.me URL (it contains the password) is not in the DOM before the click
    expect(await page.locator('a[href*="wa.me"]').count()).toBe(0);
    await page.getByTestId('whatsapp-temporary-password').click();
    const opened = await page.evaluate(() => window.__opened);
    expect(opened).toHaveLength(1);
    const url = new URL(opened[0][0]);
    expect(url.origin + url.pathname).toBe('https://wa.me/201012345678');
    const text = url.searchParams.get('text');
    expect(text).toContain(DUMMY_TEMP);
    expect(text).toContain('owner@acme.example.test');
    expect(text).toMatch(/[؀-ۿ]/);

    // closing drops it from the page and from storage
    await page.getByTestId('temporary-password-done').click();
    await expect(page.getByTestId('temporary-password')).toHaveCount(0);
    expect(await page.evaluate(() => JSON.stringify({ ...localStorage, ...sessionStorage }))).not.toContain(DUMMY_TEMP);
  });

  test('a fallback match is called out so the operator can confirm who they are talking to', async ({ page }) => {
    const state = newState('fallback');
    state.stepUpDone = true;
    await serveCentralShell(page);
    await seedOperator(page);
    await mockConsoleApi(page, state);

    await page.goto('/super-admin/tenants/acme');
    await page.getByTestId('reset-owner-password').click();
    await page.locator('.swal2-confirm').click();

    await expect(page.getByTestId('owner-fallback-warning')).toBeVisible();
    await expect(page.getByTestId('owner-details')).toBeVisible();
  });

  test('approve defaults to automatic generation and shows the credentials once', async ({ page }) => {
    const state = newState();
    await serveCentralShell(page);
    await seedOperator(page);
    await mockConsoleApi(page, state);

    await page.goto('/super-admin/signup-requests');
    await page.getByTestId('signup-approve').click();

    await expect(page.getByTestId('approve-auto-password')).toBeChecked();
    await expect(page.getByTestId('approve-manual-password')).toHaveCount(0);
    await page.locator('form button[type="submit"]').click();

    await expect(page.getByTestId('temporary-password')).toHaveText(DUMMY_TEMP);
    expect(state.approvals).toHaveLength(1);
    expect(state.approvals[0]).not.toHaveProperty('owner_password');
    expect(state.approvals[0]).not.toHaveProperty('generate_password');
  });

  test('the typed-password path still sends the typed password and shows no credentials', async ({ page }) => {
    const state = newState();
    await serveCentralShell(page);
    await seedOperator(page);
    await mockConsoleApi(page, state);

    await page.goto('/super-admin/signup-requests');
    await page.getByTestId('signup-approve').click();

    await page.locator('label').filter({ has: page.getByTestId('approve-auto-password') }).click();
    await expect(page.getByTestId('approve-auto-password')).not.toBeChecked();
    const typed = page.getByTestId('approve-manual-password').locator('input');
    await typed.fill('Typed-By-Operator-1');
    await page.locator('form button[type="submit"]').click();

    await expect(page.getByTestId('approve-modal-close')).toBeVisible();
    expect(state.approvals[0].owner_password).toBe('Typed-By-Operator-1');
    await expect(page.getByTestId('temporary-password')).toHaveCount(0);
  });
});
