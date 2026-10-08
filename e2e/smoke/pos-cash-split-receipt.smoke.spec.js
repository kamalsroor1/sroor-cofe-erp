// LOCAL smoke (real backend, throwaway DB): tenant password login -> POS cash sale with change ->
// POS split payment with change -> receipt print view -> logout.
//
// Nothing is mocked: invoices, stock and tokens are real rows in the throwaway tenant DB built by
// prepare-local-smoke.php (item ITEM.price = 120.500, opening stock ITEM.stock = 25.000).
import { test, expect } from '@playwright/test';
import { ITEM, TENANT_ID, credentials } from './smoke-env.mjs';
import { apiAs, collectConsoleErrors, loginToTenant, stubPrint } from './smoke-helpers.js';

test.describe.configure({ mode: 'serial' });

/** Waits for the next POST /api/v1/invoices and returns { request payload, status, body }. */
function nextInvoice(page) {
    return page
        .waitForResponse(
            (res) => /\/api\/v1\/invoices$/.test(new URL(res.url()).pathname) && res.request().method() === 'POST'
        )
        .then(async (res) => ({
            payload: res.request().postDataJSON(),
            status: res.status(),
            body: await res.json(),
        }));
}

async function addSmokeItemToCart(page) {
    await page.getByText(ITEM.name, { exact: true }).first().click();
    await expect(page.locator('[data-testid="pos-cart-qty"]:visible').first()).toHaveValue(/^1(\.0+)?$/);
}

async function closeSuccessModal(page) {
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('pos-success-change')).toHaveCount(0);
}

test('cashier: password login, cash + split sales with change, receipt print view, logout', async ({
    page,
    context,
}) => {
    const consoleErrors = collectConsoleErrors(page);
    await stubPrint(context);

    // ---- 1. Password login (workspace code -> phone + password) -----------------------------
    const login = await loginToTenant(page, {
        tenantCode: TENANT_ID,
        login: credentials.tenantAdminPhone,
        password: credentials.tenantAdminPassword,
    });
    const token = login.data.token;
    expect(token, 'login returns a bearer token').toBeTruthy();
    expect(await page.evaluate(() => localStorage.getItem('tenant_id'))).toBe(TENANT_ID);

    // ---- 2. POS loads the seeded item ------------------------------------------------------
    await page.goto('/pos');
    await expect(page.getByTestId('pos-checkout-submit')).toBeVisible();
    await expect(page.getByText(ITEM.name, { exact: true }).first()).toBeVisible();

    // ---- 3. Cash sale: 1 x 120.500, cash received 150 -> change 29.500 ----------------------
    await addSmokeItemToCart(page);
    await page.getByTestId('pos-payment-type-cash').click();
    await page.getByTestId('pos-cash-received').fill('150');

    const cashSale = nextInvoice(page);
    await page.getByTestId('pos-checkout-submit').click();
    const cash = await cashSale;

    expect(cash.status, JSON.stringify(cash.body)).toBe(201);
    expect(cash.payload.payment_type).toBe('cash');
    expect(cash.payload.paid_amount).toBe('120.500');
    expect(cash.payload.items).toEqual([expect.objectContaining({ quantity: '1.000', unit_price: ITEM.price })]);
    expect(cash.payload.payments).toEqual([{ method: 'cash', amount: '150.000' }]);
    expect(cash.body.data.change_amount).toBe('29.500');
    await expect(page.getByTestId('pos-success-change-amount')).toContainText('29.5');
    await closeSuccessModal(page);

    // ---- 4. Split sale: visa 50 + cash 100 on 120.500 -> change 29.500 (cash only) -----------
    await addSmokeItemToCart(page);
    await page.getByTestId('pos-payment-type-cash').click();
    await page.getByTestId('pos-split-payment-btn').click();

    const rows = page.getByTestId('pos-split-row');
    await expect(rows).toHaveCount(1);
    await rows.nth(0).getByTestId('pos-split-method').selectOption('visa');
    await rows.nth(0).getByTestId('pos-split-amount').fill('50');

    // "Add payment method" chips are the only Plus-icon buttons in the split modal.
    const splitModal = page.locator('div.fixed.inset-0').filter({ has: page.getByTestId('pos-split-confirm') });
    await splitModal.locator('button:has([class*="lucide-plus"])').first().click();
    await expect(rows).toHaveCount(2);
    await rows.nth(1).getByTestId('pos-split-method').selectOption('cash');
    await rows.nth(1).getByTestId('pos-split-amount').fill('100');

    await expect(page.getByTestId('pos-split-non-cash-error')).toHaveCount(0);
    await expect(page.getByTestId('pos-split-confirm')).toBeEnabled();
    await page.getByTestId('pos-split-confirm').click();

    const splitSale = nextInvoice(page);
    await page.getByTestId('pos-checkout-submit').click();
    const split = await splitSale;

    expect(split.status, JSON.stringify(split.body)).toBe(201);
    expect(split.payload.payment_type).toBe('cash');
    expect(split.payload.paid_amount).toBe('120.500');
    expect(split.payload.payments).toEqual([
        { method: 'visa', amount: '50.000' },
        { method: 'cash', amount: '100.000' },
    ]);
    expect(split.body.data.change_amount).toBe('29.500');
    await expect(page.getByTestId('pos-success-change-amount')).toContainText('29.5');

    // Server state: the split invoice is stored with its payment lines, stock went 25 -> 23.
    const invoiceRes = await apiAs(page, 'GET', `/api/v1/invoices/${split.body.data.id}`);
    expect(invoiceRes.status()).toBe(200);
    const invoice = (await invoiceRes.json()).data;
    expect(invoice.invoice_number).toBe(split.body.data.invoice_number);
    expect(invoice.change_amount).toBe('29.500');

    const itemsRes = await apiAs(page, 'GET', `/api/v1/items?search=${encodeURIComponent(ITEM.code)}`);
    expect(itemsRes.status()).toBe(200);
    const item = (await itemsRes.json()).data.find((row) => row.code === ITEM.code);
    expect(item, 'seeded item is listed').toBeTruthy();
    // TODO(defect): ItemResource returns stock as a float; compare the normalised value until it is a decimal string.
    expect(Number(item.current_stock).toFixed(3)).toBe('23.000');

    // ---- 5. Receipt print view (browser fallback opens a popup with ?autoprint=true) --------
    // The success modal has no role/testid on its print button (gap reported); scope by the change testid.
    const successModal = page.locator('div.fixed.inset-0').filter({ has: page.getByTestId('pos-success-change') });
    const popupPromise = page.waitForEvent('popup');
    await successModal.locator('button:has([class*="lucide-printer"])').click();
    const receipt = await popupPromise;
    const receiptErrors = collectConsoleErrors(receipt);
    await receipt.waitForURL(new RegExp(`/invoices/${split.body.data.id}/print\\?autoprint=true`));
    const printArea = receipt.locator('#receipt-print-area');
    await expect(printArea).toBeVisible();
    await expect(printArea).toContainText(split.body.data.invoice_number);
    await expect(printArea).toContainText(ITEM.name);
    await expect.poll(() => receipt.evaluate(() => window.__smokePrintCalls)).toBeGreaterThanOrEqual(1);
    await receipt.close();
    expect(receiptErrors, 'receipt view console errors').toEqual([]);

    // ---- 6. Logout (header button -> SweetAlert confirm) revokes the token ------------------
    await closeSuccessModal(page);
    // The POS screen hides the app header (SpaLayout `v-if="!isPosView"`), so leave POS first.
    await page.goto('/');
    await page.locator('header button:has([class*="lucide-log-out"]):visible').first().click();
    const logoutResponse = page.waitForResponse((res) => res.url().includes('/api/v1/auth/logout'));
    await page.locator('.swal2-confirm').click();
    expect((await logoutResponse).status()).toBe(200);
    await page.waitForURL((url) => url.pathname === '/login');
    expect(await page.evaluate(() => localStorage.getItem('auth_token'))).toBeNull();

    const reuse = await page.request.get('/api/v1/auth/me', {
        headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`,
            'X-Tenant': TENANT_ID,
        },
    });
    expect(reuse.status(), 'revoked token must be rejected').toBe(401);

    expect(consoleErrors, 'POS journey console errors').toEqual([]);
});
