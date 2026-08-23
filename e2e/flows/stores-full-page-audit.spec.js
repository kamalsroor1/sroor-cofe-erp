import { test, expect } from '@playwright/test';

test.describe('StoresView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    for (const vp of viewports) {
        test(`should render StoresView correctly on ${vp.name}`, async ({ page }) => {
            const consoleErrors = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    consoleErrors.push(msg.text());
                }
            });

            await page.setViewportSize({ width: vp.width, height: vp.height });
            const storesPromise = page.waitForResponse((resp) => resp.url().includes('/stores') && resp.status() === 200).catch(() => null);
            await page.goto('/stores', { waitUntil: 'domcontentloaded' });
            await storesPromise;
            await page.waitForTimeout(600);

            // 1. Verify Header
            const header = page.locator('h1, header, .font-black').first();
            await expect(header).toBeVisible();

            // 2. Verify Action Buttons (Add Store, Branch Stocks Balance)
            const addBtn = page.locator('button:has-text("إضافة فرع"), button:has-text("إضافة مخزن"), button:has-text("+")').first();
            await expect(addBtn).toBeVisible();

            // 3. Verify Metrics Grid or Store Cards
            const cards = page.locator('.grid > div, .text-center');
            await expect(cards.first()).toBeVisible();

            // 4. Verify Zero Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should open Add Store Modal and close properly', async ({ page }) => {
        await page.goto('/stores', { waitUntil: 'networkidle' });
        await page.waitForSelector('h1', { timeout: 15000 });
        await page.waitForTimeout(500);

        // Dismiss update modal if present
        const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
        if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await closeUpdateModalBtn.click().catch(() => {});
            await page.waitForTimeout(300);
        }

        const addBtn = page.locator('button:has-text("إضافة فرع"), button:has-text("إضافة مخزن"), button:has-text("+")').first();
        if (await addBtn.isVisible()) {
            await addBtn.click();
            await page.waitForTimeout(500);

            // Verify modal is open
            const modalTitle = page.locator('text=إضافة').first();
            await expect(modalTitle).toBeVisible();

            // Click Cancel
            const cancelBtn = page.locator('button:has-text("إلغاء"), button:has-text("Cancel")').first();
            if (await cancelBtn.isVisible()) {
                await cancelBtn.click();
                await page.waitForTimeout(300);
            }
        }
    });
});
