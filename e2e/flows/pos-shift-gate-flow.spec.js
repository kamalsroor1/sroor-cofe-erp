// POS-10: Shift gate (blocks POS when requires_shift=true and no open shift; opens shift; X-report & close shift).
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport } from '../utils/pos-viewport.js';

const ITEM = {
    id: 996001,
    name: 'AGENT2 Morning Espresso',
    code: '6226000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '75.000',
    price_retail: '75.000',
    price_wholesale: '70.000',
    min_selling_price: '65.000',
    current_stock: '80.000',
    is_pos_pinned: true,
    pos_sales_count: 10,
};

const OPEN_SHIFT = {
    id: 401,
    shift_number: 'SH-2026-401',
    opened_at: '2026-11-10T08:00:00Z',
    opening_cash_balance: '500.000',
};

const SHIFT_METRICS = {
    opening_cash_balance: '500.000',
    total_cash_sales: '250.000',
    total_customer_receipts: '0.000',
    total_credit_sales: '0.000',
    total_expenses: '25.000',
    total_supplier_paid: '0.000',
    total_refunds: '0.000',
    expected_cash_balance: '725.000',
};

test.describe('POS-10: Shift gate, open shift, X-report & close shift', () => {
    skipOnPhoneViewport(test);

    test('shows shift gate when requires_shift is true and no shift is open, then opens shift and closes with X-report', async ({
        page,
    }) => {
        let shiftIsOpen = false;
        let openPayload = null;
        let closePayload = null;

        await page.addInitScript(() => {
            Object.keys(localStorage)
                .filter((k) => k.startsWith('pos_multi_orders'))
                .forEach((k) => localStorage.removeItem(k));
        });

        await page.route('**/api/v1/pos/items*', (route) =>
            route.fulfill({
                json: { success: true, data: [ITEM], meta: { total: 1 } },
            })
        );
        await page.route('**/api/v1/categories*', (route) =>
            route.fulfill({
                json: { success: true, data: [], total_items_count: 1 },
            })
        );
        await page.route('**/api/v1/customers*', (route) => route.fulfill({ json: { success: true, data: [] } }));
        await page.route('**/api/v1/pos/bootstrap*', (route) =>
            route.fulfill({
                json: {
                    success: true,
                    data: { pos_settings: { effective_max_discount_percent: '10.000' } },
                },
            })
        );
        await page.route('**/api/v1/shifts/current*', (route) =>
            route.fulfill({
                json: {
                    success: true,
                    requires_shift: true,
                    active_shift: shiftIsOpen ? OPEN_SHIFT : null,
                    metrics: shiftIsOpen ? SHIFT_METRICS : null,
                },
            })
        );
        await page.route('**/api/v1/shifts/open', (route) => {
            openPayload = route.request().postDataJSON();
            shiftIsOpen = true;
            return route.fulfill({
                status: 201,
                json: { success: true, data: OPEN_SHIFT },
            });
        });
        await page.route('**/api/v1/shifts/close', (route) => {
            closePayload = route.request().postDataJSON();
            shiftIsOpen = false;
            return route.fulfill({
                status: 200,
                json: { success: true, message: 'Shift closed' },
            });
        });

        await page.goto('/pos', { waitUntil: 'networkidle' });

        // Gate is visible and blocks selling until shift is opened
        const gate = page.getByTestId('pos-shift-gate');
        await expect(gate).toBeVisible();

        await page.locator('#pos-shift-opening-cash').fill('500');
        await page.getByTestId('pos-shift-open-form').locator('button[type="submit"]').click();

        await expect.poll(() => openPayload).not.toBeNull();
        expect(openPayload.opening_cash_balance).toBe('500.000');

        // Gate disappears and POS is unlocked for selling
        await expect(gate).toBeHidden();
        await page.getByText(ITEM.name, { exact: true }).first().click();
        await expect(page.locator('[data-testid="pos-cart-qty"]:visible')).toHaveCount(1);

        // Open shift X-report panel via header shift badge and close the shift
        await page.getByTestId('pos-shift-badge').click();
        await expect(page.getByTestId('pos-shift-panel')).toBeVisible();
        await expect(page.getByTestId('pos-shift-report')).toBeVisible();
        await expect(page.getByTestId('pos-shift-report')).toContainText('725');

        await page.locator('#pos-shift-counted-cash').fill('725');
        await page.getByTestId('pos-shift-close').click();

        await expect.poll(() => closePayload).not.toBeNull();
        expect(closePayload).toEqual({
            shift_id: OPEN_SHIFT.id,
            actual_cash_balance: '725.000',
        });
    });
});
