import { test, expect } from '@playwright/test';

test.describe('StockTransfersView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    for (const vp of viewports) {
        test(`should render StockTransfersView correctly on ${vp.name}`, async ({ page }) => {
            const consoleErrors = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    consoleErrors.push(msg.text());
                }
            });

            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/stock-transfers', { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(1000);

            // 1. Verify Page Header
            const pageHeader = page.locator('h1, header, .font-black').first();
            await expect(pageHeader).toBeVisible();

            // 2. Verify Summary Metric Cards
            const metricCard = page.locator('.grid').first();
            await expect(metricCard).toBeVisible();

            // 3. Verify Search and Filter Bar
            const searchInput = page.locator('input[placeholder*="بحث"], input[type="text"]').first();
            await expect(searchInput).toBeVisible();

            // 4. Verify Responsive Dual Display (Desktop Table or Mobile Cards or Empty State)
            if (vp.isMobile) {
                const mobileCardOrEmpty = page.locator('.block.md\\:hidden, .text-center');
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

    test('should open Transfer Details Modal when preview is clicked', async ({ page }) => {
        await page.goto('/stock-transfers', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        // Click first preview button if exists
        const previewBtn = page.locator('button[title*="تفاصيل"], button:has(svg.lucide-eye)').first();
        if (await previewBtn.isVisible()) {
            await previewBtn.click();
            await page.waitForTimeout(500);

            // Verify modal is open
            const modalHeader = page.locator('text=إذن تحويل').first();
            await expect(modalHeader).toBeVisible();

            // Close modal
            const closeBtn = page.locator('button:has(svg.lucide-x), button:has-text("إغلاق")').first();
            if (await closeBtn.isVisible()) {
                await closeBtn.click();
                await page.waitForTimeout(300);
            }
        }
    });
});
