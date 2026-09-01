import { test, expect } from '@playwright/test';

test.describe('SettingsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: true },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    const dismissAnyModal = async (page) => {
        const modalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق"), button:has-text("فتح الوردية"), button:has-text("بدء الوردية")').first();
        if (await modalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await modalBtn.click({ force: true }).catch(() => {});
            await page.waitForTimeout(400);
        }
    };

    for (const vp of viewports) {
        test(`should render System Settings correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/settings', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Save Button
            const saveBtn = page.locator('main button:has-text("حفظ")').first();
            await expect(saveBtn).toBeVisible({ timeout: 15000 });

            // 2. Verify Navigation Section or Hub Cards
            if (vp.isMobile) {
                // Mobile Hub Mode (Cards)
                await expect(page.locator('main div:has-text("الهوية")').first()).toBeVisible();
            } else {
                // Desktop Sidebar Navigation (Buttons)
                await expect(page.locator('main button:has-text("الهوية")').first()).toBeVisible();
                await expect(page.locator('main button:has-text("المظهر")').first()).toBeVisible();
            }

            // 3. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow navigating between settings sections on desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        await page.goto('/settings', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Click Appearance Section
        const appearanceBtn = page.locator('main button:has-text("المظهر")').first();
        await expect(appearanceBtn).toBeVisible();
        await appearanceBtn.click({ force: true });
        await page.waitForTimeout(500);

        // Click Printing Section
        const printingBtn = page.locator('main button:has-text("الطباعة")').first();
        await expect(printingBtn).toBeVisible();
        await printingBtn.click({ force: true });
        await page.waitForTimeout(500);

        // Click Back to Branding Section
        const brandingBtn = page.locator('main button:has-text("الهوية")').first();
        await expect(brandingBtn).toBeVisible();
        await brandingBtn.click({ force: true });
        await page.waitForTimeout(500);
    });
});
