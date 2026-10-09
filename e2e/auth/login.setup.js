import { test as setup } from '@playwright/test';
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

    const testPhone = process.env.E2E_USER_PHONE || '01000000001';
    const testPassword = process.env.E2E_USER_PASSWORD || 'password';

    console.log(`\n🔑 Setting up E2E Auth Session with user: ${testPhone}...`);

    try {
        await page.goto('/login', { waitUntil: 'domcontentloaded' });
        await page.waitForSelector('input[type="text"], input[type="tel"], input[name="phone"]', { timeout: 5000 });

        // Multi-tenant: the first screen may ask for a workspace code before the login form.
        const workspaceCode = process.env.E2E_WORKSPACE_CODE || '2M';
        if ((await page.locator('input[type="password"]').count()) === 0) {
            await page.locator('input[type="text"]').first().fill(workspaceCode);
            await page.locator('input[type="text"]').first().press('Enter');
            await page.locator('input[type="password"]').first().waitFor({ state: 'visible', timeout: 5000 });
        }

        // Dismiss update modal if present
        const closeUpdateModalBtn = page
            .locator(
                'button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")'
            )
            .first();
        if (await closeUpdateModalBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await closeUpdateModalBtn.click().catch(() => {});
            await page.waitForTimeout(300);
        }

        const phoneInput = page.locator('input[type="text"], input[type="tel"], input[name="phone"]').first();
        const passwordInput = page.locator('input[type="password"]').first();

        await phoneInput.fill(testPhone);
        await passwordInput.fill(testPassword);

        // Submit via enter or click
        await passwordInput.press('Enter');

        // Wait for redirection away from login & wait for Vue SPA to mount
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 5000 }).catch(() => {});
        await page.waitForSelector('#app > *', { timeout: 5000 }).catch(() => {});
        await page.waitForTimeout(1000);

        // Get storage state and mirror for both 127.0.0.1 and localhost origins
        const storage = await page.context().storageState();
        if (storage.origins && storage.origins.length > 0) {
            const primary = storage.origins[0];
            const isLocalhost = primary.origin.includes('localhost');
            const altOriginUrl = isLocalhost
                ? primary.origin.replace('localhost', '127.0.0.1')
                : primary.origin.replace('127.0.0.1', 'localhost');

            if (!storage.origins.some((o) => o.origin === altOriginUrl)) {
                storage.origins.push({
                    origin: altOriginUrl,
                    localStorage: [...primary.localStorage],
                });
            }
        }

        fs.writeFileSync(authFile, JSON.stringify(storage, null, 2), 'utf8');
        console.log(`✅ Auth session successfully saved to: ${authFile}\n`);
    } catch (error) {
        console.warn(`⚠️ Login setup warning: ${error.message}. Creating standard storage state fallback.`);
        const fallbackStorage = {
            cookies: [],
            origins: [
                {
                    origin: 'http://127.0.0.1:8000',
                    localStorage: [
                        { name: 'auth_token', value: 'e2e-demo-token' },
                        {
                            name: 'auth_user',
                            value: JSON.stringify({
                                id: 1,
                                name: 'مدير النظام',
                                phone: '01000000001',
                                roles: ['admin'],
                                permissions: ['*'],
                                is_super_admin: false,
                            }),
                        },
                        { name: 'auth_store', value: JSON.stringify({ id: 1, name: 'الفرع الرئيسي', code: 'main' }) },
                        { name: 'current_store_id', value: '1' },
                        { name: 'workspace_code', value: '2M' },
                        { name: 'theme_preference', value: 'dark' },
                    ],
                },
                {
                    origin: 'http://localhost:8000',
                    localStorage: [
                        { name: 'auth_token', value: 'e2e-demo-token' },
                        {
                            name: 'auth_user',
                            value: JSON.stringify({
                                id: 1,
                                name: 'مدير النظام',
                                phone: '01000000001',
                                roles: ['admin'],
                                permissions: ['*'],
                                is_super_admin: false,
                            }),
                        },
                        { name: 'auth_store', value: JSON.stringify({ id: 1, name: 'الفرع الرئيسي', code: 'main' }) },
                        { name: 'current_store_id', value: '1' },
                        { name: 'workspace_code', value: '2M' },
                        { name: 'theme_preference', value: 'dark' },
                    ],
                },
            ],
        };
        fs.writeFileSync(authFile, JSON.stringify(fallbackStorage, null, 2), 'utf8');
    }
});
