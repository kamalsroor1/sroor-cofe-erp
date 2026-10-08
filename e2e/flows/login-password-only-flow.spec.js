import { test, expect } from '@playwright/test';

/**
 * P0-AUTH-2 regression: the login page is password-only.
 * - No passwordless "quick login" UI (no account picker, no quick-login submit).
 * - No request to the removed guest endpoints /auth/workspace-users or /auth/quick-login.
 * - No cached workspace user list in localStorage.
 */
test.describe('Flow: Password-only login (no quick-login, no user enumeration)', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login page shows phone + password form and never hits the enumeration endpoints', async ({ page }) => {
        const forbiddenRequests = [];
        const consoleErrors = [];

        page.on('request', (request) => {
            const url = request.url();
            if (url.includes('/auth/workspace-users') || url.includes('/auth/quick-login')) {
                forbiddenRequests.push(`${request.method()} ${url}`);
            }
        });
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        await page.goto('/login', { waitUntil: 'networkidle' });

        // The central host may route a guest to the workspace-connect step first; the
        // enumeration endpoints must not be called on either screen.
        if (new URL(page.url()).pathname.includes('/login')) {
            const passwordInput = page.locator('input[type="password"]').first();
            await expect(passwordInput).toBeVisible();
            await expect(page.locator('input[type="text"], input[type="tel"], input[name="phone"], input[name="login"]').first()).toBeVisible();
            await expect(page.locator('button[type="submit"]').first()).toBeVisible();

            // The quick-login account picker must be gone.
            await expect(page.locator('#quickAccountSelect')).toHaveCount(0);
        }

        const cachedUserKeys = await page.evaluate(() =>
            Object.keys(window.localStorage).filter((key) => /tenant_users|workspace[_-]?users|quick[_-]?login/i.test(key)),
        );

        expect(forbiddenRequests, 'login page must not call workspace-users / quick-login').toEqual([]);
        expect(cachedUserKeys, 'workspace user list must not be cached in localStorage').toEqual([]);
        expect(consoleErrors.filter((text) => /workspace-users|quick-login/i.test(text))).toEqual([]);
    });
});
