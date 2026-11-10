// POS-11: Recent sales drawer (F8 open, reprint, quick return clamping to remaining_quantity & POST /returns).
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport } from '../utils/pos-viewport.js';

const ITEM = {
    id: 995001,
    name: 'AGENT2 Kenyan Filter',
    code: '6225000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '150.000',
    price_retail: '150.000',
    price_wholesale: '140.000',
    min_selling_price: '130.000',
    current_stock: '25.000',
    is_pos_pinned: true,
    pos_sales_count: 3,
};

const RECENT_INVOICE = {
    id: 5001,
    invoice_number: 'INV-2026-5001',
    customer_id: 701,
    customer_name: 'AGENT2 Loyal Customer',
    net_amount: '450.000',
    payment_type: 'cash',
    invoice_date: '2026-11-10',
    created_at: '2026-11-10T10:00:00Z',
};

const RETURN_SOURCE = {
    id: 5001,
    number: 'INV-2026-5001',
    party_id: 701,
    party_name: 'AGENT2 Loyal Customer',
    lines: [
        {
            id: 9001,
            item_id: 995001,
            item_name: 'AGENT2 Kenyan Filter',
            unit: 'piece',
            sold_quantity: '3.000',
            returned_quantity: '1.000',
            remaining_quantity: '2.000',
            unit_price: '150.000',
        },
    ],
};

async function setupPosRoutes(page, onReturnSubmit = null) {
    await page.addInitScript(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('pos_multi_orders'))
            .forEach((k) => localStorage.removeItem(k));
        window.__openedPrintUrls = [];
        window.open = (url) => {
            window.__openedPrintUrls.push(url);
            return null;
        };
    });

    await page.route('**/api/v1/system/context*', (route) =>
        route.fulfill({
            json: {
                success: true,
                data: { system: { business_date: '2026-11-10' } },
            },
        })
    );
    await page.route('**/api/v1/items*', (route) =>
        route.fulfill({
            json: { success: true, data: [ITEM], meta: { total: 1 } },
        })
    );
    await page.route('**/api/v1/categories*', (route) =>
        route.fulfill({ json: { success: true, data: [], total_items_count: 1 } })
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
    await page.route('**/api/v1/invoices*', (route) => {
        if (route.request().method() === 'GET') {
            return route.fulfill({ json: { success: true, data: [RECENT_INVOICE] } });
        }
        return route.fallback();
    });
    await page.route('**/api/v1/return-sources/sales_return/5001*', (route) =>
        route.fulfill({ json: { success: true, data: RETURN_SOURCE } })
    );
    await page.route('**/api/v1/returns', async (route) => {
        if (route.request().method() !== 'POST') return route.fallback();
        const payload = route.request().postDataJSON();
        if (onReturnSubmit) onReturnSubmit(payload);
        return route.fulfill({
            status: 201,
            json: { success: true, message: 'Return created', data: { id: 301 } },
        });
    });
}

test.describe('POS-11: Recent sales drawer, reprint & quick return', () => {
    skipOnPhoneViewport(test);

    test('F8 opens recent sales drawer and reprint button opens invoice print view', async ({ page }) => {
        await setupPosRoutes(page);
        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        await page.keyboard.press('F8');
        await expect(page.getByTestId('pos-recent-sales-list')).toBeVisible();
        await expect(page.getByTestId('pos-recent-sales-list')).toContainText(RECENT_INVOICE.invoice_number);

        await page.getByTestId('pos-recent-sales-reprint').first().click();
        const openedUrls = await page.evaluate(() => window.__openedPrintUrls);
        expect(openedUrls).toContain(`/invoices/${RECENT_INVOICE.id}/print?autoprint=true`);
    });

    test('quick return modal clamps return quantity to remaining_quantity and posts sales return', async ({ page }) => {
        let submittedReturn = null;
        await setupPosRoutes(page, (payload) => {
            submittedReturn = payload;
        });

        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });

        await page.keyboard.press('F8');
        await expect(page.getByTestId('pos-recent-sales-list')).toBeVisible();

        await page.getByTestId('pos-recent-sales-return').first().click();
        await expect(page.getByTestId('pos-quick-return-modal')).toBeVisible();

        const qtyInput = page.getByTestId('pos-quick-return-qty').first();
        await expect(qtyInput).toBeVisible();

        // Typing 10 when remaining_quantity is 2.000 clamps the input value to 2.000
        await qtyInput.fill('10');
        await expect(qtyInput).toHaveValue('2.000');

        await page.getByTestId('pos-quick-return-confirm').click();
        await expect.poll(() => submittedReturn).not.toBeNull();

        expect(submittedReturn.return_type).toBe('sales_return');
        expect(submittedReturn.invoice_id).toBe(RECENT_INVOICE.id);
        expect(submittedReturn.items).toEqual([{ invoice_item_id: 9001, quantity: '2.000' }]);
    });
});
