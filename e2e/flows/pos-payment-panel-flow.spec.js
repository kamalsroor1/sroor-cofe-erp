// POS-08 & POS-02: Payment panel denominations, exact tender, change preview, short-cash block, and walk-in credit/partial lock.
import { test, expect } from '@playwright/test';

const ITEM = {
    id: 993001,
    name: 'AGENT2 House Roast',
    code: '6223000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '125.000',
    price_retail: '125.000',
    price_wholesale: '115.000',
    min_selling_price: '110.000',
    current_stock: '30.000',
    is_pos_pinned: true,
    pos_sales_count: 4,
};

async function setupPosRoutes(page, onInvoice = null) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
    });

    await page.route('**/api/v1/items*', (route) =>
        route.fulfill({ json: { success: true, data: [ITEM], meta: { total: 1 } } })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({ json: { success: true, data: [], total_items_count: 1 } })
    );
    await page.route('**/api/v1/customers*', (route) =>
        route.fulfill({ json: { success: true, data: [] } })
    );
    await page.route('**/api/v1/shifts/current*', (route) =>
        route.fulfill({ json: { success: true, active_shift: { id: 1, shift_number: 'SH-1' }, requires_shift: false } })
    );
    await page.route('**/api/v1/pos/bootstrap*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: { pos_settings: { effective_max_discount_percent: '10.000' } },
            },
        })
    );
    await page.route('**/api/v1/invoices', async (route) => {
        if (route.request().method() !== 'POST') return route.fallback();
        if (onInvoice) return route.fulfill(await onInvoice(route.request().postDataJSON()));
        return route.fulfill({
            status: 201,
            json: {
                success: true,
                data: { id: 101, invoice_number: 'INV-101', net_amount: '125.000', change_amount: '75.000' },
            },
        });
    });
}

test.describe('POS-08 & POS-02: Payment panel & walk-in restrictions', () => {
    test('denominations 50/100/200/500 and exact button update tender and change preview', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        await page.getByText(ITEM.name, { exact: true }).first().click();

        // Tap 200 denomination on untouched 125.000 net -> tender becomes 200, change becomes 75.00
        await page.getByTestId('pos-tender-200').click();
        await expect(page.getByTestId('pos-cash-received')).toHaveValue('200.000');
        await expect(page.getByTestId('pos-change-amount')).toContainText('75');

        // Tap 50 denomination -> adds 50 -> 250, change becomes 125
        await page.getByTestId('pos-tender-50').click();
        await expect(page.getByTestId('pos-cash-received')).toHaveValue('250.000');
        await expect(page.getByTestId('pos-change-amount')).toContainText('125');

        // Tap exact -> resets tender to exact net (125.000) and change to 0
        await page.getByTestId('pos-tender-exact').click();
        await expect(page.getByTestId('pos-cash-received')).toHaveValue('125.000');
        await expect(page.getByTestId('pos-change-amount')).toContainText('0');
    });

    test('short cash tender blocks checkout and alerts the cashier', async ({ page }) => {
        let invoiceSubmitted = false;
        await setupPosRoutes(page, () => {
            invoiceSubmitted = true;
            return { status: 201, json: { success: true, data: { id: 1 } } };
        });

        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByText(ITEM.name, { exact: true }).first().click();

        // Net is 125.000; enter 50 cash received
        await page.getByTestId('pos-tender-50').click();
        await page.getByTestId('pos-checkout-submit').click();

        await expect(page.locator('.swal2-popup')).toBeVisible();
        expect(invoiceSubmitted).toBe(false);
    });

    test('credit and partial payment types are disabled for walk-in customer (POS-02)', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        await expect(page.getByTestId('pos-walk-in-hint')).toBeVisible();
        await expect(page.getByTestId('pos-payment-type-credit')).toBeDisabled();
        await expect(page.getByTestId('pos-payment-type-partial')).toBeDisabled();
        await expect(page.getByTestId('pos-payment-type-cash')).toBeEnabled();
    });
});
