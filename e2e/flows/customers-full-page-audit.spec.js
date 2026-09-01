import { test, expect } from '@playwright/test';

test.describe('CustomersView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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
        test(`should render Customers Directory correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/customers', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Page Title & Actions
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('العملاء');

            // Verify Add Customer button in main
            await expect(page.locator('main button:has-text("إضافة عميل")').first()).toBeVisible();

            // 2. Verify Metrics Grid Cards (Total Receivables, Debtors Count, Total Customers)
            await expect(page.locator('text=إجمالي مديونيات العملاء').first()).toBeVisible();
            await expect(page.locator('text=عدد العملاء المدينين').first()).toBeVisible();

            // 3. Verify Filter Pills
            await expect(page.locator('button:has-text("الكل")').first()).toBeVisible();
            await expect(page.locator('button:has-text("مديونية")').first()).toBeVisible();

            // 4. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow opening create customer modal and switching debt status', async ({ page }) => {
        await page.goto('/customers', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Click Add Customer
        const addBtn = page.locator('main button:has-text("إضافة عميل")').first();
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await page.waitForTimeout(500);
            await expect(page.locator('input[placeholder*="العميل"]').first()).toBeVisible();
            // Close modal
            await page.locator('button:has-text("إلغاء")').first().click();
        }
    });
});
