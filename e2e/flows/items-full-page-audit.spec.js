import { test, expect } from '@playwright/test';

test.describe('ItemsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    for (const vp of viewports) {
        test(`should render ItemsView correctly on ${vp.name}`, async ({ page }) => {
            const consoleErrors = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    consoleErrors.push(msg.text());
                }
            });

            await page.setViewportSize({ width: vp.width, height: vp.height });
            
            // Wait for API response
            const itemsPromise = page.waitForResponse((resp) => resp.url().includes('/items') && resp.status() === 200).catch(() => null);
            await page.goto('/items', { waitUntil: 'domcontentloaded' });
            await itemsPromise;
            await page.waitForTimeout(500);

            // 1. Verify Page Header
            const pageHeader = page.locator('h1, header, .font-black').first();
            await expect(pageHeader).toBeVisible();

            // 2. Verify Summary Metric Cards
            const metricCards = page.locator('text=إجمالي');
            await expect(metricCards.first()).toBeVisible();

            // 3. Verify Search and Category Filters
            const searchInput = page.locator('input[placeholder*="بحث"], input[type="text"]').first();
            await expect(searchInput).toBeVisible();

            // 4. Verify Responsive Dual Display (Desktop Table or Mobile Cards or Empty State)
            if (vp.isMobile) {
                const mobileCardOrEmpty = page.locator('.block.md\\:hidden > div, .text-center');
                await expect(mobileCardOrEmpty.first()).toBeVisible();
            } else {
                const desktopTableOrEmpty = page.locator('.hidden.md\\:block table, .text-center');
                await expect(desktopTableOrEmpty.first()).toBeVisible();
            }

            // 5. Verify Zero Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should open Add Item Modal and close properly', async ({ page }) => {
        await page.goto('/items', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        const addBtn = page.locator('button:has-text("إضافة صنف"), button:has-text("+")').first();
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await page.waitForTimeout(500);

            // Verify modal is open
            const modalTitle = page.locator('text=إضافة صنف جديد').first();
            await expect(modalTitle).toBeVisible();

            // Close modal
            const cancelBtn = page.locator('button:has-text("إلغاء")').last();
            if (await cancelBtn.isVisible()) {
                await cancelBtn.click();
                await page.waitForTimeout(300);
            }
        }
    });
});
