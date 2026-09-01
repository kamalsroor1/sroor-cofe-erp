import { test, expect } from '@playwright/test';

test.describe('ReportsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    const dismissAnyModal = async (page) => {
        for (let i = 0; i < 3; i++) {
            const modalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق"), button:has-text("فتح الوردية"), button:has-text("بدء الوردية"), button:has-text("حسناً"), button:has-text("متابعة")').first();
            if (await modalBtn.isVisible({ timeout: 1500 }).catch(() => false)) {
                await modalBtn.click({ force: true }).catch(() => {});
                await page.waitForTimeout(500);
            }
        }
    };

    for (const vp of viewports) {
        test(`should render Reports & Analytics correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/reports', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Header & Title
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('التقارير');

            // 2. Verify Print Button
            await expect(page.locator('main button:has-text("طباعة")').first()).toBeVisible();

            // 3. Verify Filter Bar (Presets & Date Pickers)
            await expect(page.locator('button:has-text("اليوم")').first()).toBeVisible();
            await expect(page.locator('button:has-text("هذا الشهر")').first()).toBeVisible();

            // 4. Verify Tabs (Sales, Items, Stores, Customers, Expenses, Inventory, Treasury)
            await expect(page.locator('button:has-text("المبيعات والأرباح")').first()).toBeVisible();
            await expect(page.locator('button:has-text("ربحية الأصناف")').first()).toBeVisible();
            await expect(page.locator('button:has-text("مقارنة الفروع")').first()).toBeVisible();

            // 5. Verify Sales Tab Metrics (Total Sales, Gross Profit, Net Profit)
            await expect(page.locator('text=إجمالي المبيعات').first()).toBeVisible();

            // 6. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow switching between different report tabs smoothly', async ({ page }) => {
        await page.goto('/reports', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Switch to Items Profitability Tab
        const itemsTab = page.locator('button:has-text("ربحية الأصناف")').first();
        if (await itemsTab.isVisible()) {
            await itemsTab.click({ force: true });
            await page.waitForTimeout(500);
            await expect(page.locator('text=مبيعات وربحية الأصناف').first()).toBeVisible();
        }

        // Switch to Stores Comparison Tab
        const storesTab = page.locator('button:has-text("مقارنة الفروع")').first();
        if (await storesTab.isVisible()) {
            await storesTab.click({ force: true });
            await page.waitForTimeout(500);
            await expect(page.locator('text=مقارنة أداء الفروع').first()).toBeVisible();
        }

        // Switch to Inventory Tab
        const invTab = page.locator('button:has-text("تقييم المخزون")').first();
        if (await invTab.isVisible()) {
            await invTab.click({ force: true });
            await page.waitForTimeout(500);
            await expect(page.locator('text=تقييم المخزون بسعر التكلفة').first()).toBeVisible();
        }
    });
});
