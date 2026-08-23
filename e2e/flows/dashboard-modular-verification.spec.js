import { test, expect } from '@playwright/test';

test.describe('Dashboard (Modular Component-Driven Architecture) E2E Verification', () => {
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

    test('should mount Dashboard, render all 6 subcomponents and zero JS errors', async ({ page }) => {
        await page.goto('/dashboard', { waitUntil: 'networkidle' });

        // Wait for dashboard to mount
        await page.waitForSelector('h1', { timeout: 15000 });

        // 1. DashboardWelcomeBanner
        const bannerTitle = page.locator('h1');
        await expect(bannerTitle).toBeVisible();
        const posLink = page.locator('a[href*="/pos"]').first();
        await expect(posLink).toBeVisible();
        const purchasesLink = page.locator('a[href*="/purchases"]').first();
        await expect(purchasesLink).toBeVisible();
        const refreshBtn = page.locator('button[title*="تحديث"], button:has(svg.lucide-refresh-cw)').first();
        await expect(refreshBtn).toBeVisible();

        // 2. DashboardKpiGrid (4 Metric Cards)
        const metricCards = page.locator('.grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4 > div');
        await expect(metricCards).toHaveCount(4);

        // 3. DashboardAnalyticsRow (7-Day Bar Chart + Payment Distribution)
        const analyticsCards = page.locator('.grid.grid-cols-1.lg\\:grid-cols-12');
        await expect(analyticsCards.first()).toBeVisible();

        // 4. DashboardPeakHours (24-Hour Timeline)
        const peakHoursSection = page.locator('text=ساعات الذروة');
        await expect(peakHoursSection.first()).toBeVisible();

        // 5. DashboardRecentInvoices Table
        const recentInvoicesHeader = page.locator('text=آخر فواتير المبيعات');
        await expect(recentInvoicesHeader.first()).toBeVisible();
        const fullInvoicesLink = page.locator('a[href*="/invoices"]').first();
        await expect(fullInvoicesLink).toBeVisible();

        // 6. DashboardLowStock Alerts
        const lowStockHeader = page.locator('text=تنبيهات النواقص');
        await expect(lowStockHeader.first()).toBeVisible();
        const smartReorderLink = page.locator('a[href*="/smart-reorder"]').first();
        await expect(smartReorderLink).toBeVisible();

        // Check console errors
        const criticalErrors = consoleErrors.filter(
            (e) => !e.includes('favicon') && !e.includes('Failed to load resource') && !e.includes('net::ERR_')
        );
        expect(criticalErrors).toHaveLength(0);
    });

    test('should trigger refresh button without UI disruption', async ({ page }) => {
        await page.goto('/dashboard', { waitUntil: 'networkidle' });
        await page.waitForSelector('h1', { timeout: 15000 });

        // Dismiss update modal if it appeared
        const closeUpdateModalBtn = page.locator('button:has-text("لاحقاً"), button:has-text("تخطي"), button:has-text("إغلاق"), button:has-text("تم والإغلاق")').first();
        if (await closeUpdateModalBtn.isVisible({ timeout: 1500 }).catch(() => false)) {
            await closeUpdateModalBtn.click().catch(() => {});
            await page.waitForTimeout(300);
        }

        const refreshBtn = page.locator('button[title*="تحديث"], button:has(svg.lucide-refresh-cw)').first();
        if (await refreshBtn.isVisible()) {
            await refreshBtn.click({ force: true });
            await page.waitForTimeout(1000);
            await expect(page.locator('h1')).toBeVisible();
        }
    });
});
