// Mobile POS flow (< 768px):
//  - Cart bar appears at the bottom with count and total when items are added;
//  - Tapping cart bar opens the cart BottomSheet;
//  - Tapping checkout opens the payment BottomSheet;
//  - Completing payment successfully submits the invoice.
//
// POS data and POST /invoices are mocked with page.route.
// Selectors use data-testid, never translated text.
import { test, expect } from '@playwright/test';
import { isPhoneViewport } from '../utils/pos-viewport.js';

const ITEM = {
    id: 991001,
    name: 'E2E Mobile Coffee',
    code: 'E2E-MOB-01',
    unit: 'item',
    selling_price: '75.000',
    price_retail: '75.000',
    price_wholesale: '75.000',
    min_selling_price: '75.000',
    current_stock: '100.000',
    is_pos_pinned: true,
    pos_sales_count: 5,
};

async function mockPosData(page, onInvoice) {
    await page.route('**/api/v1/pos/items*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: [ITEM],
                meta: {
                    current_page: 1,
                    last_page: 1,
                    total: 1,
                    counts: { total: 1, categories: [] },
                },
            },
        })
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

test.describe('Mobile POS workflow', () => {
    test.beforeEach(async ({ page }) => {
        test.skip(!isPhoneViewport(page), 'Mobile POS flow runs only on compact viewports (< DESKTOP_MIN)');
    });

    test('adding item shows phone cart bar and bottom sheets', async ({ page }) => {
        let sent = null;
        await mockPosData(page, (payload) => {
            sent = payload;
            return {
                status: 201,
                json: {
                    success: true,
                    data: {
                        id: 101,
                        invoice_number: 'MOB-101',
                        net_amount: '75.000',
                        change_amount: '0.000',
                    },
                },
            };
        });

        await page.goto('/pos', { waitUntil: 'networkidle' });

        // Catalog shows the item
        const itemBtn = page.getByText(ITEM.name, { exact: true }).first();
        await expect(itemBtn).toBeVisible({ timeout: 15000 });

        // Cart bar is initially not visible when cart is empty
        await expect(page.getByTestId('pos-phone-cart-bar')).toHaveCount(0);

        // Click item to add to cart
        await itemBtn.click();

        // Phone cart bar appears
        await expect(page.getByTestId('pos-phone-cart-bar')).toBeVisible();
        await expect(page.getByTestId('pos-phone-cart-count')).toHaveText('1');

        // Open cart bottom sheet
        await page.getByTestId('pos-phone-cart-open').click();
        await expect(page.getByTestId('pos-phone-cart-sheet')).toBeVisible();

        // Proceed to payment from cart sheet
        await page.getByTestId('pos-phone-sheet-checkout').click();
        await expect(page.getByTestId('pos-phone-payment-sheet')).toBeVisible();

        // Select cash and submit checkout
        await page.getByTestId('pos-payment-type-cash').click();
        await page.getByTestId('pos-checkout-submit').click();

        // Verify payload was submitted
        await expect.poll(() => sent).not.toBeNull();
        expect(sent.payment_type).toBe('cash');
        expect(sent.paid_amount).toBe('75.000');
        expect(sent.items[0].item_id).toBe(ITEM.id);
        expect(sent.items[0].quantity).toBe('1.000');
    });
});
