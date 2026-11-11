// SaaS onboarding journey, end to end, against the LOCAL docker dev stack only
// (docs/07-operations/local-docker.md). Nothing is mocked: every row is real.
//
//  0. setup     a throwaway platform operator (tinker) + 2FA enrolled through the API (TOTP in memory)
//  1. guest     sroor.test:8000/ -> /welcome lists the public plans; /pricing renders
//  2. guest     /signup on the trial plan -> success state
//  3. console   operator signs in (password + TOTP), approves the request; the 2FA step-up prompt
//               opens ABOVE the approve modal (commit 6294e099) and is completed with a TOTP code
//  4. platform  provisioning polled through /central/tenants/resolve until ready; status = trial
//  5. owner     signs in on <slug>.localhost:8000 -> dashboard
//  6. owner     creates a weighted item (UI), stocks it with a purchase (API), opens a shift and
//               sells 0.250 kg in cash at the POS (UI)
//  7. checks    exact decimal stock, invoice in the list / show / receipt views, treasury cash
//  8. return    0.100 kg returned from the POS recent-sales drawer; stock + cash reverse exactly;
//               shift closed and its Z report read
//  9. isolation the owner's token is refused by tenant `demo`
// 10. cleanup   tenant cancelled + archived via the console API, operator deactivated
//
// Run (from the repo root, stack up):
//   npx playwright test e2e/flows/saas-onboarding-flow.spec.js --project=desktop
import { test, expect } from '@playwright/test';
import {
    HOST_RESOLVER_ARGS,
    LOCAL_HOSTS,
    assertLocalUrl,
    composeGatewayIp,
    tinker,
    tinkerValue,
} from '../utils/local-stack.js';
import { createTotpIssuer } from '../utils/totp.js';
import { t } from '../utils/translations.js';

// A fresh browser (no stored demo session) that resolves the platform apex to the local nginx.
test.use({
    storageState: { cookies: [], origins: [] },
    launchOptions: { args: HOST_RESOLVER_ARGS },
    locale: 'ar-EG',
});

const RUN_ID = Date.now();
const SLUG = `e2e${RUN_ID}`;
const BUSINESS_NAME = `متجر اختبار E2E ${RUN_ID}`;
const CONTACT_NAME = 'مدير الاختبار';
const PHONE = `0100${String(RUN_ID).slice(-7)}`;
const OWNER_EMAIL = `owner-${RUN_ID}@e2e.local`;
// Test-only values, generated per run, never real credentials.
const OWNER_PASSWORD = `Owner${RUN_ID}x`;
const OPERATOR_EMAIL = `e2e-operator-${RUN_ID}@e2e.local`;
const OPERATOR_PASSWORD = `Operator-${RUN_ID}-pw`;
const ITEM_NAME = `بن محوج E2E ${RUN_ID}`;
const TRIAL_PLAN_CODE = 'free';

const ADMIN = LOCAL_HOSTS.admin;
const APEX = LOCAL_HOSTS.apex;
const TENANT = LOCAL_HOSTS.tenant(SLUG);
for (const url of [ADMIN, APEX, TENANT, LOCAL_HOSTS.loopback, LOCAL_HOSTS.tenant('demo')]) assertLocalUrl(url);

const PRODUCTION_HOSTS = /(^|\.)(ibraa\.app|baraa-solutions\.com)$/u;
const IGNORED_CONSOLE = [/favicon/i, /net::ERR_ABORTED/i, /Download the Vue Devtools/i, /\[vite\]/i];

/** Records an app defect, keeps the run going, and fails the test at the end (expect.soft). */
function recordDefect(title, details) {
    const text = `${title}\n${typeof details === 'string' ? details : JSON.stringify(details, null, 2)}`;
    test.info().annotations.push({ type: 'app-defect', description: text });
    expect.soft(false, `APP DEFECT: ${text}`).toBe(true);
}

/**
 * Money / quantity must come back as a 3-decimal string (golden rule 1). A float is recorded as a
 * defect (once per field), and the value is still checked exactly at 0.001 so the journey continues.
 */
const reportedFloatFields = new Set();
function expectDecimal(value, expected, label) {
    if (value !== expected && !reportedFloatFields.has(label)) {
        reportedFloatFields.add(label);
        recordDefect(`${label} is not the decimal string "${expected}"`, `received ${JSON.stringify(value)} (${typeof value})`);
    }
    expect(Math.round(Number(value) * 1000), `${label} value`).toBe(Math.round(Number(expected) * 1000));
}

/** A step whose failure is recorded and does not stop the journey. */
async function softStep(title, body) {
    return test.step(title, async () => {
        try {
            return await body();
        } catch (error) {
            recordDefect(`step failed: ${title}`, String(error?.stack || error));
            return undefined;
        }
    });
}

function collectConsoleErrors(page, bucket) {
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        const text = msg.text();
        if (!IGNORED_CONSOLE.some((re) => re.test(text))) bucket.push(`${page.url()} :: ${text}`);
    });
    page.on('pageerror', (err) => bucket.push(`${page.url()} :: pageerror ${err.message}`));
}

async function json(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch {
        return { raw: text.slice(0, 500) };
    }
}

/** Calls the tenant API with the token / store the SPA stored on the tenant origin. */
async function tenantApi(page, method, url, data) {
    const headers = await page.evaluate(() => {
        const h = { Accept: 'application/json' };
        const token = localStorage.getItem('auth_token');
        const store = localStorage.getItem('current_store_id');
        if (token) h.Authorization = `Bearer ${token}`;
        if (store) h['X-Store-Id'] = store;
        return h;
    });
    const full = `${TENANT}${url}`;
    assertLocalUrl(full);
    const response = await page.request.fetch(full, { method, headers, data });
    return { status: response.status(), body: await json(response) };
}

function waitForApi(page, method, pathPattern) {
    return page.waitForResponse(
        (res) => res.request().method() === method && pathPattern.test(new URL(res.url()).pathname),
        { timeout: 30_000 }
    );
}

test.describe.configure({ mode: 'serial' });

test('SaaS onboarding: landing -> signup -> console approval with step-up -> owner sells and returns', async ({
    page,
    context,
    request,
}) => {
    test.setTimeout(10 * 60_000);
    const consoleErrors = [];
    collectConsoleErrors(page, consoleErrors);

    // Belt and braces: nothing in this run may reach a production host.
    const productionHits = [];
    await context.route(
        (url) => PRODUCTION_HOSTS.test(url.hostname),
        (route) => {
            productionHits.push(route.request().url());
            return route.abort();
        }
    );

    const state = { operatorId: null, totp: null, signupId: null, tenantId: null, invoice: null };

    // ---------------------------------------------------------------------------------------------
    await test.step('0. setup: throwaway operator with confirmed 2FA', async () => {
        // Each run makes ONE accepted signup, the limiter allows 3 per hour per IP: reset only the
        // signup keys of this machine's compose gateway so reruns stay possible (local stack only).
        const ip = composeGatewayIp();
        if (ip) {
            tinker(
                `foreach (["signup-requests-hour|".getenv("E2E_IP"), "signup-requests-day|".getenv("E2E_IP"), "signup-requests-global-hour"] as $k) { Illuminate\\Support\\Facades\\RateLimiter::clear(md5("signup-requests".$k)); } echo "OK=1";`,
                { E2E_IP: ip }
            );
        }

        const out = tinker(
            '$u = app(App\\Actions\\Central\\SuperAdmins\\CreateCentralSuperAdminAction::class)->execute("E2E Operator", getenv("E2E_OP_EMAIL"), getenv("E2E_OP_PASSWORD")); echo "OPERATOR_ID=".$u->getKey();',
            { E2E_OP_EMAIL: OPERATOR_EMAIL, E2E_OP_PASSWORD: OPERATOR_PASSWORD }
        );
        state.operatorId = tinkerValue(out, 'OPERATOR_ID');

        const login = await request.post(`${ADMIN}/api/v1/super-admin/auth/login`, {
            headers: { Accept: 'application/json' },
            data: { email: OPERATOR_EMAIL, password: OPERATOR_PASSWORD },
        });
        const loginBody = await json(login);
        expect(login.status(), JSON.stringify(loginBody)).toBe(200);
        expect(loginBody.data.two_factor_setup_required).toBe(true);
        const setupToken = loginBody.data.token;

        const enable = await request.post(`${ADMIN}/api/v1/super-admin/auth/two-factor/enable`, {
            headers: { Accept: 'application/json', Authorization: `Bearer ${setupToken}` },
        });
        const enableBody = await json(enable);
        expect(enable.status(), JSON.stringify(enableBody)).toBe(200);
        state.totp = createTotpIssuer(enableBody.data.secret);

        const confirm = await request.post(`${ADMIN}/api/v1/super-admin/auth/two-factor/confirm`, {
            headers: { Accept: 'application/json', Authorization: `Bearer ${setupToken}` },
            data: { code: await state.totp.next() },
        });
        const confirmBody = await json(confirm);
        expect(confirm.status(), JSON.stringify(confirmBody)).toBe(200);
        expect(confirmBody.data.abilities).toEqual(['central:*']);
    });

    // ---------------------------------------------------------------------------------------------
    await test.step('1. guest: landing redirects to /welcome with the seeded plans; /pricing renders', async () => {
        const plansResponse = await request.get(`${LOCAL_HOSTS.loopback}/api/v1/public/plans`);
        expect(plansResponse.status()).toBe(200);
        const plans = (await plansResponse.json()).data.plans;
        expect(plans.map((p) => p.code)).toEqual(expect.arrayContaining([TRIAL_PLAN_CODE]));

        await page.goto(`${APEX}/`);
        await page.waitForURL((url) => url.pathname === '/welcome');
        for (const plan of plans) {
            await expect(page.getByRole('heading', { name: plan.name, exact: true }).first()).toBeVisible();
        }

        await page.goto(`${APEX}/pricing`);
        await expect(page).toHaveURL(/\/pricing$/u);
        for (const plan of plans) {
            await expect(page.getByRole('heading', { name: plan.name, exact: true }).first()).toBeVisible();
        }
    });

    // ---------------------------------------------------------------------------------------------
    await test.step('2. guest: signup request on the trial plan', async () => {
        await page.goto(`${APEX}/signup`);
        await page.getByLabel(t('signup.form.business_name')).fill(BUSINESS_NAME);
        await page.getByLabel(t('signup.form.contact_name')).fill(CONTACT_NAME);
        await page.getByLabel(t('signup.form.phone')).fill(PHONE);
        await page.getByLabel(t('signup.form.email')).fill(OWNER_EMAIL);

        await page.getByLabel(t('signup.form.business_type')).click();
        await page.getByRole('option', { name: t('signup.business_types.retail'), exact: true }).click();

        const trialName = t('plans.plans.free.name');
        await page.getByLabel(t('signup.form.plan')).click();
        await page.getByRole('option', { name: trialName, exact: true }).click();

        await page.getByLabel(t('signup.form.subdomain')).fill(SLUG);
        await page.getByTestId('signup-terms-checkbox').check();

        const submitted = waitForApi(page, 'POST', /\/api\/v1\/public\/signup-requests$/u);
        await page.getByRole('button', { name: t('signup.form.submit') }).click();
        const response = await submitted;
        const body = await json(response);
        expect(response.status(), JSON.stringify(body)).toBe(201);
        expect(response.request().postDataJSON()).toMatchObject({
            plan_code: TRIAL_PLAN_CODE,
            preferred_subdomain: SLUG,
            phone: PHONE,
            terms_accepted: true,
        });
        await expect(page.getByRole('status')).toBeVisible();
    });

    // ---------------------------------------------------------------------------------------------
    await test.step('3. console: operator signs in with password + TOTP', async () => {
        await page.goto(`${ADMIN}/super-admin/login`);
        await page.getByLabel(t('super.central_auth.email_label')).fill(OPERATOR_EMAIL);
        await page.getByLabel(t('super.central_auth.password_label')).fill(OPERATOR_PASSWORD);
        await page.getByRole('button', { name: t('super.central_auth.sign_in') }).click();

        await page.getByLabel(t('super.central_auth.code_label')).fill(await state.totp.next());
        const challenge = waitForApi(page, 'POST', /\/super-admin\/auth\/two-factor-challenge$/u);
        await page.getByRole('button', { name: t('super.central_auth.verify') }).click();
        expect((await challenge).status()).toBe(200);
        await page.waitForURL((url) => url.pathname.startsWith('/super-admin') && !url.pathname.endsWith('/login'));
    });

    await test.step('3b. console: approve on the trial plan through the stacked step-up prompt', async () => {
        // The sign-in proved 2FA moments ago; age that proof past the 15 min step-up window so the
        // approve request meets RequireRecentTwoFactor exactly like an operator back from lunch.
        tinker(
            'App\\Models\\CentralPersonalAccessToken::query()->where("tokenable_id", (int) getenv("E2E_OP_ID"))->update(["two_factor_verified_at" => now()->subMinutes(16)]); echo "OK=1";',
            { E2E_OP_ID: state.operatorId }
        );

        await page.goto(`${ADMIN}/super-admin/signup-requests`);
        await page.getByPlaceholder(t('signup.console.search_placeholder')).fill(BUSINESS_NAME);
        const card = page.locator('[data-testid^="signup-request-"]').filter({ hasText: BUSINESS_NAME });
        await expect(card).toHaveCount(1);
        state.signupId = (await card.getAttribute('data-testid')).replace('signup-request-', '');
        await card.getByRole('button', { name: t('signup.console.approve') }).click();

        const subdomain = page.getByLabel(t('signup.console.subdomain_label'));
        await expect(subdomain).toHaveValue(SLUG);
        await expect(page.getByLabel(t('signup.console.owner_email'))).toHaveValue(OWNER_EMAIL);
        await expect(page.getByLabel(t('signup.console.plan_label'))).toContainText(t('plans.plans.free.name'));
        await page.getByLabel(t('signup.console.owner_password')).fill(OWNER_PASSWORD);

        const firstAttempt = waitForApi(page, 'POST', /\/signup-requests\/\d+\/approve$/u);
        await page.getByRole('button', { name: t('signup.console.approve_confirm') }).click();
        const refused = await firstAttempt;
        expect(refused.status()).toBe(403);
        expect((await json(refused)).error_code ?? (await json(refused)).code).toBeDefined();

        // The step-up prompt: visible, and the TOP layer (its input is what a click lands on).
        const code = page.getByLabel(t('super.central_auth.code_label'));
        await expect(code).toBeVisible();
        const topmost = await code.evaluate((input) => {
            const box = input.getBoundingClientRect();
            const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
            return hit === input || input.contains(hit);
        });
        expect(topmost, 'the step-up prompt must be above the approve modal').toBe(true);
        await code.click(); // Playwright's actionability check also fails if another layer intercepts.
        await code.fill(await state.totp.next());

        const stepUp = waitForApi(page, 'POST', /\/super-admin\/auth\/step-up$/u);
        const retried = waitForApi(page, 'POST', /\/signup-requests\/\d+\/approve$/u);
        await page.getByRole('button', { name: t('super.central_auth.step_up_confirm') }).click();
        expect((await stepUp).status()).toBe(200);
        const approved = await retried;
        const approvedBody = await json(approved);
        expect(approved.status(), JSON.stringify(approvedBody)).toBeLessThan(300);
        await expect(code).toBeHidden();
        await expect(page.getByText(t('signup.console.preparing_title'))).toBeVisible();

        // The deliberate step-up 403 above is logged by Chromium as a failed resource: exactly one.
        const stepUpNoise = consoleErrors.filter((e) => /signup-requests :: Failed to load resource: .* 403/u.test(e));
        expect(stepUpNoise.length, 'only the step-up 403 is logged').toBe(1);
        consoleErrors.splice(consoleErrors.indexOf(stepUpNoise[0]), 1);
    });

    // ---------------------------------------------------------------------------------------------
    await test.step('4. platform: provisioning finishes, tenant status is trial', async () => {
        let last = null;
        await expect
            .poll(
                async () => {
                    const res = await request.get(
                        `${LOCAL_HOSTS.loopback}/api/v1/central/tenants/resolve?code=${encodeURIComponent(SLUG)}`,
                        { headers: { Accept: 'application/json' } }
                    );
                    last = { status: res.status(), body: await json(res) };
                    return last.status;
                },
                { timeout: 240_000, intervals: [3_000, 4_000, 5_000] }
            )
            .toBe(200);
        expect(last.body.success).toBe(true);
        state.tenantId = last.body.data.tenant_id;
        expect(last.body.data.slug).toBe(SLUG);
        expect(last.body.data.status, JSON.stringify(last.body.data)).toBe('trial');
    });

    // ---------------------------------------------------------------------------------------------
    await test.step('5. owner: signs in on the tenant host and lands on the dashboard', async () => {
        await page.goto(`${TENANT}/login`);
        await page.locator('#login').fill(OWNER_EMAIL);
        await page.locator('#password').fill(OWNER_PASSWORD);
        const login = waitForApi(page, 'POST', /\/api\/v1\/auth\/login$/u);
        await page.locator('#password').press('Enter');
        const res = await login;
        const body = await json(res);
        expect(res.status(), JSON.stringify(body)).toBe(200);
        await page.waitForURL((url) => !url.pathname.startsWith('/login') && !url.pathname.startsWith('/connect'));
        await expect(page.locator('#app > *').first()).toBeVisible();

        const me = await tenantApi(page, 'GET', '/api/v1/auth/me');
        expect(me.status).toBe(200);
        expect(JSON.stringify(me.body)).toContain(OWNER_EMAIL);
    });

    // ---------------------------------------------------------------------------------------------
    const ids = { item: null, shift: null };
    const treasury = { before: null };

    await test.step('6a. owner: creates a weighted item through the items page', async () => {
        await page.goto(`${TENANT}/items`);
        await page.getByRole('button', { name: t('inventory.add_item') }).click();
        await page.getByLabel(t('inventory.item_name')).fill(ITEM_NAME);

        await page.getByLabel(t('inventory.unit')).click();
        const options = page.getByRole('option');
        const labels = await options.allInnerTexts();
        const weightLabel = labels.map((l) => l.trim()).find((l) => /^(كيلو|كجم|كج|كيلوجرام|kg)$/iu.test(l));
        expect(weightLabel, `a weight unit in the tenant unit list: ${labels.join(', ')}`).toBeTruthy();
        await page.getByRole('option', { name: weightLabel, exact: true }).click();
        await expect(page.getByTestId('item-is-weighted')).toBeChecked();

        await page.getByLabel(t('inventory.cost_price')).fill('100');
        await page.getByLabel(t('inventory.selling_price')).fill('250');

        const created = waitForApi(page, 'POST', /\/api\/v1\/items$/u);
        await page.getByRole('button', { name: t('common.save'), exact: true }).click();
        const res = await created;
        const body = await json(res);
        expect(res.status(), JSON.stringify(body)).toBe(201);
        ids.item = body.data?.id ?? body.item?.id;
        expect(ids.item).toBeTruthy();

        const item = await tenantApi(page, 'GET', `/api/v1/items/${ids.item}`);
        expect(item.status).toBe(200);
        expect(item.body.data.is_weighted).toBe(true);
        expectDecimal(item.body.data.selling_price, '250.000', 'GET /items/{id} selling_price');
    });

    await test.step('6b. owner: stocks 10.000 kg through a credit purchase (API)', async () => {
        const supplier = await tenantApi(page, 'POST', '/api/v1/suppliers', { name: `مورد E2E ${RUN_ID}` });
        expect(supplier.status, JSON.stringify(supplier.body)).toBe(201);
        const supplierId = supplier.body.data?.id;

        const purchase = await tenantApi(page, 'POST', '/api/v1/purchases', {
            supplier_id: supplierId,
            purchase_date: new Date().toISOString().slice(0, 10),
            paid_amount: '0',
            items: [{ item_id: ids.item, quantity: '10.000', unit_cost: '100.000' }],
        });
        expect(purchase.status, JSON.stringify(purchase.body)).toBe(201);

        const item = await tenantApi(page, 'GET', `/api/v1/items/${ids.item}`);
        expectDecimal(item.body.data.current_stock, '10.000', 'GET /items/{id} current_stock');

        const summary = await tenantApi(page, 'GET', '/api/v1/treasury/summary');
        expect(summary.status, JSON.stringify(summary.body)).toBe(200);
        treasury.before = summary.body;
    });

    await test.step('6c. owner: opens a shift (if POS requires one) and sells 0.250 kg cash at the POS', async () => {
        await page.goto(`${TENANT}/pos`);
        await expect(page.getByTestId('pos-checkout-submit')).toBeVisible({ timeout: 20_000 });

        const gate = page.getByTestId('pos-shift-gate');
        if (await gate.isVisible()) {
            await page.locator('#pos-shift-opening-cash').fill('100');
            const opened = waitForApi(page, 'POST', /\/api\/v1\/shifts\/open$/u);
            await page.getByTestId('pos-shift-open-form').locator('button[type="submit"]').click();
            const res = await opened;
            const body = await json(res);
            expect(res.status(), JSON.stringify(body)).toBe(201);
            ids.shift = body.data?.id;
            await expect(gate).toBeHidden();
        } else {
            const opened = await tenantApi(page, 'POST', '/api/v1/shifts/open', { opening_cash_balance: '100.000' });
            expect(opened.status, JSON.stringify(opened.body)).toBe(201);
            ids.shift = opened.body.data?.id;
            await page.reload();
            await expect(page.getByTestId('pos-checkout-submit')).toBeVisible();
        }

        await page.getByTestId('pos-product-tile').filter({ hasText: ITEM_NAME }).first().click();
        await expect(page.getByTestId('pos-weight-picker')).toBeVisible();
        await page.getByTestId('pos-weight-input').fill('0.250');
        await expect(page.getByTestId('pos-weight-qty')).toContainText('0.250');
        await page.getByTestId('pos-weight-confirm').click();
        await expect(page.locator('[data-testid="pos-cart-qty"]:visible').first()).toHaveValue('0.250');

        await page.getByTestId('pos-payment-type-cash').click();
        await page.getByTestId('pos-cash-received').fill('100');

        const sale = waitForApi(page, 'POST', /\/api\/v1\/(invoices|pos\/checkout)$/u);
        await page.getByTestId('pos-checkout-submit').click();
        const res = await sale;
        const body = await json(res);
        expect(res.status(), JSON.stringify(body)).toBe(201);
        const payload = res.request().postDataJSON();
        expect(payload.payment_type).toBe('cash');
        expect(payload.items).toEqual([expect.objectContaining({ item_id: ids.item, quantity: '0.250' })]);
        state.invoice = body.data;
        expectDecimal(state.invoice.net_total, '62.500', 'POST /invoices response net_total (InvoiceResource)');
        expectDecimal(state.invoice.change_amount, '37.500', 'POST /invoices response change_amount');
        await expect(page.getByTestId('pos-success-change-amount')).toContainText('37.5');
        await page.keyboard.press('Escape');
        await expect(page.getByTestId('pos-success-change')).toHaveCount(0);
    });

    // ---------------------------------------------------------------------------------------------
    await softStep('7. server effects: stock, invoice views, treasury', async () => {
        const item = await tenantApi(page, 'GET', `/api/v1/items/${ids.item}`);
        expectDecimal(item.body.data.current_stock, '9.750', 'GET /items/{id} current_stock');

        const invoice = await tenantApi(page, 'GET', `/api/v1/invoices/${state.invoice.id}`);
        expect(invoice.status).toBe(200);
        expect(invoice.body.data.invoice_number).toBe(state.invoice.invoice_number);

        const summary = await tenantApi(page, 'GET', '/api/v1/treasury/summary');
        test.info().annotations.push({
            type: 'treasury',
            description: JSON.stringify({ before: treasury.before, afterSale: summary.body }),
        });
        treasury.afterSale = summary.body;
        assertCashDelta(treasury.before, summary.body, '62.500', 'cash sale of 62.500');

        await page.goto(`${TENANT}/invoices`);
        await expect(page.getByText(state.invoice.invoice_number).first()).toBeVisible({ timeout: 15_000 });
        await page.goto(`${TENANT}/invoices/${state.invoice.id}`);
        await expect(page.getByText(state.invoice.invoice_number).first()).toBeVisible({ timeout: 15_000 });
        await expect(page.getByText(ITEM_NAME).first()).toBeVisible();
        await page.goto(`${TENANT}/invoices/${state.invoice.id}/print`);
        const receipt = page.locator('#receipt-print-area');
        await expect(receipt).toBeVisible({ timeout: 15_000 });
        await expect(receipt).toContainText(state.invoice.invoice_number);
        await expect(receipt).toContainText(ITEM_NAME);
    });

    // ---------------------------------------------------------------------------------------------
    await softStep('8. return 0.100 kg from the POS recent sales; stock and cash reverse exactly', async () => {
        await page.goto(`${TENANT}/pos`);
        await expect(page.getByTestId('pos-checkout-submit')).toBeVisible({ timeout: 20_000 });
        await page.getByTestId('pos-recent-sales-btn').click();
        const row = page
            .getByTestId('pos-recent-sales-list')
            .locator('li')
            .filter({ hasText: state.invoice.invoice_number });
        await row.getByTestId('pos-recent-sales-return').click();
        await expect(page.getByTestId('pos-quick-return-modal')).toBeVisible();
        await page.getByTestId('pos-quick-return-qty').first().fill('0.100');
        const cash = page.getByTestId('refund-method-cash');
        if (await cash.isEnabled()) await cash.click();

        const booked = waitForApi(page, 'POST', /\/api\/v1\/returns$/u);
        await page.getByTestId('pos-quick-return-confirm').click();
        const res = await booked;
        const body = await json(res);
        expect(res.status(), JSON.stringify(body)).toBe(201);
        expect(res.request().postDataJSON()).toMatchObject({
            return_type: 'sales_return',
            invoice_id: state.invoice.id,
            refund_method: 'cash',
            items: [expect.objectContaining({ quantity: '0.100' })],
        });

        const item = await tenantApi(page, 'GET', `/api/v1/items/${ids.item}`);
        expectDecimal(item.body.data.current_stock, '9.850', 'GET /items/{id} current_stock');

        const summary = await tenantApi(page, 'GET', '/api/v1/treasury/summary');
        test.info().annotations.push({ type: 'treasury-after-return', description: JSON.stringify(summary.body) });
        assertCashDelta(treasury.afterSale, summary.body, '-25.000', 'cash refund of 25.000');
    });

    await softStep('8b. close the shift from the POS and read its Z report', async () => {
        await page.goto(`${TENANT}/pos`);
        await expect(page.getByTestId('pos-checkout-submit')).toBeVisible({ timeout: 20_000 });
        await page.getByTestId('pos-shift-badge').click();
        await expect(page.getByTestId('pos-shift-panel')).toBeVisible();
        await expect(page.getByTestId('pos-shift-report')).toBeVisible();
        // opening 100.000 + cash sale 62.500 - cash refund 25.000
        await page.locator('#pos-shift-counted-cash').fill('137.5');
        const closed = waitForApi(page, 'POST', /\/api\/v1\/shifts\/close$/u);
        await page.getByTestId('pos-shift-close').click();
        const res = await closed;
        const body = await json(res);
        expect(res.status(), JSON.stringify(body)).toBe(200);
        expect(res.request().postDataJSON().actual_cash_balance).toBe('137.500');

        const shiftId = ids.shift ?? res.request().postDataJSON().shift_id;
        const z = await tenantApi(page, 'GET', `/api/v1/shifts/${shiftId}/z-report`);
        expect(z.status, JSON.stringify(z.body)).toBe(200);
        const report = z.body.report ?? z.body.data;
        expect(report.status).toBe('closed');
        expectDecimal(report.opening_cash_balance, '100.000', 'Z report opening_cash_balance');
        expectDecimal(report.total_cash_sales, '62.500', 'Z report total_cash_sales');
        expectDecimal(report.total_refunds, '25.000', 'Z report total_refunds');
        expectDecimal(report.expected_cash_balance, '137.500', 'Z report expected_cash_balance');
        expectDecimal(report.cash_difference, '0.000', 'Z report cash_difference');
        test.info().annotations.push({ type: 'z-report', description: JSON.stringify(z.body).slice(0, 2000) });
    });

    // ---------------------------------------------------------------------------------------------
    await softStep('9. isolation: the owner token is useless on tenant demo', async () => {
        const token = await page.evaluate(() => localStorage.getItem('auth_token'));
        expect(token).toBeTruthy();
        const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };

        const demoMe = await request.get(`${LOCAL_HOSTS.tenant('demo')}/api/v1/auth/me`, { headers });
        expect([401, 403], `demo /auth/me answered ${demoMe.status()}`).toContain(demoMe.status());
        const demoItems = await request.get(`${LOCAL_HOSTS.tenant('demo')}/api/v1/items`, { headers });
        expect([401, 403]).toContain(demoItems.status());

        // A spoofed X-Tenant on the owner's own host must never switch the tenant.
        const spoofed = await request.get(`${TENANT}/api/v1/auth/me`, {
            headers: { ...headers, 'X-Tenant': 'demo' },
        });
        const spoofedBody = await json(spoofed);
        if (spoofed.status() === 200) {
            expect(JSON.stringify(spoofedBody)).toContain(OWNER_EMAIL);
        } else {
            expect([400, 401, 403, 404]).toContain(spoofed.status());
        }
    });

    // ---------------------------------------------------------------------------------------------
    await softStep('10. cleanup: cancel + archive the E2E tenant through the console API', async () => {
        await page.goto(`${ADMIN}/super-admin/dashboard`);
        const centralToken = await page.evaluate(() => localStorage.getItem('central_auth_token'));
        expect(centralToken, 'console session still open').toBeTruthy();
        const headers = { Accept: 'application/json', Authorization: `Bearer ${centralToken}` };

        // The step-up from step 3b is still fresh on this token (15 min window).
        const cancelled = await request.post(`${ADMIN}/api/v1/super-admin/tenants/${state.tenantId}/toggle-status`, {
            headers,
            data: { status: 'cancelled', note: 'E2E run cleanup' },
        });
        expect(cancelled.status(), JSON.stringify(await json(cancelled))).toBe(200);

        const archived = await request.delete(`${ADMIN}/api/v1/super-admin/tenants/${state.tenantId}`, {
            headers,
            data: { confirm_code: SLUG, expected_status: 'cancelled', note: 'E2E run cleanup' },
        });
        const archivedBody = await json(archived);
        test.info().annotations.push({ type: 'cleanup-archive', description: JSON.stringify(archivedBody).slice(0, 1000) });
        expect(archived.status(), JSON.stringify(archivedBody)).toBe(202);

        // The archive job takes a verified final backup, then flips the tenant to `archived`.
        await expect
            .poll(
                async () => {
                    const res = await request.get(`${ADMIN}/api/v1/super-admin/tenants/${state.tenantId}`, { headers });
                    const body = await json(res);
                    return body.data?.status ?? body.data?.tenant?.status ?? body.tenant?.status ?? res.status();
                },
                { timeout: 120_000, intervals: [2_000, 3_000, 5_000] }
            )
            .toBe('archived');
    });

    await test.step('10b. cleanup: deactivate the throwaway operator and revoke its tokens', async () => {
        if (!state.operatorId) return;
        tinker(
            '$u = App\\Models\\CentralUser::query()->find((int) getenv("E2E_OP_ID")); if ($u) { $u->tokens()->delete(); $u->forceFill(["is_active" => false])->save(); } echo "OK=1";',
            { E2E_OP_ID: state.operatorId }
        );
    });

    expect.soft(productionHits, 'requests to production hosts (aborted)').toEqual([]);
    expect.soft(consoleErrors, 'browser console errors during the journey').toEqual([]);
});

/** Compares every cash-like numeric field of two treasury summaries and expects one moved by `delta`. */
function assertCashDelta(before, after, delta, label) {
    const flatten = (obj, prefix = '') =>
        Object.entries(obj ?? {}).flatMap(([key, value]) =>
            value !== null && typeof value === 'object'
                ? flatten(value, `${prefix}${key}.`)
                : [[`${prefix}${key}`, value]]
        );
    const toMilli = (v) => Math.round(Number(v) * 1000);
    const a = Object.fromEntries(flatten(before));
    const moved = flatten(after)
        .filter(([key, value]) => /cash|balance|treasury/iu.test(key) && key in a && !Number.isNaN(Number(value)))
        .filter(([key, value]) => toMilli(value) - toMilli(a[key]) === toMilli(delta));
    expect(moved.length, `${label}: a cash/balance field must move by exactly ${delta}`).toBeGreaterThan(0);
    for (const [key, value] of moved) {
        expect(String(value), `${key} is a 3-decimal string`).toMatch(/^-?\d+\.\d{3}$/u);
    }
}
