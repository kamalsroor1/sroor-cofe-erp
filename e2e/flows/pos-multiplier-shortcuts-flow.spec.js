// POS-16: Quantity multiplier prefix (3*, ٣*) and F1/Escape shortcuts modal.
import { test, expect } from '@playwright/test';

const ITEM = {
    id: 992001,
    name: 'AGENT2 Turkish Coffee',
    code: '6222000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '90.000',
    price_retail: '90.000',
    price_wholesale: '80.000',
    min_selling_price: '75.000',
    current_stock: '100.000',
    is_pos_pinned: true,
    pos_sales_count: 2,
};

async function setupPosRoutes(page) {
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
    await page.route('**/api/v1/pos/lookup*', (route) => {
        const url = new URL(route.request().url());
        if (url.searchParams.get('code') === ITEM.code) {
            return route.fulfill({
                json: { success: true, data: { item: ITEM, quantity: null, match_type: 'code' } },
            });
        }
        return route.fulfill({ status: 404, json: { success: false } });
    });
}

test.describe('POS-16: Quantity multiplier & keyboard shortcuts', () => {
    test('3* prefix with barcode adds quantity 3 to cart', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });

        const searchInput = page.getByTestId('pos-search-input');
        await searchInput.fill(`3*${ITEM.code}`);
        await searchInput.press('Enter');

        const qtyInput = page.locator('[data-testid="pos-cart-qty"]:visible').first();
        await expect(qtyInput).toBeVisible();
        await expect(qtyInput).toHaveValue('3');
    });

    test('Arabic-Indic digits ٣* prefix with barcode adds quantity 3 to cart', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });

        const searchInput = page.getByTestId('pos-search-input');
        await searchInput.fill(`٣*${ITEM.code}`);
        await searchInput.press('Enter');

        const qtyInput = page.locator('[data-testid="pos-cart-qty"]:visible').first();
        await expect(qtyInput).toBeVisible();
        await expect(qtyInput).toHaveValue('3');
    });

    test('F1 opens the shortcuts help modal and Escape closes it', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await expect(page.getByTestId('pos-search-input')).toBeVisible();

        await page.keyboard.press('F1');
        const modal = page.getByTestId('pos-shortcuts-modal');
        await expect(modal).toBeVisible();

        await page.keyboard.press('Escape');
        await expect(modal).toBeHidden();
    });
});
