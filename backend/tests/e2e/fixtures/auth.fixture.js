import { test as base } from '@playwright/test';

// Default: the local `demo` tenant admin (scripts/local/setup-local.ps1); the central admin no longer logs into a tenant.
const E2E_USER_PHONE = process.env.E2E_USER_PHONE || '01000000201';

/**
 * Custom Playwright test fixture with pre-authenticated mobile session
 */
export const test = base.extend({
    authenticatedPage: async ({ page }, use) => {
        // 1. Navigate to Login
        await page.goto('/login');

        // 2. Fill Admin Credentials
        const phoneInput = page.locator('input[type="text"], input[type="tel"]').first();
        const passInput = page.locator('input[type="password"]').first();
        const submitBtn = page.locator('button[type="submit"]').first();

        await phoneInput.fill(E2E_USER_PHONE);
        await passInput.fill('password');
        await submitBtn.click();

        // 3. Wait for Dashboard landing
        await page.waitForURL('/', { timeout: 10000 });

        // 4. Pass authenticated page to test
        await use(page);
    },
});

export { expect } from '@playwright/test';
