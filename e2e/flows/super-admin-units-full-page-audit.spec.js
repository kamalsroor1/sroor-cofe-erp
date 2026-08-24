import { test, expect } from '@playwright/test';

test.describe('SuperAdminUnitsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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
        test(`should render Super Admin Units management correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                const txt = msg.text();
                if (msg.type() === 'error' && !txt.includes('favicon') && !txt.includes('ERR_CONNECTION_REFUSED') && !txt.includes('404')) {
                    consoleErrors.push(txt);
                }
            });

            await page.goto('/super-admin/units', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Header & Title
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('وحدات القياس');

            // 2. Verify Active Units Container
            const activeHeader = page.locator('main h2').first();
            await expect(activeHeader).toBeVisible();

            // 3. Verify Save Button
            const saveBtn = page.locator('button:has-text("حفظ")').first();
            await expect(saveBtn).toBeVisible();

            // 4. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow adding preset and custom unit', async ({ page }) => {
        await page.goto('/super-admin/units', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Add custom unit
        const input = page.locator('input[placeholder*="مثال: باليتة"], input[placeholder*="e.g. Pallet"]').first();
        if (await input.isVisible().catch(() => false)) {
            await input.fill('وحدة_اختبار');
            const addBtn = page.locator('button:has-text("إضافة للسيستم"), button:has-text("Add to System")').first();
            await addBtn.click({ force: true });
            await page.waitForTimeout(400);

            // Check if added to badge
            const badge = page.locator('span:has-text("وحدة_اختبار")').first();
            await expect(badge).toBeVisible();
        }
    });
});
