// POS-09: Customer search & selection (remote customer snapshot retention and wholesale cart repricing).
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport, expectSelectedCustomer } from '../utils/pos-viewport.js';

const ITEM = {
    id: 994001,
    name: 'AGENT2 Wholesale Coffee Bag',
    code: '6224000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '200.000',
    price_retail: '200.000',
    wholesale_price: '160.000',
    price_wholesale: '160.000',
    min_selling_price: '150.000',
    current_stock: '50.000',
    is_pos_pinned: true,
    pos_sales_count: 5,
};

const REMOTE_WHOLESALE_CUSTOMER = {
    id: 880999,
    name: 'AGENT2 Wholesale Roastery Client',
    phone: '01099887766',
    price_tier: 'wholesale',
    current_balance: '0.000',
    is_walk_in: false,
};

async function setupPosRoutes(page) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
    });

    await page.route('**/api/v1/items*', (route) =>
        route.fulfill({
            json: { success: true, data: [ITEM], meta: { total: 1 } },
        })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({ json: { success: true, data: [], total_items_count: 1 } })
    );
    // Initial preload returns empty list; searching queries ?search=... and returns REMOTE_WHOLESALE_CUSTOMER.
    await page.route('**/api/v1/customers*', (route) => {
        const url = new URL(route.request().url());
        const search = url.searchParams.get('search') || '';
        if (search.toLowerCase().includes('roastery') || search.includes('0109988')) {
            return route.fulfill({
                json: { success: true, data: [REMOTE_WHOLESALE_CUSTOMER] },
            });
        }
        return route.fulfill({ json: { success: true, data: [] } });
    });
    await page.route('**/api/v1/shifts/current*', (route) =>
        route.fulfill({
            json: {
                success: true,
                active_shift: { id: 1, shift_number: 'SH-1' },
                requires_shift: false,
            },
        })
    );
    await page.route('**/api/v1/pos/bootstrap*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: { pos_settings: { effective_max_discount_percent: '10.000' } },
            },
        })
    );
}

test.describe('POS-09: Customer search & wholesale repricing', () => {
    skipOnPhoneViewport(test);

    test('picking a remote-searched wholesale customer keeps customer name and reprices cart to wholesale', async ({
        page,
    }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        // Add item at retail price (200)
        await page.getByText(ITEM.name, { exact: true }).first().click();
        const priceInput = page.locator('[data-line-field="price"]:visible').first();
        await expect(priceInput).toHaveValue('200');

        // Open customer modal and search for remote customer not in initial preload
        await page.getByTestId('pos-customer-picker-btn').click();
        await expect(page.getByTestId('pos-customer-modal')).toBeVisible();

        await page.getByTestId('pos-customer-search-input').fill('Roastery');
        const customerRow = page.getByTestId('pos-customer-row').first();
        await expect(customerRow).toBeVisible();
        await customerRow.click();

        // Modal closes, header shows picked customer name (never falls back to walk-in)
        await expect(page.getByTestId('pos-customer-modal')).toBeHidden();
        await expectSelectedCustomer(page, expect, REMOTE_WHOLESALE_CUSTOMER.name);

        // Cart line is automatically repriced to wholesale_price (160) and credit/partial are unlocked
        await expect(priceInput).toHaveValue('160');
        await expect(page.getByTestId('pos-payment-type-credit')).toBeEnabled();
        await expect(page.getByTestId('pos-payment-type-partial')).toBeEnabled();
    });
});
