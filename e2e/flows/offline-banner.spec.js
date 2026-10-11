// OFFL-2: POS offline guard. While the browser is offline the SPA shows the offline banner,
// disables both checkout buttons, explains why, blocks the F9 shortcut with an alert, and
// never sends a checkout request. Back online, the banner clears and checkout re-enables.
//
// Never completes a checkout. Selectors: data-testid and roles only (no translated text).
// Local server only (playwright.config.js webServer). Never point at production.
import { test, expect } from '@playwright/test';
import { createStockedItem, createTenantApi, recordApiPosts, removeStockedItem } from '../utils/offline-helper.js';

test.describe('POS offline banner and checkout guard (OFFL-2)', () => {
    /** @type {import('@playwright/test').APIRequestContext | null} */
    let api = null;
    let storeId = 0;
    /** @type {{ id: number, name: string, code: string, quantity: string } | null} */
    let item = null;

    test.beforeAll(async ({ playwright }, testInfo) => {
        ({ api, storeId } = await createTenantApi(playwright, testInfo.project.use.baseURL));
        item = await createStockedItem(api, storeId, { prefix: 'E2E-OFFL', quantity: '5.000' });
    });

    test.afterAll(async () => {
        if (!api) return;
        const outcome = await removeStockedItem(api, storeId, item);
        console.log(`[OFFL-2] fixture item ${item?.code ?? '-'}: ${outcome}`);
        await api.dispose();
    });

    test('offline blocks checkout without any POST, online restores it', async ({ page, context }) => {
        const pageErrors = [];
        page.on('pageerror', (err) => pageErrors.push(err.message));
        const checkoutPosts = recordApiPosts(page, ['/api/v1/pos/', '/api/v1/invoices']);

        await page.goto('/pos');
        // Skeleton -> content: the catalog renders real tiles.
        await expect(page.getByTestId('pos-product-tile').first()).toBeVisible({ timeout: 20000 });

        const submit = page.getByTestId('pos-checkout-submit');
        const savePrint = page.getByTestId('pos-checkout-save-print');
        const banner = page.getByTestId('offline-banner');
        const offlineHint = page.getByTestId('pos-checkout-offline-hint');

        await expect(banner).toBeHidden();
        await expect(submit).toBeDisabled(); // empty cart

        // Add the fixture item through the POS search (F2 focuses it; the remote search finds new items).
        await page.keyboard.press('F2');
        await page.keyboard.type(item.code);
        const itemButton = page.getByRole('button', { name: item.name }).first();
        await expect(itemButton).toBeVisible({ timeout: 10000 });
        await itemButton.click();

        await expect(submit).toBeEnabled();
        await expect(savePrint).toBeEnabled();
        await expect(offlineHint).toBeHidden();

        // --- Go offline ---
        await context.setOffline(true);

        await expect(banner).toBeVisible({ timeout: 5000 });
        await expect(submit).toBeDisabled();
        await expect(savePrint).toBeDisabled();
        await expect(offlineHint).toBeVisible();

        // F9 is the keyboard checkout: it must be blocked with a SweetAlert, not sent.
        const swalDialog = page.locator('.swal2-container').getByRole('dialog');
        await expect(swalDialog).toHaveCount(0);
        await page.keyboard.press('F9');
        await expect(swalDialog).toBeVisible();
        await expect(swalDialog).toHaveAttribute('aria-modal', 'true');

        expect(checkoutPosts, 'no checkout request may leave the browser while offline').toEqual([]);

        await page.keyboard.press('Escape');
        await expect(swalDialog).toHaveCount(0);

        // --- Back online ---
        await context.setOffline(false);

        await expect(banner).toBeHidden({ timeout: 10000 });
        await expect(offlineHint).toBeHidden();
        await expect(submit).toBeEnabled();
        await expect(savePrint).toBeEnabled();

        // The cart survived the outage and still nothing was posted (no auto-replay, no checkout).
        expect(checkoutPosts).toEqual([]);
        expect(pageErrors, 'uncaught page errors').toEqual([]);
    });
});
