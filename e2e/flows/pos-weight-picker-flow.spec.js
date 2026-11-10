// POS-06: Weighted item picker (opens on tap, 1/2 kg preset adds 0.500, and by-amount mode computes exact weight).
import { test, expect } from '@playwright/test';

const WEIGHTED_ITEM = {
    id: 998001,
    name: 'AGENT2 Fresh Roasted Beans (KG)',
    code: '6228000001111',
    unit: 'kg',
    is_weighted: true,
    selling_price: '400.000',
    price_retail: '400.000',
    price_wholesale: '380.000',
    min_selling_price: '360.000',
    current_stock: '35.000',
    is_pos_pinned: true,
    pos_sales_count: 8,
};

async function setupPosRoutes(page) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
    });

    await page.route('**/api/v1/items*', (route) =>
        route.fulfill({ json: { success: true, data: [WEIGHTED_ITEM], meta: { total: 1 } } })
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
}

test.describe('POS-06: Weighted item picker flow', () => {
    test('clicking weighted item opens weight picker and 1/2 kg preset adds 0.500 to cart', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        await page.getByText(WEIGHTED_ITEM.name, { exact: true }).first().click();

        const picker = page.getByTestId('pos-weight-picker');
        await expect(picker).toBeVisible();

        await page.getByTestId('pos-weight-preset-half').click();
        await expect(picker).toBeHidden();

        const qtyInput = page.locator('[data-testid="pos-cart-qty"]:visible').first();
        await expect(qtyInput).toHaveValue('0.500');
    });

    test('by-amount mode computes exact 3-decimal weight and adds it to cart', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        await page.getByText(WEIGHTED_ITEM.name, { exact: true }).first().click();
        await expect(page.getByTestId('pos-weight-picker')).toBeVisible();

        // Switch to 'by amount' mode: 100 EGP at 400.000 EGP/kg = 0.250 kg
        await page.getByTestId('pos-weight-mode-amount').click();
        await page.getByTestId('pos-weight-input').fill('100');

        await expect(page.getByTestId('pos-weight-qty')).toContainText('0.250');
        await page.getByTestId('pos-weight-confirm').click();

        const qtyInput = page.locator('[data-testid="pos-cart-qty"]:visible').first();
        await expect(qtyInput).toHaveValue('0.250');
    });
});
