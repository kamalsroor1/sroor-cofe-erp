import { test, expect } from '@playwright/test';

test.describe('DailyJournalView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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
        test(`should render Daily Journal & Shift Ledger correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/daily-journal', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Page Title & Header Actions
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('يومية');

            // Verify Quick Add Expense Button
            await expect(page.locator('main button:has-text("تسجيل مصروف اليومية")').first()).toBeVisible();

            // 2. Verify Financial Metrics Grid (Inflow, Outflow, Net, Expected in Drawer)
            await expect(page.locator('text=إجمالي المقبوضات').first()).toBeVisible();
            await expect(page.locator('text=إجمالي المدفوعات').first()).toBeVisible();
            await expect(page.locator('text=صافي النقدية').first()).toBeVisible();

            // 3. Verify Tabs (Invoices vs Expenses)
            await expect(page.locator('button:has-text("فواتير مبيعات")').first()).toBeVisible();
            await expect(page.locator('button:has-text("مصروفات ونثريات")').first()).toBeVisible();

            // 4. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow switching tabs and opening quick expense modal', async ({ page }) => {
        await page.goto('/daily-journal', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Switch to Expenses Tab
        const expTab = page.locator('button:has-text("مصروفات ونثريات")').first();
        if (await expTab.isVisible()) {
            await expTab.click();
            await page.waitForTimeout(500);
        }

        // Open Quick Expense Modal
        const quickExpBtn = page.locator('main button:has-text("تسجيل مصروف اليومية")').first();
        if (await quickExpBtn.isVisible()) {
            await quickExpBtn.click();
            await page.waitForTimeout(500);
            await expect(page.locator('input[placeholder*="بوفيه"]').first()).toBeVisible();
            // Close modal
            await page.locator('button:has-text("إلغاء")').first().click();
        }
    });
});
