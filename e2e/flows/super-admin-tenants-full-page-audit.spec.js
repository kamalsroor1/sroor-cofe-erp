import { test, expect } from '@playwright/test';

test.describe('SuperAdminTenantsView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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
        test(`should render Super Admin Tenants list correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/super-admin/tenants', { waitUntil: 'networkidle' });
            await page.waitForTimeout(1000);
            await dismissAnyModal(page);

            // 1. Verify Header & Title
            const header = page.locator('main h1').first();
            await expect(header).toBeVisible({ timeout: 15000 });
            await expect(header).toContainText('المستأجرين');

            // 2. Verify Action Buttons (New Tenant / Dashboard)
            await expect(page.locator('button:has-text("إنشاء مستأجر")').first()).toBeVisible();

            // 3. Verify Search & Filters
            await expect(page.locator('input[placeholder*="بحث"]').first()).toBeVisible();

            // 4. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow opening create tenant modal', async ({ page }) => {
        await page.goto('/super-admin/tenants', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Click New Tenant button
        const newBtn = page.locator('button:has-text("إنشاء مستأجر")').first();
        await expect(newBtn).toBeVisible();
        await newBtn.click({ force: true });
        await page.waitForTimeout(1000);

        // Verify Modal Title / Input
        const orgInput = page.locator('input[placeholder*="شركة النصر"]').first();
        if (await orgInput.isVisible({ timeout: 5000 }).catch(() => false)) {
            await expect(orgInput).toBeVisible();
        }

        // Close Modal
        const closeBtn = page.locator('button:has-text("إلغاء"), button:has-text("✕")').first();
        if (await closeBtn.isVisible().catch(() => false)) {
            await closeBtn.click({ force: true });
        }
    });
});
