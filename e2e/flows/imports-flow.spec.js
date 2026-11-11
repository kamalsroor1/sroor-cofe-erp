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
import { test, expect } from '@playwright/test';
import { skipOnPhoneViewport } from '../utils/pos-viewport.js';

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
        expect(commitPayload).toEqual({ token: 'mock-import-token-12345', skip_invalid: false });
    });
});
