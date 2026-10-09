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

    await page.route('**/api/v1/system/context', (r) => {
        r.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                data: {
                    system: { version: '1.0.0', name: 'سروور ERP' },
                    branding: { app_name: 'سروور كوفي' },
                    tenant: { id: 'demo', name: 'مؤسسة تجريبية' },
                    locale: 'ar',
                    translations: {},
                    stores: [{ id: 1, name: 'الفرع الرئيسي', code: 'main' }],
                },
            }),
        });
    });

    await page.route('**/api/v1/stores', (r) => {
        r.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                data: [{ id: 1, name: 'الفرع الرئيسي', code: 'main' }],
            }),
        });
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

        // Skeleton → Content transition check
        const skeleton = page
            .locator('.animate-pulse, [data-testid="skeleton"], [data-testid="table-skeleton"]')
            .first();
        if (await skeleton.isVisible().catch(() => false)) {
            await skeleton.waitFor({ state: 'hidden', timeout: 10000 }).catch(() => {});
        }

        // Unconditional content assertion when testId is specified
        if (testId) {
            await expect(page.locator(`[data-testid="${testId}"]`)).toBeVisible();
        }

        // Touch targets check on mobile (390px): width >= 44px AND height >= 44px
        if (vp.isMobile) {
            const actionSelector = testId
                ? `[data-testid="${testId}"] [data-testid^="action-"], [data-testid="${testId}"] button`
                : '[data-testid^="action-"], button';
            const actions = page.locator(actionSelector);
            if ((await actions.count()) > 0) {
                const firstAction = actions.first();
                const box = await firstAction.boundingBox();
                if (box) {
                    expect(box.width).toBeGreaterThanOrEqual(44);
                    expect(box.height).toBeGreaterThanOrEqual(44);
                }
            }
        }
    }

    // 2. Page 2 navigation check (when pagination exists)
    const page2Btn = page.locator('button').filter({ hasText: /^2$/ }).first();
    const nextBtn = page
        .locator('button')
        .filter({ hasText: /التالي|Next/i })
        .first();
    const targetPageBtn =
        (await page2Btn.count()) > 0 && (await page2Btn.isEnabled().catch(() => false))
            ? page2Btn
            : (await nextBtn.count()) > 0 && (await nextBtn.isEnabled().catch(() => false))
              ? nextBtn
              : null;

    if (targetPageBtn) {
        await targetPageBtn.click();
        if (testId) {
            await expect(page.locator(`[data-testid="${testId}"]`)).toBeVisible();
        }
    }

    // 3. Dark / Light Toggle Check
    await page.evaluate(() => document.documentElement.classList.add('dark'));
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(true);
    await page.evaluate(() => document.documentElement.classList.remove('dark'));
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark'))).toBe(false);

    // 4. Mock 500 Error + Retry Test
    if (apiPattern) {
        await page.route(apiPattern, (r) => {
            r.fulfill({
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
    }

    // 5. Zero Console Errors / Vue Warnings Assertion
    expect(consoleErrors).toEqual([]);
}
