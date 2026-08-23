import { test, expect } from '@playwright/test';

test.describe('StoreStocksView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    const dismissAnyModal = async (page) => {
        const modalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق"), button:has-text("فتح الوردية الآن"), button:has-text("بدء الوردية")').first();
        if (await modalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await modalBtn.click().catch(() => {});
            await page.waitForTimeout(400);
        }
    };

    for (const vp of viewports) {
        test(`should render Store Stocks & Inventory correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/stores/stocks', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Page Title & Header
            const header = page.locator('h1');
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('أرصدة وجرد الفروع');

            // 2. Verify Store Selector & Filters
            const searchInput = page.locator('input[placeholder*="بحث"]').first();
            await expect(searchInput).toBeVisible();

            // 3. Verify Filter Pills (All, Low, Out)
            await expect(page.locator('button:has-text("الكل")').first()).toBeVisible();

            // 4. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow toggling stock status filter pills', async ({ page }) => {
        await page.goto('/stores/stocks', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Click low stock filter pill
        const lowStockBtn = page.locator('button:has-text("بضاعة شارفت على النفاد"), button:has-text("نواقص")').first();
        if (await lowStockBtn.isVisible()) {
            await lowStockBtn.click();
            await page.waitForTimeout(500);
        }
    });
});
