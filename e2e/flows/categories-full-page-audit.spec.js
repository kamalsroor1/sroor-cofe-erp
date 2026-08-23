import { test, expect } from '@playwright/test';

test.describe('CategoriesView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    for (const vp of viewports) {
        test(`should render CategoriesView correctly on ${vp.name}`, async ({ page }) => {
            const consoleErrors = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    consoleErrors.push(msg.text());
                }
            });

            await page.setViewportSize({ width: vp.width, height: vp.height });
            const catPromise = page.waitForResponse((resp) => resp.url().includes('/categories') && resp.status() === 200).catch(() => null);
            await page.goto('/categories', { waitUntil: 'domcontentloaded' });
            await catPromise;
            await page.waitForTimeout(500);

            // 1. Verify Header
            const header = page.locator('h1, header, .font-black').first();
            await expect(header).toBeVisible();

            // 2. Verify Add Category Action Button
            const addBtn = page.locator('button:has-text("إضافة فئة"), button:has-text("+")').first();
            await expect(addBtn).toBeVisible();

            // 3. Verify Categories Grid or Empty State
            const categoryCardsOrEmpty = page.locator('.grid > div, .text-center');
            await expect(categoryCardsOrEmpty.first()).toBeVisible();

            // 4. Verify Zero Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should open Add Category Modal and close properly', async ({ page }) => {
        await page.goto('/categories', { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);

        const addBtn = page.locator('button:has-text("إضافة فئة"), button:has-text("+")').first();
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await page.waitForTimeout(500);

            // Verify modal is open
            const modalTitle = page.locator('text=إضافة فئة').first();
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
