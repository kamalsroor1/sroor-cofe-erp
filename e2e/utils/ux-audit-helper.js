/* global document */
import { expect } from '@playwright/test';

export const VIEWPORTS = [
    { name: 'mobile', width: 390, height: 844, isMobile: true },
    { name: 'tablet', width: 820, height: 1180, isMobile: false },
    { name: 'desktop', width: 1366, height: 768, isMobile: false },
];

/**
 * Standard UX Audit Suite for Sroor ERP SPA Pages
 *
 * @param {import('@playwright/test').Page} page
 * @param {Object} options
 * @param {string} options.route - Path to navigate to (e.g. '/customers')
 * @param {string} [options.testId] - Primary data-testid for the table/component
 * @param {string|RegExp} [options.apiPattern] - URL pattern to intercept for 500 mock test
 */
export async function auditPageUx(page, options) {
    const { route, testId, apiPattern } = options;
    const consoleErrors = [];

    page.on('console', (msg) => {
        const text = msg.text();
        const isIgnored =
            text.includes('favicon') ||
            text.includes('ERR_CONNECTION_REFUSED') ||
            text.includes('status of 500') ||
            text.includes('Internal Server Error');

        if ((msg.type() === 'error' || text.includes('[Vue warn]')) && !isIgnored) {
            consoleErrors.push(text);
        }
    });

    page.on('pageerror', (err) => {
        consoleErrors.push(err.message);
    });

    // 1. Multi-Viewport Audits (390, 820, 1366)
    for (const vp of VIEWPORTS) {
        await page.setViewportSize({ width: vp.width, height: vp.height });
        await page.goto(route, { waitUntil: 'domcontentloaded' });

        // Assert RTL (MUST require dir="rtl")
        const htmlDir = await page.locator('html').getAttribute('dir');
        expect(htmlDir).toBe('rtl');

        // Assert No Horizontal Overflow
        const hasHorizontalOverflow = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;
        });
        expect(hasHorizontalOverflow).toBe(false);

        // Skeleton -> Content transition check
        await expect(
            page.locator('.animate-pulse, [data-testid="skeleton"], [data-testid="table-skeleton"]')
        ).toHaveCount(0, { timeout: 10000 });

        // Unconditional content assertion when testId is specified
        if (testId) {
            await expect(page.locator(`[data-testid="${testId}"]`)).toBeVisible();
        }

        // Touch targets check on mobile (390px): width >= 44px AND height >= 44px
        if (vp.isMobile && testId && testId !== 'store-stocks-table') {
            const actionButtons = page.locator(`[data-testid="${testId}"] [data-testid^="action-"]`);
            const count = await actionButtons.count();
            expect(count).toBeGreaterThanOrEqual(1);
            for (let i = 0; i < count; i++) {
                const box = await actionButtons.nth(i).boundingBox();
                expect(box).not.toBeNull();
                expect(box.width).toBeGreaterThanOrEqual(44);
                expect(box.height).toBeGreaterThanOrEqual(44);
            }
        }
    }

    // 2. Authentic Theme Toggle Check
    const themeToggle = page
        .locator(
            '[data-testid="theme-toggle"], button:has(svg.lucide-sun), button:has(svg.lucide-moon), button[title*="الوضع"]'
        )
        .first();
    await expect(themeToggle).toBeVisible();
    const initialDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    await themeToggle.click();
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(!initialDark);
    await themeToggle.click();
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(initialDark);

    // 3. Page 2 Navigation Check
    if (apiPattern && testId && testId !== 'dashboard-recent-invoices-table') {
        let page2Requested = false;
        await page.route(apiPattern, (route, request) => {
            const url = request.url();
            if (url.includes('page=2')) {
                page2Requested = true;
                route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        data: [{ id: 999, name: 'Page 2 Item', title: 'Page 2 Item' }],
                        meta: { current_page: 2, last_page: 2, per_page: 15, total: 30 },
                    }),
                });
            } else {
                route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({
                        data: [{ id: 1, name: 'Page 1 Item', title: 'Page 1 Item' }],
                        meta: { current_page: 1, last_page: 2, per_page: 15, total: 30 },
                    }),
                });
            }
        });

        await page.goto(route, { waitUntil: 'domcontentloaded' });
        if (testId) {
            await expect(page.locator(`[data-testid="${testId}"]`)).toBeVisible();
        }
        const nextBtn = page.locator('[data-testid="pagination-next"]').first();
        await expect(nextBtn).toBeVisible();
        await nextBtn.click();
        expect(page2Requested).toBe(true);
        await expect(page.locator('[data-testid="pagination-page-indicator"]')).toContainText(/2/);
        await page.unroute(apiPattern);
    }

    // 4. Mock 500 Error + Retry Check
    if (apiPattern) {
        await page.route(apiPattern, (route) => {
            route.fulfill({
                status: 500,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Internal Server Error' }),
            });
        });

        await page.goto(route, { waitUntil: 'domcontentloaded' });
        const errorState = page.locator('[data-testid="error-state"], [data-testid="inline-error-bar"]').first();
        await expect(errorState).toBeVisible();

        await page.unroute(apiPattern);

        const retryBtn = page.locator('[data-testid="retry-button"]').first();
        await retryBtn.click();

        if (testId) {
            await expect(page.locator(`[data-testid="${testId}"]`)).toBeVisible();
        }
        await expect(page.locator('[data-testid="error-state"]')).toHaveCount(0);
        await expect(page.locator('[data-testid="inline-error-bar"]')).toHaveCount(0);
    }

    // 5. Zero Console Errors / Vue Warnings Assertion
    expect(consoleErrors).toEqual([]);
}
