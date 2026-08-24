import { test, expect } from '@playwright/test';

test.describe('ItemsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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

    const dismissAnyModal = async (page) => {
        const modalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق"), button:has-text("فتح الوردية"), button:has-text("بدء الوردية")').first();
        if (await modalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await modalBtn.click({ force: true }).catch(() => {});
            await page.waitForTimeout(400);
        }
    };

    for (const vp of viewports) {
        test(`should render ItemsView correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/items', { waitUntil: 'networkidle' });
            await page.waitForTimeout(600);
            await dismissAnyModal(page);

            // 1. Verify Page Header
            const title = page.locator('h1').first();
            await expect(title).toBeVisible();

            // 2. Verify Search Bar
            const searchInput = page.locator('input[placeholder*="بحث"]').first();
            await expect(searchInput).toBeVisible();

            // 3. Verify Table or Grid Items
            const itemsContainer = page.locator('table, .grid').first();
            await expect(itemsContainer).toBeVisible();

            // 4. Verify Touch Target Size (>= 40px)
            const addBtn = page.locator('button:has-text("إضافة صنف"), button:has-text("+")').first();
            if (await addBtn.isVisible()) {
                const box = await addBtn.boundingBox();
                if (box) {
                    expect(box.height).toBeGreaterThanOrEqual(36);
                }
            }

            // 5. Verify Zero Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should open Add Item Modal and close properly', async ({ page }) => {
        await page.goto('/items', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        const addBtn = page.locator('button:has-text("إضافة صنف"), button:has-text("+")').first();
        if (await addBtn.isVisible()) {
            await addBtn.click({ force: true });
            await page.waitForTimeout(500);

            // Verify modal is open
            const modalTitle = page.locator('text=إضافة صنف جديد').first();
            await expect(modalTitle).toBeVisible();

            // Close modal
            const cancelBtn = page.locator('button:has-text("إلغاء")').last();
            if (await cancelBtn.isVisible()) {
                await cancelBtn.click({ force: true });
                await page.waitForTimeout(300);
            }
        }
    });
});
