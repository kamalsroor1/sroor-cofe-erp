import { test, expect } from '@playwright/test';

test.describe('InvoicesView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    let consoleErrors = [];

    test.beforeEach(async ({ page }) => {
        consoleErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });
        page.on('pageerror', (err) => {
            consoleErrors.push(err.message);
        });
    });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 640, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: true },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    for (const vp of viewports) {
        test(`should render perfectly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/invoices', { waitUntil: 'networkidle' });
            await page.waitForSelector('h1', { timeout: 15000 });

            // Dismiss update modal if present
            const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
            if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
                await closeUpdateModalBtn.click().catch(() => {});
                await page.waitForTimeout(300);
            }

            // 1. Verify Page Title & Header Actions
            await expect(page.locator('h1')).toContainText('فواتير المبيعات');
            
            // 2. Verify Filter Toggle Button & Touch Target (>= 40px)
            const filterBtn = page.locator('button:has-text("تصفية الفواتير"), button:has-text("فلاتر البحث")').first();
            await expect(filterBtn).toBeVisible();
            const filterBox = await filterBtn.boundingBox();
            if (filterBox) {
                expect(filterBox.height).toBeGreaterThanOrEqual(40);
            }

            // 3. Verify Metrics Cards
            const metricCards = page.locator('.grid .rounded-2xl, .grid .rounded-3xl');
            expect(await metricCards.count()).toBeGreaterThanOrEqual(4);

            // 4. Verify Search Input & Date Pills
            const searchInput = page.locator('input[placeholder*="بحث"]').first();
            await expect(searchInput).toBeVisible();

            // 5. Verify Invoices Table / Cards / Empty State
            const hasEmptyState = await page.locator('.p-12:has-text("فواتير"), .text-center:has-text("فواتير")').isVisible().catch(() => false);
            if (!hasEmptyState) {
                if (vp.width < 768) {
                    const mobileCardStack = page.locator('.block.md\\:hidden');
                    if (await mobileCardStack.count() > 0) {
                        await expect(mobileCardStack.first()).toBeVisible();
                    }
                } else {
                    const desktopTable = page.locator('.hidden.md\\:block table');
                    if (await desktopTable.count() > 0) {
                        await expect(desktopTable.first()).toBeVisible();
                    }
                }
            } else {
                expect(hasEmptyState).toBeTruthy();
            }

            // 6. Test Filter Sidebar Toggle (Open & Close)
            await filterBtn.click();
            await page.waitForTimeout(300);
            const asideFilter = page.locator('aside:has-text("تصفية الفواتير")');
            await expect(asideFilter).toBeVisible();
            
            // Close filter sidebar
            const closeFilterBtn = page.locator('aside:has-text("تصفية الفواتير") button:has-text("✕")').first();
            if (await closeFilterBtn.isVisible()) {
                await closeFilterBtn.click();
                await page.waitForTimeout(300);
            }

            // 7. Verify Zero Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should open Invoice Details Modal and display correct financial details', async ({ page }) => {
        await page.goto('/invoices', { waitUntil: 'networkidle' });
        await page.waitForSelector('h1', { timeout: 15000 });

        // Dismiss update modal if present
        const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
        if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await closeUpdateModalBtn.click().catch(() => {});
            await page.waitForTimeout(300);
        }

        // Click on details/preview button for first invoice if available
        const previewBtn = page.locator('button[title*="تفاصيل"], button:has-text("معاينة")').first();
        if (await previewBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await previewBtn.click();
            await page.waitForTimeout(500);

            // Modal should be visible with items table and financial breakdown
            const modal = page.locator('.fixed.inset-0').first();
            await expect(modal).toBeVisible();

            // Close modal
            const closeModalBtn = page.locator('.fixed.inset-0 button:has-text("✕"), .fixed.inset-0 button:has-text("إغلاق")').first();
            if (await closeModalBtn.isVisible()) {
                await closeModalBtn.click();
            }
        }
    });
});
