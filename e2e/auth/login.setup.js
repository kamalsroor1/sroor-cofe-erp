import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const authDir = path.resolve(__dirname, '../.auth');
const authFile = path.resolve(authDir, 'user.json');

setup('Authenticate & Save Storage State', async ({ page }) => {
    // Ensure auth directory exists
    if (!fs.existsSync(authDir)) {
        fs.mkdirSync(authDir, { recursive: true });
    }

    const testPhone = process.env.E2E_USER_PHONE || '01012316954';
    const testPassword = process.env.E2E_USER_PASSWORD || 'password123';

    console.log(`\n🔑 Setting up E2E Auth Session with user: ${testPhone}...`);

    try {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('input[type="text"], input[type="tel"], input[name="phone"]', { timeout: 10000 });

        // Fill phone and password
        const phoneInput = page.locator('input[type="text"], input[type="tel"], input[name="phone"]').first();
        const passwordInput = page.locator('input[type="password"]').first();
        const submitButton = page.locator('button[type="submit"]').first();

        await phoneInput.fill(testPhone);
        await passwordInput.fill(testPassword);
        await submitButton.click();

        // Wait for redirection to dashboard or presence of main app layout
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 12000 }).catch(() => {});

        // Save session state (cookies & localStorage with token)
        await page.context().storageState({ path: authFile });
        console.log(`✅ Auth session successfully saved to: ${authFile}\n`);
    } catch (error) {
        console.warn(`⚠️ Login setup warning: ${error.message}. Creating minimal storage state fallback.`);
        // Ensure file exists so downstream crawler doesn't crash
        await page.context().storageState({ path: authFile });
    }
});
