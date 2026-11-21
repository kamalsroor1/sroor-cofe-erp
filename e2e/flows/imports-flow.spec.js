// PRD-06: Excel/CSV import flow E2E test.
// Covers:
//  - Selecting import type (items, customers, suppliers)
//  - Uploading spreadsheet with Arabic entity names and opening balances
//  - Validating preview step (summary counts, data table, skip-invalid handling)
//  - Committing import and verifying results summary
//
// POST /api/v1/imports/items/preview and /commit are mocked via page.route so nothing is written
// to the persistent database and tests run deterministically. Selectors use data-testid, never
// translated text.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport } from '../utils/pos-viewport.js';
import { REPO_ROOT } from '../utils/local-stack.js';
import { t } from '../utils/translations.js';

const MOCK_ITEMS_PREVIEW = {
    success: true,
    data: {
        token: 'mock-import-token-12345',
        type: 'items',
        summary: {
            total: 3,
            valid: 3,
            invalid: 0,
            duplicates: 0,
        },
        rows: [
            {
                row_number: 2,
                status: 'valid',
                data: {
                    name: 'بن برازيلي وسط',
                    code: 'BRAZIL-MED',
                    category: 'قهوة',
                    unit: 'كجم',
                    cost_price: '350.000',
                    selling_price: '450.000',
                    opening_quantity: '25.000',
                    opening_cost: '350.000',
                },
                errors: [],
            },
            {
                row_number: 3,
                status: 'valid',
                data: {
                    name: 'بن كولومبي فاخر',
                    code: 'COLOMB-PRM',
                    category: 'قهوة',
                    unit: 'كجم',
                    cost_price: '420.000',
                    selling_price: '520.000',
                    opening_quantity: '15.000',
                    opening_cost: '420.000',
                },
                errors: [],
            },
            {
                row_number: 4,
                status: 'valid',
                data: {
                    name: 'شاي أخضر سيلاني',
                    code: 'TEA-GREEN',
                    category: 'شاي',
                    unit: 'قطعة',
                    cost_price: '45.000',
                    selling_price: '65.000',
                    opening_quantity: '50.000',
                    opening_cost: '45.000',
                },
                errors: [],
            },
        ],
        rows_truncated: false,
    },
};

const MOCK_COMMIT_RESULT = {
    success: true,
    data: {
        type: 'items',
        created: 3,
        skipped: 0,
        duplicates: 0,
        errors: [],
    },
};

test.describe('PRD-06: Imports Flow Journey', () => {
    // The preview table is the md+ layout; phones render cards instead.
    skipOnPhoneViewport(test);

    test('complete wizard: select items type, upload CSV, preview, and commit', async ({ page }) => {
        let commitPayload = null;

        await page.route('**/api/v1/imports/items/preview', (route) => {
            if (route.request().method() !== 'POST') return route.fallback();
            return route.fulfill({ status: 200, json: MOCK_ITEMS_PREVIEW });
        });

        await page.route('**/api/v1/imports/items/commit', async (route) => {
            if (route.request().method() !== 'POST') return route.fallback();
            commitPayload = route.request().postDataJSON();
            return route.fulfill({ status: 200, json: MOCK_COMMIT_RESULT });
        });

        // Step 1: open the import page
        await page.goto('/imports', { waitUntil: 'networkidle' });

        // Step 2: choose the items type and continue
        await page.getByTestId('import-type-items').click();
        const continueBtn = page.getByTestId('import-type-continue');
        await expect(continueBtn).toBeEnabled();
        await continueBtn.click();

        // Step 3: upload a CSV with Arabic items
        const csvContent =
            'name,code,category,unit,cost_price,selling_price,opening_quantity,opening_cost\n' +
            'بن برازيلي وسط,BRAZIL-MED,قهوة,كجم,350.000,450.000,25.000,350.000\n' +
            'بن كولومبي فاخر,COLOMB-PRM,قهوة,كجم,420.000,520.000,15.000,420.000\n' +
            'شاي أخضر سيلاني,TEA-GREEN,شاي,قطعة,45.000,65.000,50.000,45.000\n';

        await page.getByTestId('import-file-input').setInputFiles({
            name: 'items_import.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from(csvContent, 'utf-8'),
        });

        // Step 4: the preview shows the summary counts and one table row per sheet row
        await expect(page.getByTestId('import-preview-step')).toBeVisible({ timeout: 15000 });
        await expect(page.getByTestId('import-summary-all-count')).toHaveText('3');
        await expect(page.getByTestId('import-summary-valid-count')).toHaveText('3');
        await expect(page.getByTestId('import-summary-invalid-count')).toHaveText('0');
        for (const rowNumber of [2, 3, 4]) {
            await expect(page.getByTestId(`import-preview-row-${rowNumber}`)).toBeVisible();
        }

        // Step 5: commit (the confirmation dialog is SweetAlert2; its confirm button has a fixed class)
        const commitBtn = page.getByTestId('import-commit-btn');
        await expect(commitBtn).toBeEnabled();
        await commitBtn.click();
        await page.locator('.swal2-confirm').click();

        // Step 6: the result summary shows the created / skipped / duplicate counts
        await expect(page.getByTestId('import-result-step')).toBeVisible({ timeout: 15000 });
        await expect(page.getByTestId('import-result-created')).toHaveText('3');
        await expect(page.getByTestId('import-result-skipped')).toHaveText('0');
        await expect(page.getByTestId('import-result-duplicates')).toHaveText('0');

        // The commit body carries the token returned by the preview and the skip flag
        expect(commitPayload).toEqual({
            token: 'mock-import-token-12345',
            skip_invalid: false,
            confirm_new_categories: false,
        });
    });
});

// PRD-06: the same wizard against the REAL API (nothing mocked): a real .xlsx of items and a real .csv of
// customers, both with Arabic names and Arabic-Indic digits, generated per run so reruns never collide.
// The opening stock / balance must land in the item's movements and the customer's balance.
test.describe('PRD-06: Imports with real Arabic files', () => {
    skipOnPhoneViewport(test);

    const RUN = Date.now().toString(36);
    const workDir = fs.mkdtempSync(path.join(os.tmpdir(), 'e2e-imports-'));
    test.afterAll(() => fs.rmSync(workDir, { recursive: true, force: true }));

    /** Arabic-Indic digits, as typed on an Arabic keyboard / exported by Arabic Excel. */
    const arabicDigits = (value) => String(value).replace(/[0-9]/gu, (d) => '٠١٢٣٤٥٦٧٨٩'[Number(d)]);

    /** Tenant API with the session the SPA stored (token, tenant, store). */
    const api = (page, url) =>
        page.evaluate(async (target) => {
            const headers = { Accept: 'application/json' };
            const token = localStorage.getItem('auth_token');
            const tenant = localStorage.getItem('active_tenant');
            const store = localStorage.getItem('current_store_id');
            if (token) headers.Authorization = `Bearer ${token}`;
            if (tenant) headers['X-Tenant'] = tenant;
            if (store) headers['X-Store-Id'] = store;
            const res = await fetch(target, { headers });
            return { status: res.status, body: await res.json().catch(() => null) };
        }, url);

    const runWizard = async (page, { type, file, expectSummary, skipInvalid = false }) => {
        await page.goto('/imports', { waitUntil: 'networkidle' });
        await page.getByTestId(`import-type-${type}`).click();
        await page.getByTestId('import-type-continue').click();

        const preview = page.waitForResponse((res) => res.url().includes(`/api/v1/imports/${type}/preview`));
        await page.getByTestId('import-file-input').setInputFiles(file);
        const previewRes = await preview;
        expect(previewRes.status(), await previewRes.text()).toBe(200);

        await expect(page.getByTestId('import-preview-step')).toBeVisible({ timeout: 15_000 });
        for (const [chip, count] of Object.entries(expectSummary)) {
            await expect(page.getByTestId(`import-summary-${chip}-count`)).toHaveText(String(count));
        }
        if (skipInvalid) {
            await page.getByText(t('import.ui.skip_invalid'), { exact: true }).click();
        }
        const confirmCategories = page.getByTestId('import-confirm-categories');
        if (await confirmCategories.isVisible()) await confirmCategories.check();

        const commit = page.waitForResponse((res) => res.url().includes(`/api/v1/imports/${type}/commit`));
        await page.getByTestId('import-commit-btn').click();
        await page.locator('.swal2-confirm').click(); // SweetAlert2 confirmation, as in the mocked journey
        const commitRes = await commit;
        expect(commitRes.status(), await commitRes.text()).toBe(200);
        await expect(page.getByTestId('import-result-step')).toBeVisible({ timeout: 30_000 });
    };

    test('items .xlsx: Arabic rows import with opening stock, the bad row is skipped', async ({ page }) => {
        const labels = (keys) => keys.map((key) => t(`import.columns.items.${key}`));
        const columns = ['code', 'name', 'category', 'unit', 'cost_price', 'selling_price', 'is_weighted', 'opening_quantity', 'opening_cost'];
        const yes = t('import.examples.items.is_weighted');
        const rows = [
            labels(columns),
            [`YMN-${RUN}`, `بن يمني مطحون ${RUN}`, 'بن', 'كجم', arabicDigits('350'), arabicDigits('450'), yes, '١٢٫٥', arabicDigits('350')],
            [`HIL-${RUN}`, `حبهان مطحون ${RUN}`, 'توابل', 'كجم', '800', '950', yes, '3.25', '800'],
            [`BAD-${RUN}`, `صنف بسعر غلط ${RUN}`, 'بن', 'كجم', '10', 'سعر', yes, '', ''],
        ];
        const file = path.join(workDir, `items-${RUN}.xlsx`);
        execFileSync('php', [path.join(REPO_ROOT, 'e2e', 'utils', 'make-xlsx.php'), file], { input: JSON.stringify(rows) });

        await runWizard(page, { type: 'items', file, expectSummary: { valid: 2, invalid: 1 }, skipInvalid: true });
        await expect(page.getByTestId('import-result-created')).toHaveText('2');
        await expect(page.getByTestId('import-result-skipped')).toHaveText('1');

        const list = await api(page, `/api/v1/items?search=${encodeURIComponent(`YMN-${RUN}`)}`);
        expect(list.status).toBe(200);
        const item = (list.body.data ?? []).find((row) => row.code === `YMN-${RUN}`);
        expect(item, JSON.stringify(list.body).slice(0, 500)).toBeTruthy();
        expect(item.name).toBe(`بن يمني مطحون ${RUN}`);
        expect(item.selling_price).toBe('450.000');
        expect(item.current_stock).toBe('12.500');

        const movements = await api(page, `/api/v1/items/${item.id}/movements`);
        expect(movements.status, JSON.stringify(movements.body).slice(0, 500)).toBe(200);
        expect(JSON.stringify(movements.body)).toContain('12.500');

        const bad = await api(page, `/api/v1/items?search=${encodeURIComponent(`BAD-${RUN}`)}`);
        expect((bad.body.data ?? []).some((row) => row.code === `BAD-${RUN}`)).toBe(false);
    });

    test('customers .csv (UTF-8): Arabic names with an opening balance in Arabic digits', async ({ page }) => {
        const columns = ['name', 'phone', 'address', 'price_tier', 'opening_balance'];
        const name = `عميل استيراد ${RUN}`;
        const csv = [
            columns.map((key) => t(`import.columns.customers.${key}`)),
            [name, `0100${String(Date.now()).slice(-7)}`, 'المنصورة', t('import.examples.customers.price_tier'), arabicDigits('1500')],
        ]
            .map((row) => row.map((cell) => `"${String(cell).replace(/"/gu, '""')}"`).join(','))
            .join('\r\n');
        const file = path.join(workDir, `customers-${RUN}.csv`);
        fs.writeFileSync(file, `\uFEFF${csv}\r\n`, 'utf8');

        await runWizard(page, { type: 'customers', file, expectSummary: { valid: 1, invalid: 0 } });
        await expect(page.getByTestId('import-result-created')).toHaveText('1');

        const list = await api(page, `/api/v1/customers?search=${encodeURIComponent(name)}`);
        expect(list.status).toBe(200);
        const customer = (list.body.data ?? []).find((row) => row.name === name);
        expect(customer, JSON.stringify(list.body).slice(0, 500)).toBeTruthy();
        expect(customer.current_balance).toBe('1500.000');
    });
});
