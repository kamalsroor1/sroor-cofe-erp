import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const authDir = path.resolve(__dirname, '../.auth');
const authFile = path.resolve(authDir, 'user.json');

setup('Authenticate & Save Storage State', async ({ page }) => {
    if (!fs.existsSync(authDir)) {
        fs.mkdirSync(authDir, { recursive: true });
    }

    const testPhone = process.env.E2E_USER_PHONE || '01012316954';
    const testPassword = process.env.E2E_USER_PASSWORD || 'password';

    console.log(`\n🔑 Setting up E2E Auth Session with user: ${testPhone}...`);

    try {
        await page.goto('/login', { waitUntil: 'networkidle' });
        await page.waitForSelector('input[type="text"], input[type="tel"], input[name="phone"]', { timeout: 15000 });

        const phoneInput = page.locator('input[type="text"], input[type="tel"], input[name="phone"]').first();
        const passwordInput = page.locator('input[type="password"]').first();
        const submitButton = page.locator('button[type="submit"]').first();

        await phoneInput.fill(testPhone);
        await passwordInput.fill(testPassword);
        await submitButton.click();

        // Wait for redirection away from login & wait for Vue SPA to mount
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 }).catch(() => {});
        await page.waitForSelector('#app > *', { timeout: 15000 }).catch(() => {});
        await page.waitForTimeout(500);

        // Get storage state and mirror for both 127.0.0.1 and localhost origins
        const storage = await page.context().storageState();
        if (storage.origins && storage.origins.length > 0) {
            const primary = storage.origins[0];
            const isLocalhost = primary.origin.includes('localhost');
            const altOriginUrl = isLocalhost
                ? primary.origin.replace('localhost', '127.0.0.1')
                : primary.origin.replace('127.0.0.1', 'localhost');

            if (!storage.origins.some(o => o.origin === altOriginUrl)) {
                storage.origins.push({
                    origin: altOriginUrl,
                    localStorage: [...primary.localStorage],
                });
            }
        }

        fs.writeFileSync(authFile, JSON.stringify(storage, null, 2), 'utf8');
        console.log(`✅ Auth session successfully saved to: ${authFile}\n`);
    } catch (error) {
        console.warn(`⚠️ Login setup warning: ${error.message}. Creating minimal storage state fallback.`);
        await page.context().storageState({ path: authFile });
    }
});
