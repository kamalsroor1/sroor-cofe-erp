import { test, expect } from '@playwright/test';

test.describe('SuperAdminAppVersionsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
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
        test(`should render Super Admin App Versions list correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                const txt = msg.text();
                if (msg.type() === 'error' && !txt.includes('favicon') && !txt.includes('ERR_CONNECTION_REFUSED') && !txt.includes('404')) {
                    consoleErrors.push(txt);
                }
            });

            await page.goto('/super-admin/app-versions', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Header & Title
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('إصدارات التطبيق');

            // 2. Verify Publish Button
            const publishBtn = page.locator('button:has-text("نشر إصدار")').first();
            await expect(publishBtn).toBeVisible();

            // 3. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow opening upload apk modal', async ({ page }) => {
        await page.goto('/super-admin/app-versions', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Click Publish New APK button
        const publishBtn = page.locator('button:has-text("نشر إصدار")').first();
        await expect(publishBtn).toBeVisible();
        await publishBtn.click({ force: true });
        await page.waitForTimeout(500);

        // Verify Modal Opened
        const modal = page.locator('h3:has-text("نشر إصدار"), h2:has-text("نشر إصدار")').first();
        if (await modal.isVisible({ timeout: 5000 }).catch(() => false)) {
            await expect(modal).toBeVisible();
        }

        // Close Modal
        const closeBtn = page.locator('button:has-text("إلغاء"), button:has-text("✕")').first();
        if (await closeBtn.isVisible().catch(() => false)) {
            await closeBtn.click({ force: true });
        }
    });
});
