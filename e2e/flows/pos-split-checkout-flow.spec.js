// SP-6: POS split checkout journey (split-payment contract, CTO option A).
//  - split cash overpay: payment_type stays cash, exact lines are sent, the
//    success modal shows the change returned by the server;
//  - visa overpay: the split modal blocks confirmation;
//  - fractional kilo line: the payload carries 3-decimal strings.
//
// POS data and POST /invoices are mocked with page.route, so nothing is written
// to the local DB. Local server only (playwright.config.js). Selectors use
// data-testid, never translated text.
import { test, expect } from '@playwright/test';

const ITEM = {
    id: 990001,
    name: 'E2E Kilo Coffee',
    code: 'E2E-KILO',
    unit: 'kg',
    selling_price: '550.500',
    price_retail: '550.500',
    price_wholesale: '550.500',
    min_selling_price: '550.500',
    current_stock: '50.000',
    is_pos_pinned: true,
    pos_sales_count: 1,
};

async function mockPosData(page, onInvoice) {
    await page.route('**/api/v1/items*', (route) =>
        route.fulfill({ json: { success: true, data: [ITEM], meta: { total: 1 } } })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({ json: { success: true, data: [], total_items_count: 1 } })
    );
    await page.route('**/api/v1/invoices', async (route) => {
        if (route.request().method() !== 'POST') return route.fallback();
        const payload = route.request().postDataJSON();
        return route.fulfill(await onInvoice(payload));
    });
}

async function openPosWithItem(page) {
    await page.goto('/pos', { waitUntil: 'networkidle' });
    await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible', timeout: 15000 });
    await page.getByText(ITEM.name, { exact: true }).first().click();
}

test.describe('POS split checkout', () => {
    let consoleErrors = [];

    test.beforeEach(async ({ page }) => {
        consoleErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error' || msg.type() === 'warning') consoleErrors.push(msg.text());
        });
        page.on('pageerror', (err) => consoleErrors.push(err.message));
    });

    test('split cash overpay keeps cash and shows the server change', async ({ page }) => {
        let sent = null;
        await mockPosData(page, (payload) => {
            sent = payload;
            return {
                status: 201,
                json: {
                    success: true,
                    data: { id: 1, invoice_number: 'E2E-1', net_amount: '550.500', change_amount: '49.500' },
                },
            };
        });
        await openPosWithItem(page);

        await page.getByTestId('pos-payment-type-cash').click();
        await page.getByTestId('pos-split-payment-btn').click();
        await page.getByTestId('pos-split-amount').first().fill('600');
        await expect(page.getByTestId('pos-split-confirm')).toBeEnabled();
        await page.getByTestId('pos-split-confirm').click();

        await page.getByTestId('pos-checkout-submit').click();
        await expect(page.getByTestId('pos-success-change')).toBeVisible();
        await expect(page.getByTestId('pos-success-change-amount')).toContainText('49.5');

        expect(sent.payment_type).toBe('cash');
        expect(sent.paid_amount).toBe('550.500');
        expect(sent.payments).toEqual([{ method: 'cash', amount: '600.000' }]);
        expect(consoleErrors.filter((e) => !e.includes('favicon') && !e.includes('net::ERR_'))).toHaveLength(0);
    });

    test('visa overpay is blocked by the split modal', async ({ page }) => {
        await mockPosData(page, () => ({ status: 500, json: { success: false } }));
        await openPosWithItem(page);

        await page.getByTestId('pos-payment-type-cash').click();
        await page.getByTestId('pos-split-payment-btn').click();
        await page.getByTestId('pos-split-method').first().selectOption('visa');
        await page.getByTestId('pos-split-amount').first().fill('600');

        await expect(page.getByTestId('pos-split-non-cash-error')).toBeVisible();
        await expect(page.getByTestId('pos-split-confirm')).toBeDisabled();
    });

    test('fractional kilo line is sent with 3-decimal strings', async ({ page }) => {
        let sent = null;
        await mockPosData(page, (payload) => {
            sent = payload;
            return {
                status: 201,
                json: {
                    success: true,
                    data: { id: 2, invoice_number: 'E2E-2', net_amount: '137.625', change_amount: '0.000' },
                },
            };
        });
        await openPosWithItem(page);

        await page.locator('[data-testid="pos-cart-qty"]:visible').first().fill('0.25');
        await page.getByTestId('pos-payment-type-cash').click();
        await page.getByTestId('pos-checkout-submit').click();

        await expect.poll(() => sent).not.toBeNull();
        expect(sent.items[0].quantity).toBe('0.250');
        expect(sent.items[0].unit_price).toBe('550.500');
        expect(sent.paid_amount).toBe('137.625');
        await expect(page.getByTestId('pos-success-change')).toHaveCount(0);
    });
});
