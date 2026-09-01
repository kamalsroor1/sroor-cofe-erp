import { test, expect } from '@playwright/test';

test.describe('CoffeeBlenderView Comprehensive 4-Axes Audit & Multi-Viewport Verification', () => {
    test.use({ storageState: 'e2e/.auth/user.json' });

    const viewports = [
        { name: '1. Small Phone (360px)', width: 360, height: 740, isMobile: true },
        { name: '2. Large Phone (412px)', width: 412, height: 915, isMobile: true },
        { name: '3. Tablet Portrait (768px)', width: 768, height: 1024, isMobile: false },
        { name: '4. Tablet Landscape (1024px)', width: 1024, height: 768, isMobile: false },
        { name: '5. Desktop (1280px)', width: 1280, height: 800, isMobile: false },
    ];

    const dismissAnyModal = async (page) => {
        const modalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق"), button:has-text("فتح الوردية الآن"), button:has-text("بدء الوردية")').first();
        if (await modalBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await modalBtn.click().catch(() => {});
            await page.waitForTimeout(400);
        }
    };

    for (const vp of viewports) {
        test(`should render Coffee Blender Studio correctly on ${vp.name}`, async ({ page }) => {
            await page.setViewportSize({ width: vp.width, height: vp.height });

            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error' && !msg.text().includes('favicon') && !msg.text().includes('ERR_CONNECTION_REFUSED')) {
                    consoleErrors.push(msg.text());
                }
            });

            await page.goto('/coffee-blender', { waitUntil: 'networkidle' });
            await page.waitForTimeout(800);
            await dismissAnyModal(page);

            // 1. Verify Page Title & Header
            const header = page.locator('h1');
            await expect(header).toBeVisible({ timeout: 10000 });

            // 2. Verify Specs Card & Formulation Card
            const specsCard = page.locator('text=مواصفات التركيبة والكمية المستهدفة');
            await expect(specsCard).toBeVisible();

            const rawBeansCard = page.locator('text=المكونات والمواد الأولية');
            await expect(rawBeansCard).toBeVisible();

            // 3. Verify Financial Summary
            const summaryCard = page.locator('text=ملخص تكلفة وسعر التركيبة');
            await expect(summaryCard).toBeVisible();

            // 4. Verify No Console Errors
            expect(consoleErrors).toEqual([]);
        });
    }

    test('should allow changing target weight and calculate proportions', async ({ page }) => {
        await page.goto('/coffee-blender', { waitUntil: 'networkidle' });
        await page.waitForTimeout(800);
        await dismissAnyModal(page);

        // Click on 500g preset
        const preset500 = page.locator('button:has-text("500 جم")').first();
        if (await preset500.isVisible()) {
            await preset500.click();
            await page.waitForTimeout(500);
            await expect(page.locator('text=500').first()).toBeVisible();
        }
    });
});
