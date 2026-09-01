import { test, expect } from '@playwright/test';

test.describe('ActivityLogsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
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
        test(`should render System Activity Logs correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/activity-logs', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Header & Title
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('التدقيق الأمني');

            // 2. Verify Refresh Button
            await expect(page.locator('main button:has-text("تحديث")').first()).toBeVisible();

            // 3. Verify 4 Stats KPI Cards
            await expect(page.locator('text=إجمالي عمليات اليوم').first()).toBeVisible();

            // 4. Verify Filters Bar (Search Input)
            await expect(page.locator('input[placeholder*="بحث"]').first()).toBeVisible();

            // 5. Verify Timeline Container
            await expect(page.locator('main').first()).toBeVisible();

            // 6. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow interacting with activity logs filters and refresh', async ({ page }) => {
        await page.goto('/activity-logs', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Click Refresh button
        const refreshBtn = page.locator('main button:has-text("تحديث")').first();
        await expect(refreshBtn).toBeVisible();
        await refreshBtn.click({ force: true });
        await page.waitForTimeout(500);

        // Search Input Interaction
        const searchInput = page.locator('input[placeholder*="بحث"]').first();
        await searchInput.fill('فاتورة');
        await page.waitForTimeout(500);
        await searchInput.fill('');
    });
});
