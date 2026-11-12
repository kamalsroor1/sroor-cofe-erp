import { test, expect } from '@playwright/test';

/**
 * SELL-01: forced password change.
 *
 * The API is mocked (deterministic, no real credentials): a flagged account (must_change_password)
 * can only reach /change-password until the change succeeds; an unflagged one never sees it.
 * Selectors are data-testids / roles, never translated text.
 */

test.use({ storageState: { cookies: [], origins: [] } });

const WORKSPACE = process.env.E2E_WORKSPACE_CODE || 'demo';
const OLD_SECRET = 'Old-Secret-1';
const NEW_SECRET = 'New-Secret-22';

const makeUser = (mustChange) => ({
  id: 901,
  name: 'E2E Flagged',
  email: null,
  phone: '01099900001',
  roles: ['admin'],
  permissions: [],
  theme_preference: 'dark',
  locale: 'ar',
  is_active: true,
  must_change_password: mustChange,
  default_store_id: 1,
});

const json = (route, body, status = 200) =>
  route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });

/**
 * Wires the mocked API. `state.flagged` flips what /auth/me and every guarded endpoint answer, so one
 * test can model "flag raised while the session is open".
 */
async function mockApi(page, state) {
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '');
    const allowedWhileFlagged = ['/auth/me', '/system/context', '/auth/logout', '/auth/change-password'];
    if (state.flagged && !allowedWhileFlagged.includes(path)) {
      return json(
        route,
        { success: false, message: 'Change your password first.', error_code: 'auth.password_change_required' },
        403
      );
    }
    return json(route, { success: true, data: [], meta: {}, pagination: {} });
  });
  await page.route('**/api/v1/auth/options', (route) =>
    json(route, { success: true, data: { quick_login: false, default_method: 'password' } })
  );
  await page.route('**/api/v1/system/context', (route) =>
    json(route, { success: true, data: { system: { system_theme_color: 'ibraa' }, locale: 'ar' } })
  );
  await page.route('**/api/v1/auth/me', (route) =>
    json(route, { success: true, data: { user: makeUser(state.flagged) } })
  );
  await page.route('**/api/v1/auth/change-password', async (route) => {
    state.changeRequests.push(route.request().postDataJSON());
    if (state.changeResponse) return state.changeResponse(route);
    state.flagged = false;
    return json(route, {
      success: true,
      message: 'ok',
      data: { must_change_password: false, user: makeUser(false) },
    });
  });
}

async function seedSession(page, user) {
  await page.addInitScript(
    ({ workspace, user }) => {
      localStorage.setItem('active_tenant', workspace);
      localStorage.setItem('tenant_id', workspace);
      localStorage.setItem('auth_token', 'e2e-token');
      localStorage.setItem('auth_user', JSON.stringify(user));
      localStorage.setItem('theme_preference', 'dark');
    },
    { workspace: WORKSPACE, user }
  );
}

const newState = (flagged) => ({ flagged, changeRequests: [], changeResponse: null });

const fillAndSubmit = async (page, { current = OLD_SECRET, next = NEW_SECRET, confirm = next } = {}) => {
  await page.getByTestId('current-password').fill(current);
  await page.getByTestId('new-password').fill(next);
  await page.getByTestId('confirm-password').fill(confirm);
  await page.getByTestId('change-password-submit').click();
};

test.describe('Forced password change (SELL-01)', () => {
  test('a flagged login lands on the change screen and cannot reach /pos or /', async ({ page }) => {
    const state = newState(true);
    await page.addInitScript((workspace) => {
      localStorage.setItem('active_tenant', workspace);
      localStorage.setItem('tenant_id', workspace);
    }, WORKSPACE);
    await mockApi(page, state);
    await page.route('**/api/v1/auth/login', (route) =>
      json(route, {
        success: true,
        data: {
          token: 'e2e-token',
          must_change_password: true,
          user: makeUser(true),
          store: { id: 1, name: 'Main' },
          stores: [],
        },
      })
    );

    await page.goto('/login');
    await page.locator('input[type="text"]').first().fill('01099900001');
    await page.locator('input[type="password"]').first().fill(OLD_SECRET);
    await page.locator('button[type="submit"]').first().click();

    await expect(page).toHaveURL(/\/change-password/);
    await expect(page.getByTestId('change-password-screen')).toBeVisible();

    for (const target of ['/pos', '/', '/invoices', '/login']) {
      await page.goto(target);
      await expect(page).toHaveURL(/\/change-password/);
      await expect(page.getByTestId('change-password-form')).toBeVisible();
    }
  });

  test('a successful change continues to the route that was requested', async ({ page }) => {
    const state = newState(true);
    await seedSession(page, makeUser(true));
    await mockApi(page, state);

    await page.goto('/invoices');
    await expect(page).toHaveURL(/\/change-password\?redirect=(%2F|\/)invoices/);

    await fillAndSubmit(page);

    await expect(page).toHaveURL(/\/invoices$/);
    await expect(page.getByTestId('change-password-screen')).toHaveCount(0);
    expect(state.changeRequests).toEqual([
      { current_password: OLD_SECRET, password: NEW_SECRET, password_confirmation: NEW_SECRET },
    ]);

    const stored = await page.evaluate(() => JSON.stringify({ ...localStorage }));
    expect(stored).not.toContain(OLD_SECRET);
    expect(stored).not.toContain(NEW_SECRET);
    expect(JSON.parse(await page.evaluate(() => localStorage.getItem('auth_user'))).must_change_password).toBe(false);
  });

  test('a wrong current password shows the field error and keeps the user on the screen', async ({ page }) => {
    const state = newState(true);
    state.changeResponse = (route) =>
      json(
        route,
        { success: false, message: 'invalid', errors: { current_password: ['The current password is incorrect.'] } },
        422
      );
    await seedSession(page, makeUser(true));
    await mockApi(page, state);

    await page.goto('/change-password');
    await fillAndSubmit(page, { current: 'Wrong-Secret-9' });

    await expect(page.getByTestId('current-password')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByTestId('new-password')).toHaveAttribute('aria-invalid', 'false');
    await expect(page).toHaveURL(/\/change-password/);
  });

  test('client-side checks block a short or unconfirmed password before any request', async ({ page }) => {
    const state = newState(true);
    await seedSession(page, makeUser(true));
    await mockApi(page, state);

    await page.goto('/change-password');
    await fillAndSubmit(page, { next: 'short', confirm: 'different' });

    await expect(page.getByTestId('new-password')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.getByTestId('confirm-password')).toHaveAttribute('aria-invalid', 'true');
    expect(state.changeRequests).toHaveLength(0);
  });

  test('a throttled or locked response shows a clear message', async ({ page }) => {
    const state = newState(true);
    state.changeResponse = (route) => json(route, { message: 'Too Many Attempts.' }, 429);
    await seedSession(page, makeUser(true));
    await mockApi(page, state);

    await page.goto('/change-password');
    await fillAndSubmit(page);

    await expect(page.getByTestId('change-password-error')).toBeVisible();
    await expect(page).toHaveURL(/\/change-password/);
  });

  test('the password visibility toggle switches the field type', async ({ page }) => {
    await seedSession(page, makeUser(true));
    await mockApi(page, newState(true));

    await page.goto('/change-password');
    const field = page.getByTestId('new-password');
    await expect(field).toHaveAttribute('type', 'password');
    await page.getByTestId('password-visibility-toggle').nth(1).click();
    await expect(field).toHaveAttribute('type', 'text');
  });

  test('an unflagged user never sees the change screen', async ({ page }) => {
    await seedSession(page, makeUser(false));
    await mockApi(page, newState(false));

    await page.goto('/change-password');

    await expect(page).not.toHaveURL(/\/change-password/);
    await expect(page.getByTestId('change-password-screen')).toHaveCount(0);
  });

  test('a 403 with the error code in the middle of a session routes to the screen', async ({ page }) => {
    const state = newState(false);
    await seedSession(page, makeUser(false));
    await mockApi(page, state);

    await page.goto('/profile');
    await expect(page).toHaveURL(/\/profile/);
    await expect(page.getByTestId('change-password-screen')).toHaveCount(0);

    state.flagged = true;
    await page.evaluate(() => window.spaRouter.push('/invoices'));

    await expect(page).toHaveURL(/\/change-password/);
    await expect(page.getByTestId('change-password-form')).toBeVisible();
  });

  test('logout from the screen returns to the sign-in', async ({ page }) => {
    await seedSession(page, makeUser(true));
    await mockApi(page, newState(true));

    await page.goto('/change-password');
    await page.getByTestId('change-password-logout').click();

    await expect(page).toHaveURL(/\/login/);
    expect(await page.evaluate(() => localStorage.getItem('auth_token'))).toBeNull();
  });
});
