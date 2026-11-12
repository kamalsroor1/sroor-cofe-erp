// POS-07: Discount cap (<= cap passes without override; > cap shows warning, asks manager PIN on 422, and retries with override).
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport } from '../utils/pos-viewport.js';

const ITEM = {
    id: 997001,
    name: 'AGENT2 Specialty Geisha',
    code: '6227000001111',
    unit: 'piece',
    is_weighted: false,
    selling_price: '200.000',
    price_retail: '200.000',
    price_wholesale: '190.000',
    min_selling_price: '150.000',
    current_stock: '20.000',
    is_pos_pinned: true,
    pos_sales_count: 5,
};

async function setupPosRoutes(page, { asCashier = false, onInvoice = null } = {}) {
    await page.addInitScript(
        ({ restrictPermissions }) => {
            Object.keys(localStorage)
                .filter((k) => k.startsWith('pos_multi_orders'))
                .forEach((k) => localStorage.removeItem(k));

            if (restrictPermissions) {
                try {
                    const user = JSON.parse(localStorage.getItem('auth_user') || '{}');
                    user.roles = ['cashier'];
                    user.permissions = ['pos.access', 'invoices.create', 'items.view'];
                    localStorage.setItem('auth_user', JSON.stringify(user));
                } catch {
                    // ignore
                }
            }
        },
        { restrictPermissions: asCashier }
    );

    await page.route('**/api/v1/pos/items*', (route) =>
        route.fulfill({
            json: { success: true, data: [ITEM], meta: { current_page: 1, last_page: 1, total: 1 } },
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
    await page.route('**/api/v1/invoices', async (route) => {
        if (route.request().method() !== 'POST') return route.fallback();
        const payload = route.request().postDataJSON();
        if (onInvoice) return route.fulfill(await onInvoice(payload));
        return route.fulfill({
            status: 201,
            json: {
                success: true,
                data: {
                    id: 701,
                    invoice_number: 'INV-701',
                    net_amount: '180.000',
                    change_amount: '0.000',
                },
            },
        });
    });
}

test.describe('POS-07: Discount cap & manager PIN override', () => {
    skipOnPhoneViewport(test);

    test('discount within store cap (<= 10%) succeeds without warning or override', async ({ page }) => {
        let sentPayload = null;
        await setupPosRoutes(page, {
            asCashier: true,
            onInvoice: (payload) => {
                sentPayload = payload;
                return {
                    status: 201,
                    json: {
                        success: true,
                        data: {
                            id: 701,
                            invoice_number: 'INV-701',
                            net_amount: '180.000',
                            change_amount: '0.000',
                        },
                    },
                };
            },
        });

        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });
        await page.getByText(ITEM.name, { exact: true }).first().click();

        // Apply 10% preset (equal to cap); the discount line is collapsed until it is opened
        await page.getByTestId('pos-discount-toggle').click();
        await page.getByTestId('pos-discount-preset-10').click();
        await expect(page.getByTestId('pos-discount-cap-warning')).toHaveCount(0);

        await page.getByTestId('pos-checkout-submit').click();
        await expect.poll(() => sentPayload).not.toBeNull();
        expect(sentPayload.override).toBeUndefined();
    });

    test('discount exceeding cap (> 10%) for cashier shows warning, prompts manager PIN, and retries with override', async ({
        page,
    }) => {
        const attempts = [];
        await setupPosRoutes(page, {
            asCashier: true,
            onInvoice: (payload) => {
                attempts.push(payload);
                if (!payload.override) {
                    return {
                        status: 422,
                        json: {
                            message: 'Manager approval required',
                            errors: {
                                override: ['Discount exceeds store cap (10%). Manager approval required.'],
                            },
                        },
                    };
                }
                return {
                    status: 201,
                    json: {
                        success: true,
                        data: {
                            id: 702,
                            invoice_number: 'INV-702',
                            net_amount: '160.000',
                            change_amount: '0.000',
                        },
                    },
                };
            },
        });

        await page.goto('/pos', { waitUntil: 'networkidle' });
        await page.getByTestId('pos-checkout-submit').waitFor({ state: 'visible' });
        await page.getByText(ITEM.name, { exact: true }).first().click();

        // Enter 20% discount (> 10% cap)
        await page.getByTestId('pos-discount-toggle').click();
        await page.getByTestId('pos-discount-input').fill('20');
        await expect(page.getByTestId('pos-discount-cap-warning')).toBeVisible();

        // Submit -> server returns 422 errors.override -> Manager PIN modal opens
        await page.getByTestId('pos-checkout-submit').click();
        await expect(page.locator('#pos-override-user')).toBeVisible();
        await expect(page.locator('#pos-override-pin')).toBeVisible();

        await page.locator('#pos-override-user').fill('1');
        await page.locator('#pos-override-pin').fill('1234');
        await page.locator('.swal2-confirm').click();

        await expect.poll(() => attempts.length).toBe(2);
        expect(attempts[0].override).toBeUndefined();
        expect(attempts[1].override).toEqual({ user_id: 1, pin: '1234' });
        // Both attempts reuse the exact same idempotent client_uuid
        expect(attempts[1].client_uuid).toBe(attempts[0].client_uuid);
    });
});
