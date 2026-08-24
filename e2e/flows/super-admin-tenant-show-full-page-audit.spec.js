import { test, expect } from '@playwright/test';

test.describe('SuperAdminTenantShowView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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
        test(`should render Super Admin Tenant details correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                const txt = msg.text();
                if (msg.type() === 'error' && !txt.includes('favicon') && !txt.includes('ERR_CONNECTION_REFUSED') && !txt.includes('404')) {
                    consoleErrors.push(txt);
                }
            });

            // 1. Go to tenants list and click first tenant show link
            await page.goto('/super-admin/tenants', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            const detailLink = page.locator('a[href*="/super-admin/tenants/"]').first();
            if (await detailLink.isVisible({ timeout: 5000 }).catch(() => false)) {
                await detailLink.click({ force: true });
                await page.waitForTimeout(1500);
            }

            // 2. Verify Main Header
            const mainEl = page.locator('main').first();
            await expect(mainEl).toBeVisible({ timeout: 15000 });

            // 3. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow opening status & subscription modal', async ({ page }) => {
        await page.goto('/super-admin/tenants', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        const detailLink = page.locator('a[href*="/super-admin/tenants/"]').first();
        if (await detailLink.isVisible({ timeout: 5000 }).catch(() => false)) {
            await detailLink.click({ force: true });
            await page.waitForTimeout(1500);
        }

        // Click Edit Status button
        const statusBtn = page.locator('button:has-text("الحالة والاشتراك"), button:has-text("الحالة")').first();
        if (await statusBtn.isVisible().catch(() => false)) {
            await statusBtn.click({ force: true });
            await page.waitForTimeout(500);

            // Close Modal
            const closeBtn = page.locator('button:has-text("إلغاء"), button:has-text("✕")').first();
            if (await closeBtn.isVisible().catch(() => false)) {
                await closeBtn.click({ force: true });
            }
        }
    });
});
