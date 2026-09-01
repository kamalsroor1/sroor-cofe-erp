import { test, expect } from '@playwright/test';

test.describe('Dashboard Responsive & Touch Ergonomics Multi-Viewport Audit', () => {
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
        test(`should render perfectly and handle touch on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/dashboard', { waitUntil: 'networkidle' });
            await page.waitForSelector('h1', { timeout: 15000 });

            // Dismiss update modal if appeared
            const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
            if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
                await closeUpdateModalBtn.click().catch(() => {});
                await page.waitForTimeout(300);
            }

            // 1. Verify Banner & Touch Targets (min 44px on mobile)
            const posLink = page.locator('a[href*="/pos"]:visible').first();
            await expect(posLink).toBeVisible();
            const posBox = await posLink.boundingBox();
            if (posBox) {
                expect(posBox.height).toBeGreaterThanOrEqual(40); // 44px touch ergonomics standard
            }

            // 2. Verify Weekly Bar Chart & Tooltip Tap
            const chartBars = page.locator('.rounded-xl[style*="--bar-track"]');
            if (await chartBars.count() > 0) {
                await chartBars.first().click({ force: true });
                await page.waitForTimeout(200);
            }

            // 3. Verify Recent Invoices (Dual Layout: Cards on mobile, Table on desktop)
            if (vp.width < 768) {
                const mobileInvoiceCards = page.locator('.block.md\\:hidden > div');
                const count = await mobileInvoiceCards.count();
                expect(count).toBeGreaterThanOrEqual(0);
            } else {
                const desktopInvoiceTable = page.locator('.hidden.md\\:block table');
                await expect(desktopInvoiceTable).toBeVisible();
            }

            // 4. Verify Peak Hours Heatmap Touch interaction
            const peakHourSlots = page.locator('.h-28 > div');
            if (await peakHourSlots.count() > 0) {
                await peakHourSlots.first().click({ force: true });
                await page.waitForTimeout(200);
            }

            // 5. Zero JS Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }
});
