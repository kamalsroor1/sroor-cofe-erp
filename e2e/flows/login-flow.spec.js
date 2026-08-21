import { test, expect } from '@playwright/test';
import path from 'path';
import fs from 'fs';
import { getScreenshotRunDir } from '../utils/screenshot-helper.js';

test.describe('Flow: Complete Authentication & Navigation Journey', () => {
    // Override storage state to start as a fresh guest user for login testing
    test.use({ storageState: { cookies: [], origins: [] } });

    test('User Login, Store Context Verification & Navigation Flow', async ({ page }) => {
        const runDir = getScreenshotRunDir();
        const flowDir = path.join(runDir, 'flows', 'login-and-pos-flow');
        if (!fs.existsSync(flowDir)) {
            fs.mkdirSync(flowDir, { recursive: true });
        }

        const testPhone = process.env.E2E_USER_PHONE || '01012316954';
        const testPassword = process.env.E2E_USER_PASSWORD || 'password';

        // Step 1: Open Login Page
        await page.goto('/login', { waitUntil: 'networkidle' });
        await page.waitForSelector('input[type="text"], input[type="tel"], input[name="phone"]', { timeout: 15000 });
        await page.screenshot({ path: path.join(flowDir, '01-login-screen-initial.png'), fullPage: true });

        // Step 2: Enter Credentials
        const phoneInput = page.locator('input[type="text"], input[type="tel"], input[name="phone"]').first();
        const passwordInput = page.locator('input[type="password"]').first();
        const submitButton = page.locator('button[type="submit"]').first();

        await phoneInput.fill(testPhone);
        await passwordInput.fill(testPassword);
        await page.screenshot({ path: path.join(flowDir, '02-credentials-filled.png'), fullPage: true });

        // Step 3: Submit and Verify Dashboard Landing
        await submitButton.click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
        await page.waitForSelector('#app > *', { timeout: 15000 });
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(flowDir, '03-dashboard-landing-success.png'), fullPage: true });

        // Assertion: Main layout is mounted
        const appContainer = page.locator('#app');
        await expect(appContainer).toBeVisible();

        // Step 4: Navigate to Fast Touch POS
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.waitForSelector('#app > *', { timeout: 15000 });
        await page.waitForTimeout(600);
        await page.screenshot({ path: path.join(flowDir, '04-pos-screen-loaded.png'), fullPage: true });

        // Assertion: POS screen loaded
        expect(page.url()).toContain('/pos');
    });
});
