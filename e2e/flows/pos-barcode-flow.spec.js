// POS-01: Barcode scanner flow (exact lookup, partial/unknown code rejection, serialized fast consecutive scans).
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport } from '../utils/pos-viewport.js';

const ITEM_A = {
    id: 991001,
    name: 'AGENT2 Espresso Beans',
    code: '6221000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '120.000',
    price_retail: '120.000',
    price_wholesale: '110.000',
    min_selling_price: '100.000',
    current_stock: '50.000',
    is_pos_pinned: true,
    pos_sales_count: 5,
};

const ITEM_B = {
    id: 991002,
    name: 'AGENT2 Colombian Blend',
    code: '6221000002222',
    unit: 'piece',
    is_weighted: false,
    selling_price: '180.000',
    price_retail: '180.000',
    price_wholesale: '165.000',
    min_selling_price: '150.000',
    current_stock: '40.000',
    is_pos_pinned: true,
    pos_sales_count: 3,
};

async function setupPosRoutes(page) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
    });

    await page.route('**/api/v1/items*', (route) =>
        route.fulfill({
            json: { success: true, data: [ITEM_A, ITEM_B], meta: { total: 2 } },
        })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({ json: { success: true, data: [], total_items_count: 2 } })
    );
    await page.route('**/api/v1/customers*', (route) => route.fulfill({ json: { success: true, data: [] } }));
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
    await page.route('**/api/v1/pos/lookup*', async (route) => {
        const url = new URL(route.request().url());
        const code = url.searchParams.get('code');
        if (code === ITEM_A.code) {
            return route.fulfill({
                json: {
                    success: true,
                    data: { item: ITEM_A, quantity: null, match_type: 'code' },
                },
            });
        }
        if (code === ITEM_B.code) {
            return route.fulfill({
                json: {
                    success: true,
                    data: { item: ITEM_B, quantity: null, match_type: 'code' },
                },
            });
        }
        return route.fulfill({
            status: 404,
            json: { success: false, message: 'Barcode not found' },
        });
    });
}

test.describe('POS-01: Barcode scanner flow', () => {
    skipOnPhoneViewport(test);

    test('exact barcode scan adds the matching item to the cart', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });

        const searchInput = page.getByTestId('pos-search-input');
        await expect(searchInput).toBeVisible();

        await searchInput.fill(ITEM_A.code);
        await searchInput.press('Enter');

        const qtyInput = page.locator('[data-testid="pos-cart-qty"]:visible');
        await expect(qtyInput).toHaveCount(1);
        await expect(qtyInput.first()).toHaveValue('1');
    });

    test('partial or unknown barcode does not add substring matches and shows not-found toast', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });

        const searchInput = page.getByTestId('pos-search-input');
        await expect(searchInput).toBeVisible();

        // '622100' is a prefix of ITEM_A.code ('6221000001111'), but a barcode scan must not match substrings.
        await searchInput.fill('622100');
        await searchInput.press('Enter');

        await expect(page.locator('.swal2-popup')).toBeVisible();
        await expect(page.locator('[data-testid="pos-cart-qty"]:visible')).toHaveCount(0);
    });

    test('fast consecutive scans are serialized in exact scan order', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });

        const searchInput = page.getByTestId('pos-search-input');
        await expect(searchInput).toBeVisible();

        await searchInput.fill(ITEM_A.code);
        await searchInput.press('Enter');
        await searchInput.fill(ITEM_B.code);
        await searchInput.press('Enter');

        const qtyInputs = page.locator('[data-testid="pos-cart-qty"]:visible');
        await expect(qtyInputs).toHaveCount(2);
        await expect(qtyInputs.nth(0)).toHaveAttribute('data-line-id', String(ITEM_A.id));
        await expect(qtyInputs.nth(1)).toHaveAttribute('data-line-id', String(ITEM_B.id));
    });
});
