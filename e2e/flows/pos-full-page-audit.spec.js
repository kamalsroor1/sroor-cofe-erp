import { test, expect } from '@playwright/test';

test.describe('POSView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
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
        test(`should render POS correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/pos', { waitUntil: 'networkidle' });
            await page.waitForSelector('h1', { timeout: 15000 });

            // Dismiss update modal if present
            const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
            if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
                await closeUpdateModalBtn.click().catch(() => {});
                await page.waitForTimeout(300);
            }

            // 1. Header & Title Verification
            await expect(page.locator('h1')).toContainText('نقطة البيع');

            // 2. Search & Barcode Input
            const searchInput = page.locator('input[placeholder*="ابحث"], input[placeholder*="الباركود"]').first();
            await expect(searchInput).toBeVisible();

            // 3. Customer Selection Button & Touch Target (>= 38px)
            const customerBtn = page.locator('button:has-text("نقدي عام"), button:has-text("عميل")').first();
            await expect(customerBtn).toBeVisible();
            const custBox = await customerBtn.boundingBox();
            if (custBox) {
                expect(custBox.height).toBeGreaterThanOrEqual(38);
            }

            // 4. Cart Table / Empty Cart State
            const hasCartItems = await page.locator('table tbody tr').isVisible().catch(() => false);
            const hasEmptyCart = await page.locator('h3:has-text("الفاتورة فارغة"), p:has-text("الفاتورة فارغة")').isVisible().catch(() => false);
            expect(hasCartItems || hasEmptyCart).toBeTruthy();

            // 5. Checkout Execution Button & Touch Target (>= 44px)
            const checkoutBtn = page.locator('button:has-text("حفظ واعتماد الفاتورة")').first();
            await expect(checkoutBtn).toBeVisible();
            const btnBox = await checkoutBtn.boundingBox();
            if (btnBox) {
                expect(btnBox.height).toBeGreaterThanOrEqual(44);
            }

            // 6. Zero Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should open Customer Picker Modal and close properly', async ({ page }) => {
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.waitForSelector('h1', { timeout: 15000 });

        // Dismiss update modal if present
        const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
        if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await closeUpdateModalBtn.click().catch(() => {});
            await page.waitForTimeout(300);
        }

        // Open customer picker modal
        const customerBtn = page.locator('button:has-text("نقدي عام"), button:has-text("عميل")').first();
        await customerBtn.click();
        await page.waitForTimeout(300);

        // Modal should be visible
        const modal = page.locator('.fixed.inset-0').first();
        await expect(modal).toBeVisible();

        // Close modal
        const closeModalBtn = page.locator('.fixed.inset-0 button:has-text("✕"), .fixed.inset-0 button:has-text("إلغاء"), .fixed.inset-0 button:has-text("إغلاق")').first();
        if (await closeModalBtn.isVisible()) {
            await closeModalBtn.click();
            await page.waitForTimeout(300);
        }
    });

    test('should interact with quick pinned items and update cart', async ({ page }) => {
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.waitForSelector('h1', { timeout: 15000 });

        // Dismiss update modal if present
        const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
        if (await closeUpdateModalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await closeUpdateModalBtn.click().catch(() => {});
            await page.waitForTimeout(300);
        }

        // Check if quick pinned item button exists and click it
        const quickItemBtn = page.locator('.pt-2 button:has-text("+")').first();
        if (await quickItemBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            await quickItemBtn.click();
            await page.waitForTimeout(300);

            // Item should be added to cart table
            const cartItemRow = page.locator('table tbody tr').first();
            await expect(cartItemRow).toBeVisible();
        }
    });
});
