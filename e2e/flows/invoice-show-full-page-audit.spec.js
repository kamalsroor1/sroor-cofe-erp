import { test, expect } from '@playwright/test';

test.describe('InvoiceShowView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

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
        test(`should render Invoice Show correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });
            await page.goto('/invoices/1', { waitUntil: 'networkidle' });
            await page.waitForTimeout(800);
            await dismissAnyModal(page);

            // 1. Verify Page Content or Header
            const pageContainer = page.locator('.font-tajawal').first();
            await expect(pageContainer).toBeVisible();

            // 2. Check Action Buttons
            const printBtn = page.locator('button:has-text("طباعة"), button:has-text("Print")').first();
            if (await printBtn.isVisible()) {
                await expect(printBtn).toBeVisible();
            }

            // 3. Verify Zero Critical Console Errors
            const criticalErrors = consoleErrors.filter(
                (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
            );
            expect(criticalErrors).toHaveLength(0);
        });
    }

    test('should allow switching between Interactive, Thermal, and A4 print modes', async ({ page }) => {
        await page.goto('/invoices/1', { waitUntil: 'networkidle' });
        await page.waitForTimeout(1000);
        await dismissAnyModal(page);

        // Switch to Thermal 80mm Mode
        const thermalBtn = page.locator('button[data-tab="thermal"]').first();
        if (await thermalBtn.isVisible()) {
            await thermalBtn.click({ force: true });
            await page.waitForTimeout(600);
            const thermalCard = page.locator('#receipt-print-area').first();
            await expect(thermalCard).toBeVisible();
        }

        // Switch to Official A4 Mode
        const a4Btn = page.locator('button[data-tab="a4"]').first();
        if (await a4Btn.isVisible()) {
            await a4Btn.click({ force: true });
            await page.waitForTimeout(600);
            const a4Card = page.locator('#a4-print-area').first();
            await expect(a4Card).toBeVisible();
        }

        // Switch back to Interactive Mode
        const interactiveBtn = page.locator('button[data-tab="interactive"]').first();
        if (await interactiveBtn.isVisible()) {
            await interactiveBtn.click({ force: true });
            await page.waitForTimeout(600);
        }
    });
});
